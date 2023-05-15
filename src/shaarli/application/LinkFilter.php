<?php

/**
 * Class LinkFilter.
 *
 * Perform search and filter operation on link data list.
 */
class LinkFilter
{
    /**
     * @var string permalinks.
     */
    public static $FILTER_HASH   = 'permalink';

    /**
     * @var string text search.
     */
    public static $FILTER_TEXT   = 'fulltext';

    /**
     * @var string tag filter.
     */
    public static $FILTER_TAG    = 'tags';

    /**
     * @var string filter by day.
     */
    public static $FILTER_DAY    = 'FILTER_DAY';

    /**
     * @var string Allowed characters for hashtags (regex syntax).
     */
    public static $HASHTAG_CHARS = '\p{Pc}\p{N}\p{L}\p{Mn}';

    /**
     * @var LinkDB all available links.
     */
    private $links;

    /**
     * @param LinkDB $links initialization.
     */
    public function __construct($links)
    {
        $this->links = $links;
    }

    /**
     * Filter links according to parameters.
     *
     * @param string $type          Type of filter (eg. tags, permalink, etc.).
     * @param mixed  $request       Filter content.
     * @param bool   $casesensitive Optional: Perform case sensitive filter if true.
     * @param string $visibility    Optional: return only all/private/public links
     * @param string $untaggedonly  Optional: return only untagged links. Applies only if $type includes FILTER_TAG
     *
     * @return array filtered link list.
     */
    public function filter($type, $request, $casesensitive = false, $visibility = 'all', $untaggedonly = false)
    {
        if (! in_array($visibility, ['all', 'public', 'private'])) {
            $visibility = 'all';
        }

        switch ($type) {
            case self::$FILTER_HASH:
                return $this->filterSmallHash($request);
            case self::$FILTER_TAG | self::$FILTER_TEXT: // == "vuotext"
                $noRequest = empty($request) || (empty($request[0]) && empty($request[1]));
                if ($noRequest) {
                    if ($untaggedonly) {
                        return $this->filterUntagged($visibility);
                    }
                    return $this->noFilter($visibility);
                }
                if ($untaggedonly) {
                    $filtered = $this->filterUntagged($visibility);
                } else {
                    $filtered = $this->links;
                }
                if (!empty($request[0])) {
                    $filtered = (new LinkFilter($filtered))->filterTags($request[0], $casesensitive, $visibility);
                }
                if (!empty($request[1])) {
                    $filtered = (new LinkFilter($filtered))->filterFulltext($request[1], $visibility);
                }
                return $filtered;
            case self::$FILTER_TEXT:
                return $this->filterFulltext($request, $visibility);
            case self::$FILTER_TAG:
                if ($untaggedonly) {
                    return $this->filterUntagged($visibility);
                } else {
                    return $this->filterTags($request, $casesensitive, $visibility);
                }
            case self::$FILTER_DAY:
                return $this->filterDay($request);
            default:
                return $this->noFilter($visibility);
        }
    }

