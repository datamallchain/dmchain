<?php
namespace Shaarli\Feed;

use DateTime;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Formatter\BookmarkFormatter;

/**
 * FeedBuilder class.
 *
 * Used to build ATOM and RSS feeds data.
 */
class FeedBuilder
{
    /**
     * @var string Constant: RSS feed type.
     */
    public static $FEED_RSS = 'rss';

    /**
     * @var string Constant: ATOM feed type.
     */
    public static $FEED_ATOM = 'atom';

    /**
     * @var string Default language if the locale isn't set.
     */
    public static $DEFAULT_LANGUAGE = 'en-en';

    /**
     * @var int Number of bookmarks to display in a feed by default.
     */
    public static $DEFAULT_NB_LINKS = 50;

    /**
     * @var BookmarkServiceInterface instance.
     */
    protected $linkDB;

    /**
     * @var BookmarkFormatter instance.
     */
    protected $formatter;

    /**
     * @var string RSS or ATOM feed.
     */
    protected $feedType;

    /**
     * @var array $_SERVER
     */
    protected $serverInfo;

    /**
     * @var array $_GET
     */
    protected $userInput;

    /**
     * @var boolean True if the user is currently logged in, false otherwise.
     */
    protected $isLoggedIn;

    /**
     * @var boolean Use permalinks instead of direct bookmarks if true.
     */
    protected $usePermalinks;

    /**
     * @var boolean true to hide dates in feeds.
     */
    protected $hideDates;

    /**
     * @var string server locale.
     */
    protected $locale;

    /**
     * @var DateTime Latest item date.
     */
    protected $latestDate;

    /**
     * Feed constructor.
     *
     * @param BookmarkServiceInterface $linkDB     LinkDB instance.
     * @param BookmarkFormatter        $formatter  instance.
     * @param string                   $feedType   Type of feed.
     * @param array                    $serverInfo $_SERVER.
     * @param array                    $userInput  $_GET.
     * @param boolean                  $isLoggedIn True if the user is currently logged in, false otherwise.
     */
    public function __construct($linkDB, $formatter, $feedType, $serverInfo, $userInput, $isLoggedIn)
    {
        $this->linkDB = $linkDB;
        $this->formatter = $formatter;
        $this->feedType = $feedType;
        $this->serverInfo = $serverInfo;
        $this->userInput = $userInput;
        $this->isLoggedIn = $isLoggedIn;
    }

    /**
     * Build data for feed templates.
     *
     * @return array Formatted data for feeds templates.
     */
    public function buildData()
    {
        // Search for untagged bookmarks
        if (isset($this->userInput['searchtags']) && empty($this->userInput['searchtags'])) {
            $this->userInput['searchtags'] = false;
        }

        // Optionally filter the results:
        $linksToDisplay = $this->linkDB->search($this->userInput);

        $nblinksToDisplay = $this->getNbLinks(count($linksToDisplay));

        // Can't use array_keys() because $link is a LinkDB instance and not a real array.
        $keys = array();
        foreach ($linksToDisplay as $key => $value) {
            $keys[] = $key;
        }

        $pageaddr = escape(index_url($this->serverInfo));
        $this->formatter->addContextData('index_url', $pageaddr);
        $linkDisplayed = array();
        for ($i = 0; $i < $nblinksToDisplay && $i < count($keys); $i++) {
            $linkDisplayed[$keys[$i]] = $this->buildItem($linksToDisplay[$keys[$i]], $pageaddr);
        }

        $data['language'] = $this->getTypeLanguage();
        $data['last_update'] = $this->getLatestDateFormatted();
        $data['show_dates'] = !$this->hideDates || $this->isLoggedIn;
        // Remove leading slash from REQUEST_URI.
        $data['self_link'] = escape(server_url($this->serverInfo))
            . escape($this->serverInfo['REQUEST_URI']);
        $data['index_url'] = $pageaddr;
        $data['usepermalinks'] = $this->usePermalinks === true;
        $data['links'] = $linkDisplayed;

        return $data;
    }

