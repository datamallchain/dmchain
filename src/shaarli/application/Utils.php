<?php
/**
 * Shaarli utilities
 */

/**
 * Logs a message to a text file
 *
 * The log format is compatible with fail2ban.
 *
 * @param string $logFile  where to write the logs
 * @param string $clientIp the client's remote IPv4/IPv6 address
 * @param string $message  the message to log
 */
function logm($logFile, $clientIp, $message)
{
    file_put_contents(
        $logFile,
        date('Y/m/d H:i:s').' - '.$clientIp.' - '.strval($message).PHP_EOL,
        FILE_APPEND
    );
}

/**
 *  Returns the small hash of a string, using RFC 4648 base64url format
 *
 *  Small hashes:
 *   - are unique (well, as unique as crc32, at last)
 *   - are always 6 characters long.
 *   - only use the following characters: a-z A-Z 0-9 - _ @
 *   - are NOT cryptographically secure (they CAN be forged)
 *
 *  In Shaarli, they are used as a tinyurl-like link to individual entries,
 *  built once with the combination of the date and item ID.
 *  e.g. smallHash('20111006_131924' . 142) --> eaWxtQ
 *
 * @warning before v0.8.1, smallhashes were built only with the date,
 *          and their value has been preserved.
 *
 * @param string $text Create a hash from this text.
 *
 * @return string generated small hash.
 */
function smallHash($text)
{
    $t = rtrim(base64_encode(hash('crc32', $text, true)), '=');
    return strtr($t, '+/', '-_');
}

/**
 * Tells if a string start with a substring
 *
 * @param string $haystack Given string.
 * @param string $needle   String to search at the beginning of $haystack.
 * @param bool   $case     Case sensitive.
 *
 * @return bool True if $haystack starts with $needle.
 */
function startsWith($haystack, $needle, $case = true)
{
    if ($case) {
        return (strcmp(substr($haystack, 0, strlen($needle)), $needle) === 0);
    }
    return (strcasecmp(substr($haystack, 0, strlen($needle)), $needle) === 0);
}

/**
 * Tells if a string ends with a substring
 *
 * @param string $haystack Given string.
 * @param string $needle   String to search at the end of $haystack.
 * @param bool   $case     Case sensitive.
 *
 * @return bool True if $haystack ends with $needle.
 */
function endsWith($haystack, $needle, $case = true)
{
    if ($case) {
        return (strcmp(substr($haystack, strlen($haystack) - strlen($needle)), $needle) === 0);
    }
    return (strcasecmp(substr($haystack, strlen($haystack) - strlen($needle)), $needle) === 0);
}

/**
 * Htmlspecialchars wrapper
 * Support multidimensional array of strings.
 *
 * @param mixed $input Data to escape: a single string or an array of strings.
 *
 * @return string escaped.
 */
function escape($input)
{
    if (is_array($input)) {
        $out = array();
        foreach($input as $key => $value) {
            $out[$key] = escape($value);
        }
        return $out;
    }
    return htmlspecialchars($input, ENT_COMPAT, 'UTF-8', false);
}

/**
 * Reverse the escape function.
 *
 * @param string $str the string to unescape.
 *
 * @return string unescaped string.
 */
function unescape($str)
{
    return htmlspecialchars_decode($str);
}

/**
 * Sanitize link before rendering.
 *
 * @param array $link Link to escape.
 */
function sanitizeLink(&$link)
{
    $link['url'] = escape($link['url']); // useful?
    $link['title'] = escape($link['title']);
    $link['description'] = escape($link['description']);
    $link['tags'] = escape($link['tags']);
}

/**
 * Checks if a string represents a valid date

 * @param string $format The expected DateTime format of the string
 * @param string $string A string-formatted date
 *
 * @return bool whether the string is a valid date
 *
 * @see http://php.net/manual/en/class.datetime.php
 * @see http://php.net/manual/en/datetime.createfromformat.php
 */
function checkDateFormat($format, $string)
{
    $date = DateTime::createFromFormat($format, $string);
    return $date && $date->format($string) == $string;
}

/**
 * Generate a header location from HTTP_REFERER.
 * Make sure the referer is Shaarli itself and prevent redirection loop.
 *
 * @param string $referer - HTTP_REFERER.
 * @param string $host - Server HOST.
 * @param array $loopTerms - Contains list of term to prevent redirection loop.
 *
 * @return string $referer - final referer.
 */