    /**
     * Unknown filter, but handle private only.
     *
     * @param string $visibility Optional: return only all/private/public links
     *
     * @return array filtered links.
     */
    private function noFilter($visibility = 'all')
    {
        if ($visibility === 'all') {
            return $this->links;
        }

        $out = array();
        foreach ($this->links as $key => $value) {
            if ($value['private'] && $visibility === 'private') {
                $out[$key] = $value;
            } elseif (! $value['private'] && $visibility === 'public') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * Returns the shaare corresponding to a smallHash.
     *
     * @param string $smallHash permalink hash.
     *
     * @return array $filtered array containing permalink data.
     *
     * @throws LinkNotFoundException if the smallhash doesn't match any link.
     */
    private function filterSmallHash($smallHash)
    {
        $filtered = array();
        foreach ($this->links as $key => $l) {
            if ($smallHash == $l['shorturl']) {
                // Yes, this is ugly and slow
                $filtered[$key] = $l;
                return $filtered;
            }
        }

        if (empty($filtered)) {
            throw new LinkNotFoundException();
        }

        return $filtered;
    }

    /**
     * Returns the list of links corresponding to a full-text search
     *
     * Searches:
     *  - in the URLs, title and description;
     *  - are case-insensitive;
     *  - terms surrounded by quotes " are exact terms search.
     *  - terms starting with a dash - are excluded (except exact terms).
     *
     * Example:
     *    print_r($mydb->filterFulltext('hollandais'));
     *
     * mb_convert_case($val, MB_CASE_LOWER, 'UTF-8')
     *  - allows to perform searches on Unicode text
     *  - see https://github.com/shaarli/Shaarli/issues/75 for examples
     *
     * @param string $searchterms search query.
     * @param string $visibility Optional: return only all/private/public links.
     *
     * @return array search results.
     */
    private function filterFulltext($searchterms, $visibility = 'all')
    {
        if (empty($searchterms)) {
            return $this->noFilter($visibility);
        }

        $filtered = array();
        $search = mb_convert_case(html_entity_decode($searchterms), MB_CASE_LOWER, 'UTF-8');
        $exactRegex = '/"([^"]+)"/';
        // Retrieve exact search terms.
        preg_match_all($exactRegex, $search, $exactSearch);
        $exactSearch = array_values(array_filter($exactSearch[1]));

        // Remove exact search terms to get AND terms search.
        $explodedSearchAnd = explode(' ', trim(preg_replace($exactRegex, '', $search)));
        $explodedSearchAnd = array_values(array_filter($explodedSearchAnd));

        // Filter excluding terms and update andSearch.
        $excludeSearch = array();
        $andSearch = array();
        foreach ($explodedSearchAnd as $needle) {
            if ($needle[0] == '-' && strlen($needle) > 1) {
                $excludeSearch[] = substr($needle, 1);
            } else {
                $andSearch[] = $needle;
            }
        }

        $keys = array('title', 'description', 'url', 'tags');

        // Iterate over every stored link.
        foreach ($this->links as $id => $link) {
            // ignore non private links when 'privatonly' is on.
            if ($visibility !== 'all') {
                if (! $link['private'] && $visibility === 'private') {
                    continue;
                } elseif ($link['private'] && $visibility === 'public') {
                    continue;
                }
            }

            // Concatenate link fields to search across fields.
            // Adds a '\' separator for exact search terms.
            $content = '';
            foreach ($keys as $key) {
                $content .= mb_convert_case($link[$key], MB_CASE_LOWER, 'UTF-8') . '\\';
            }

            // Be optimistic
            $found = true;

            // First, we look for exact term search
            for ($i = 0; $i < count($exactSearch) && $found; $i++) {
                $found = strpos($content, $exactSearch[$i]) !== false;
            }

            // Iterate over keywords, if keyword is not found,
            // no need to check for the others. We want all or nothing.
            for ($i = 0; $i < count($andSearch) && $found; $i++) {
                $found = strpos($content, $andSearch[$i]) !== false;
            }

            // Exclude terms.
            for ($i = 0; $i < count($excludeSearch) && $found; $i++) {
                $found = strpos($content, $excludeSearch[$i]) === false;
            }

            if ($found) {
                $filtered[$id] = $link;
            }
        }

        return $filtered;
    }

    /**
     * generate a regex fragment out of a tag
     * @param string $tag to to generate regexs from. may start with '-' to negate, contain '*' as wildcard
     * @return string generated regex fragment
     */
    private static function tag2regex($tag)
    {
        $len = strlen($tag);
        if (!$len || $tag === "-" || $tag === "*") {
            // nothing to search, return empty regex
            return '';
        }
        if ($tag[0] === "-") {
            // query is negated
            $i = 1; // use offset to start after '-' character
            $regex = '(?!'; // create negative lookahead
        } else {
            $i = 0; // start at first character
            $regex = '(?='; // use positive lookahead
        }
        $regex .= '.*(?:^| )'; // before tag may only be a space or the beginning
        // iterate over string, separating it into placeholder and content
        for (; $i < $len; $i++) {
            if ($tag[$i] === '*') {
                // placeholder found
                $regex .= '[^ ]*?';
            } else {
                // regular characters
                $offset = strpos($tag, '*', $i);
                if ($offset === false) {
                    // no placeholder found, set offset to end of string
                    $offset = $len;
                }
                // subtract one, as we want to get before the placeholder or end of string
                $offset -= 1;
                // we got a tag name that we want to search for. escape any regex characters to prevent conflicts.
                $regex .= preg_quote(substr($tag, $i, $offset - $i + 1), '/');
                // move $i on
                $i = $offset;
            }
        }
        $regex .= '(?:$| ))'; // after the tag may only be a space or the end
        return $regex;
    }

    /**
     * Returns the list of links associated with a given list of tags
     *
     * You can specify one or more tags, separated by space or a comma, e.g.
     *  print_r($mydb->filterTags('linux programming'));
     *
     * @param string $tags          list of tags separated by commas or blank spaces.
     * @param bool   $casesensitive ignore case if false.
     * @param string $visibility    Optional: return only all/private/public links.
     *
     * @return array filtered links.
     */
    public function filterTags($tags, $casesensitive = false, $visibility = 'all')
    {
        // get single tags (we may get passed an array, even though the docs say different)
        $inputTags = $tags;
        if (!is_array($tags)) {
            // we got an input string, split tags
            $inputTags = preg_split('/(?:\s+)|,/', $inputTags, -1, PREG_SPLIT_NO_EMPTY);
        }

        if (!count($inputTags)) {
            // no input tags
            return $this->noFilter($visibility);
        }

        // build regex from all tags
        $re = '/^' . implode(array_map("self::tag2regex", $inputTags)) . '.*$/';
        if (!$casesensitive) {
            // make regex case insensitive
            $re .= 'i';
        }

        // create resulting array
        $filtered = array();

        // iterate over each link
        foreach ($this->links as $key => $link) {
            // check level of visibility
            // ignore non private links when 'privateonly' is on.
            if ($visibility !== 'all') {
                if (! $link['private'] && $visibility === 'private') {
                    continue;
                } elseif ($link['private'] && $visibility === 'public') {
                    continue;
                }
            }
            $search = $link['tags']; // build search string, start with tags of current link
            if (strlen(trim($link['description'])) && strpos($link['description'], '#') !== false) {
                // description given and at least one possible tag found
                $descTags = array();
                // find all tags in the form of #tag in the description
                preg_match_all(
                    '/(?<![' . self::$HASHTAG_CHARS . '])#([' . self::$HASHTAG_CHARS . ']+?)\b/sm',
                    $link['description'],
                    $descTags
                );
                if (count($descTags[1])) {
                    // there were some tags in the description, add them to the search string
                    $search .= ' ' . implode(' ', $descTags[1]);
                }
            };
            // match regular expression with search string
            if (!preg_match($re, $search)) {
                // this entry does _not_ match our regex
                continue;
            }
            $filtered[$key] = $link;
        }
        return $filtered;
    }

    /**
     * Return only links without any tag.
     *
     * @param string $visibility return only all/private/public links.
     *
     * @return array filtered links.
     */
    public function filterUntagged($visibility)
    {
        $filtered = [];
        foreach ($this->links as $key => $link) {
            if ($visibility !== 'all') {
                if (! $link['private'] && $visibility === 'private') {
                    continue;
                } elseif ($link['private'] && $visibility === 'public') {
                    continue;
                }
            }

            if (empty(trim($link['tags']))) {
                $filtered[$key] = $link;
            }
        }

        return $filtered;
    }

    /**
     * Returns the list of articles for a given day, chronologically sorted
     *
     * Day must be in the form 'YYYYMMDD' (e.g. '20120125'), e.g.
     *  print_r($mydb->filterDay('20120125'));
     *
     * @param string $day day to filter.
     *
     * @return array all link matching given day.
     *
     * @throws Exception if date format is invalid.
     */
    public function filterDay($day)
    {
        if (! checkDateFormat('Ymd', $day)) {
            throw new Exception('Invalid date format');
        }

        $filtered = array();
        foreach ($this->links as $key => $l) {
            if ($l['created']->format('Ymd') == $day) {
                $filtered[$key] = $l;
            }
        }

        // sort by date ASC
        return array_reverse($filtered, true);
    }

    /**
     * Convert a list of tags (str) to an array. Also
     * - handle case sensitivity.
     * - accepts spaces commas as separator.
     *
     * @param string $tags          string containing a list of tags.
     * @param bool   $casesensitive will convert everything to lowercase if false.
     *
     * @return array filtered tags string.
     */
    public static function tagsStrToArray($tags, $casesensitive)
    {
        // We use UTF-8 conversion to handle various graphemes (i.e. cyrillic, or greek)
        $tagsOut = $casesensitive ? $tags : mb_convert_case($tags, MB_CASE_LOWER, 'UTF-8');
        $tagsOut = str_replace(',', ' ', $tagsOut);

        return preg_split('/\s+/', $tagsOut, -1, PREG_SPLIT_NO_EMPTY);
    }
}

class LinkNotFoundException extends Exception
{
    /**
     * LinkNotFoundException constructor.
     */
    public function __construct()
    {
        $this->message =  t('The link you are trying to reach does not exist or has been deleted.');
    }
}