    /**
     * Build a feed item (one per shaare).
     *
     * @param Bookmark $link     Single link array extracted from LinkDB.
     * @param string   $pageaddr Index URL.
     *
     * @return array Link array with feed attributes.
     */
    protected function buildItem($link, $pageaddr)
    {
        $data = $this->formatter->format($link);
        $data['guid'] = $pageaddr . '?' . $data['shorturl'];
        if ($this->usePermalinks === true) {
            $permalink = '<a href="'. $data['url'] .'" title="'. t('Direct link') .'">'. t('Direct link') .'</a>';
        } else {
            $permalink = '<a href="'. $data['guid'] .'" title="'. t('Permalink') .'">'. t('Permalink') .'</a>';
        }
        $data['description'] .= PHP_EOL . PHP_EOL . '<br>&#8212; ' . $permalink;

        $data['pub_iso_date'] = $this->getIsoDate($data['created']);

        // atom:entry elements MUST contain exactly one atom:updated element.
        if (!empty($link->getUpdated())) {
            $data['up_iso_date'] = $this->getIsoDate($data['updated'], DateTime::ATOM);
        } else {
            $data['up_iso_date'] = $this->getIsoDate($data['created'], DateTime::ATOM);
        }

        // Save the more recent item.
        if (empty($this->latestDate) || $this->latestDate < $data['created']) {
            $this->latestDate = $data['created'];
        }
        if (!empty($data['updated']) && $this->latestDate < $data['updated']) {
            $this->latestDate = $data['updated'];
        }

        return $data;
    }

    /**
     * Set this to true to use permalinks instead of direct bookmarks.
     *
     * @param boolean $usePermalinks true to force permalinks.
     */
    public function setUsePermalinks($usePermalinks)
    {
        $this->usePermalinks = $usePermalinks;
    }

    /**
     * Set this to true to hide timestamps in feeds.
     *
     * @param boolean $hideDates true to enable.
     */
    public function setHideDates($hideDates)
    {
        $this->hideDates = $hideDates;
    }

    /**
     * Set the locale. Used to show feed language.
     *
     * @param string $locale The locale (eg. 'fr_FR.UTF8').
     */
    public function setLocale($locale)
    {
        $this->locale = strtolower($locale);
    }

    /**
     * Get the language according to the feed type, based on the locale:
     *
     *   - RSS format: en-us (default: 'en-en').
     *   - ATOM format: fr (default: 'en').
     *
     * @return string The language.
     */
    public function getTypeLanguage()
    {
        // Use the locale do define the language, if available.
        if (!empty($this->locale) && preg_match('/^\w{2}[_\-]\w{2}/', $this->locale)) {
            $length = ($this->feedType === self::$FEED_RSS) ? 5 : 2;
            return str_replace('_', '-', substr($this->locale, 0, $length));
        }
        return ($this->feedType === self::$FEED_RSS) ? 'en-en' : 'en';
    }

    /**
     * Format the latest item date found according to the feed type.
     *
     * Return an empty string if invalid DateTime is passed.
     *
     * @return string Formatted date.
     */
    protected function getLatestDateFormatted()
    {
        if (empty($this->latestDate) || !$this->latestDate instanceof DateTime) {
            return '';
        }

        $type = ($this->feedType == self::$FEED_RSS) ? DateTime::RSS : DateTime::ATOM;
        return $this->latestDate->format($type);
    }

    /**
     * Get ISO date from DateTime according to feed type.
     *
     * @param DateTime    $date   Date to format.
     * @param string|bool $format Force format.
     *
     * @return string Formatted date.
     */
    protected function getIsoDate(DateTime $date, $format = false)
    {
        if ($format !== false) {
            return $date->format($format);
        }
        if ($this->feedType == self::$FEED_RSS) {
            return $date->format(DateTime::RSS);
        }
        return $date->format(DateTime::ATOM);
    }

    /**
     * Returns the number of link to display according to 'nb' user input parameter.
     *
     * If 'nb' not set or invalid, default value: $DEFAULT_NB_LINKS.
     * If 'nb' is set to 'all', display all filtered bookmarks (max parameter).
     *
     * @param int $max maximum number of bookmarks to display.
     *
     * @return int number of bookmarks to display.
     */
    public function getNbLinks($max)
    {
        if (empty($this->userInput['nb'])) {
            return self::$DEFAULT_NB_LINKS;
        }

        if ($this->userInput['nb'] == 'all') {
            return $max;
        }

        $intNb = intval($this->userInput['nb']);
        if (!is_int($intNb) || $intNb == 0) {
            return self::$DEFAULT_NB_LINKS;
        }

        return $intNb;
    }
}