function generateLocation($referer, $host, $loopTerms = array())
{
    $finalReferer = '?';

    // No referer if it contains any value in $loopCriteria.
    foreach ($loopTerms as $value) {
        if (strpos($referer, $value) !== false) {
            return $finalReferer;
        }
    }

    // Remove port from HTTP_HOST
    if ($pos = strpos($host, ':')) {
        $host = substr($host, 0, $pos);
    }

    $refererHost = parse_url($referer, PHP_URL_HOST);
    if (!empty($referer) && (strpos($refererHost, $host) !== false || startsWith('?', $refererHost))) {
        $finalReferer = $referer;
    }

    return $finalReferer;
}

/**
 * Validate session ID to prevent Full Path Disclosure.
 *
 * See #298.
 * The session ID's format depends on the hash algorithm set in PHP settings
 *
 * @param string $sessionId Session ID
 *
 * @return true if valid, false otherwise.
 *
 * @see http://php.net/manual/en/function.hash-algos.php
 * @see http://php.net/manual/en/session.configuration.php
 */
function is_session_id_valid($sessionId)
{
    if (empty($sessionId)) {
        return false;
    }

    if (!$sessionId) {
        return false;
    }

    if (!preg_match('/^[a-zA-Z0-9,-]{2,128}$/', $sessionId)) {
        return false;
    }

    return true;
}

/**
 * Sniff browser language to set the locale automatically.
 * Note that is may not work on your server if the corresponding locale is not installed.
 *
 * @param string $headerLocale Locale send in HTTP headers (e.g. "fr,fr-fr;q=0.8,en;q=0.5,en-us;q=0.3").
 **/
function autoLocale($headerLocale)
{
    // Default if browser does not send HTTP_ACCEPT_LANGUAGE
    $attempts = array('en_US', 'en_US.utf8', 'en_US.UTF-8');
    if (isset($headerLocale)) {
        // (It's a bit crude, but it works very well. Preferred language is always presented first.)
        if (preg_match('/([a-z]{2,3})[-_]?([a-z]{2})?/i', $headerLocale, $matches)) {
            $first = [strtolower($matches[1]), strtoupper($matches[1])];
            $separators = ['_', '-'];
            $encodings = ['utf8', 'UTF-8'];
            if (!empty($matches[2])) {
                $second = [strtoupper($matches[2]), strtolower($matches[2])];
                $attempts = arrays_combination([$first, $separators, $second, ['.'], $encodings]);
            } else {
                $attempts = arrays_combination([$first, $separators, $first, ['.'], $encodings]);
            }
        }
    }
    setlocale(LC_ALL, $attempts);
}

/**
 * Combine multiple arrays of string to get all possible strings.
 * The order is important because this doesn't shuffle the entries.
 *
 * Example:
 *   [['a'], ['b', 'c']]
 * will generate:
 *   - ab
 *   - ac
 *
 * TODO PHP 5.6: use the `...` operator instead of an array of array.
 *
 * @param array $items array of array of string
 *
 * @return array Combined string from the input array.
 */
function arrays_combination($items)
{
    $out = [''];
    foreach ($items as $item) {
        $add = [];
        foreach ($item as $element) {
            foreach ($out as $key => $existingEntry) {
                $add[] = $existingEntry . $element;
            }
        }
        $out = $add;
    }
    return $out;
}

/**
 * Generates a default API secret.
 *
 * Note that the random-ish methods used in this function are predictable,
 * which makes them NOT suitable for crypto.
 * BUT the random string is salted with the salt and hashed with the username.
 * It makes the generated API secret secured enough for Shaarli.
 *
 * PHP 7 provides random_int(), designed for cryptography.
 * More info: http://stackoverflow.com/questions/4356289/php-random-string-generator

 * @param string $username Shaarli login username
 * @param string $salt     Shaarli password hash salt
 *
 * @return string|bool Generated API secret, 12 char length.
 *                     Or false if invalid parameters are provided (which will make the API unusable).
 */
function generate_api_secret($username, $salt)
{
    if (empty($username) || empty($salt)) {
        return false;
    }

    return str_shuffle(substr(hash_hmac('sha512', uniqid($salt), $username), 10, 12));
}

/**
 * Trim string, replace sequences of whitespaces by a single space.
 * PHP equivalent to `normalize-space` XSLT function.
 *
 * @param string $string Input string.
 *
 * @return mixed Normalized string.
 */
function normalize_spaces($string)
{
    return preg_replace('/\s{2,}/', ' ', trim($string));
}
