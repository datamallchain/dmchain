<?php

require_once 'application/LinkUtils.php';

/**
* Class LinkUtilsTest.
*/
class LinkUtilsTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test html_extract_title() when the title is found.
     */
    public function testHtmlExtractExistentTitle()
    {
        $title = 'Read me please.';
        $html = '<html><meta>stuff</meta><title>'. $title .'</title></html>';
        $this->assertEquals($title, html_extract_title($html));
        $html = '<html><title>'. $title .'</title>blabla<title>another</title></html>';
        $this->assertEquals($title, html_extract_title($html));
    }

    /**
     * Test html_extract_title() when the title is not found.
     */
    public function testHtmlExtractNonExistentTitle()
    {
        $html = '<html><meta>stuff</meta></html>';
        $this->assertFalse(html_extract_title($html));
    }

    /**
     * Test headers_extract_charset() when the charset is found.
     */
    public function testHeadersExtractExistentCharset()
    {
        $charset = 'x-MacCroatian';
        $headers = 'text/html; charset='. $charset;
        $this->assertEquals(strtolower($charset), header_extract_charset($headers));
    }

    /**
     * Test headers_extract_charset() when the charset is not found.
     */
    public function testHeadersExtractNonExistentCharset()
    {
        $headers = '';
        $this->assertFalse(header_extract_charset($headers));

        $headers = 'text/html';
        $this->assertFalse(header_extract_charset($headers));
    }

    /**
     * Test html_extract_charset() when the charset is found.
     */
    public function testHtmlExtractExistentCharset()
    {
        $charset = 'x-MacCroatian';
        $html = '<html><meta>stuff2</meta><meta charset="'. $charset .'"/></html>';
        $this->assertEquals(strtolower($charset), html_extract_charset($html));
    }

    /**
     * Test html_extract_charset() when the charset is not found.
     */
    public function testHtmlExtractNonExistentCharset()
    {
        $html = '<html><meta>stuff</meta></html>';
        $this->assertFalse(html_extract_charset($html));
        $html = '<html><meta>stuff</meta><meta charset=""/></html>';
        $this->assertFalse(html_extract_charset($html));
    }

    /**
     * Test the download callback with valid value
     */
    public function testCurlDownloadCallbackOk()
    {
        $callback = get_curl_download_callback($charset, $title, 'ut_curl_getinfo_ok');
        $data = [
            'HTTP/1.1 200 OK',
            'Server: GitHub.com',
            'Date: Sat, 28 Oct 2017 12:01:33 GMT',
            'Content-Type: text/html; charset=utf-8',
            'Status: 200 OK',
            'end' => 'th=device-width"><title>Refactoring · GitHub</title><link rel="search" type="application/opensea',
            '<title>ignored</title>',
        ];
        foreach ($data as $key => $line) {
            $ignore = null;
            $expected = $key !== 'end' ? strlen($line) : false;
            $this->assertEquals($expected, $callback($ignore, $line));
            if ($expected === false) {
                break;
            }
        }
        $this->assertEquals('utf-8', $charset);
        $this->assertEquals('Refactoring · GitHub', $title);
    }

    /**
     * Test the download callback with valid values and no charset
     */
    public function testCurlDownloadCallbackOkNoCharset()
    {
        $callback = get_curl_download_callback($charset, $title, 'ut_curl_getinfo_no_charset');
        $data = [
            'HTTP/1.1 200 OK',
            'end' => 'th=device-width"><title>Refactoring · GitHub</title><link rel="search" type="application/opensea',
            '<title>ignored</title>',
        ];
        foreach ($data as $key => $line) {
            $ignore = null;
            $this->assertEquals(strlen($line), $callback($ignore, $line));
        }
        $this->assertEmpty($charset);
        $this->assertEquals('Refactoring · GitHub', $title);
    }

    /**
     * Test the download callback with valid values and no charset
     */
    public function testCurlDownloadCallbackOkHtmlCharset()
    {
        $callback = get_curl_download_callback($charset, $title, 'ut_curl_getinfo_no_charset');
        $data = [
            'HTTP/1.1 200 OK',
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />',
            'end' => 'th=device-width"><title>Refactoring · GitHub</title><link rel="search" type="application/opensea',
            '<title>ignored</title>',
        ];
        foreach ($data as $key => $line) {
            $ignore = null;
            $expected = $key !== 'end' ? strlen($line) : false;
            $this->assertEquals($expected, $callback($ignore, $line));
            if ($expected === false) {
                break;
            }
        }
        $this->assertEquals('utf-8', $charset);
        $this->assertEquals('Refactoring · GitHub', $title);
    }

    /**
     * Test the download callback with valid values and no title
     */
    public function testCurlDownloadCallbackOkNoTitle()
    {
        $callback = get_curl_download_callback($charset, $title, 'ut_curl_getinfo_ok');
        $data = [
            'HTTP/1.1 200 OK',
            'end' => 'th=device-width">Refactoring · GitHub<link rel="search" type="application/opensea',
            'ignored',
        ];
        foreach ($data as $key => $line) {
            $ignore = null;
            $this->assertEquals(strlen($line), $callback($ignore, $line));
        }
        $this->assertEquals('utf-8', $charset);
        $this->assertEmpty($title);
    }

    /**
     * Test the download callback with an invalid content type.
     */
    public function testCurlDownloadCallbackInvalidContentType()
    {
        $callback = get_curl_download_callback($charset, $title, 'ut_curl_getinfo_ct_ko');
        $ignore = null;
        $this->assertFalse($callback($ignore, ''));
        $this->assertEmpty($charset);
        $this->assertEmpty($title);
    }

    /**
     * Test the download callback with an invalid response code.
     */
    public function testCurlDownloadCallbackInvalidResponseCode()
    {
        $callback = get_curl_download_callback($charset, $title, 'ut_curl_getinfo_rc_ko');
        $ignore = null;
        $this->assertFalse($callback($ignore, ''));
        $this->assertEmpty($charset);
        $this->assertEmpty($title);
    }

    /**
     * Test the download callback with an invalid content type and response code.
     */
    public function testCurlDownloadCallbackInvalidContentTypeAndResponseCode()
    {
        $callback = get_curl_download_callback($charset, $title, 'ut_curl_getinfo_rs_ct_ko');
        $ignore = null;
        $this->assertFalse($callback($ignore, ''));
        $this->assertEmpty($charset);
        $this->assertEmpty($title);
    }

    /**
     * Test count_private.
     */
    public function testCountPrivateLinks()
    {
        $refDB = new ReferenceLinkDB();
        $this->assertEquals($refDB->countPrivateLinks(), count_private($refDB->getLinks()));
    }

    /**
     * Test text2clickable without a redirector being set.
     */
    public function testText2clickableWithoutRedirector()
    {
        $text = 'stuff http://hello.there/is=someone#here otherstuff';
        $expectedText = 'stuff <a href="http://hello.there/is=someone#here">http://hello.there/is=someone#here</a> otherstuff';
        $processedText = text2clickable($text, '');
        $this->assertEquals($expectedText, $processedText);

        $text = 'stuff http://hello.there/is=someone#here(please) otherstuff';
        $expectedText = 'stuff <a href="http://hello.there/is=someone#here(please)">http://hello.there/is=someone#here(please)</a> otherstuff';
        $processedText = text2clickable($text, '');
        $this->assertEquals($expectedText, $processedText);

        $text = 'stuff http://hello.there/is=someone#here(please)&no otherstuff';
        $expectedText = 'stuff <a href="http://hello.there/is=someone#here(please)&no">http://hello.there/is=someone#here(please)&no</a> otherstuff';
        $processedText = text2clickable($text, '');
        $this->assertEquals($expectedText, $processedText);
    }

    /**
     * Test text2clickable a redirector set.
     */
    public function testText2clickableWithRedirector()
    {
        $text = 'stuff http://hello.there/is=someone#here otherstuff';
        $redirector = 'http://redirector.to';
        $expectedText = 'stuff <a href="'.
            $redirector .
            urlencode('http://hello.there/is=someone#here') .
            '">http://hello.there/is=someone#here</a> otherstuff';
        $processedText = text2clickable($text, $redirector);
        $this->assertEquals($expectedText, $processedText);
    }

    /**
     * Test text2clickable a redirector set and without URL encode.
     */
    public function testText2clickableWithRedirectorDontEncode()
    {
        $text = 'stuff http://hello.there/?is=someone&or=something#here otherstuff';
        $redirector = 'http://redirector.to';
        $expectedText = 'stuff <a href="'.
            $redirector .
            'http://hello.there/?is=someone&or=something#here' .
            '">http://hello.there/?is=someone&or=something#here</a> otherstuff';
        $processedText = text2clickable($text, $redirector, false);
        $this->assertEquals($expectedText, $processedText);
    }

    /**
     * Test testSpace2nbsp.
     */
    public function testSpace2nbsp()
    {
        $text = '  Are you   thrilled  by flags   ?'. PHP_EOL .' Really?';
        $expectedText = '&nbsp; Are you &nbsp; thrilled &nbsp;by flags &nbsp; ?'. PHP_EOL .'&nbsp;Really?';
        $processedText = space2nbsp($text);
        $this->assertEquals($expectedText, $processedText);
    }

    /**
     * Test hashtags auto-link.
     */
    public function testHashtagAutolink()
    {
        $index = 'http://domain.tld/';
        $rawDescription = '#hashtag\n
            # nothashtag\n
            test#nothashtag #hashtag \#nothashtag\n
            test #hashtag #hashtag test #hashtag.test\n
            #hashtag #hashtag-nothashtag #hashtag_hashtag\n
            What is #ашок anyway?\n
            カタカナ #カタカナ」カタカナ\n';
        $autolinkedDescription = hashtag_autolink($rawDescription, $index);

        $this->assertContains($this->getHashtagLink('hashtag', $index), $autolinkedDescription);
        $this->assertNotContains(' #hashtag', $autolinkedDescription);
        $this->assertNotContains('>#nothashtag', $autolinkedDescription);
        $this->assertContains($this->getHashtagLink('ашок', $index), $autolinkedDescription);
        $this->assertContains($this->getHashtagLink('カタカナ', $index), $autolinkedDescription);
        $this->assertContains($this->getHashtagLink('hashtag_hashtag', $index), $autolinkedDescription);
        $this->assertNotContains($this->getHashtagLink('hashtag-nothashtag', $index), $autolinkedDescription);
    }

    /**
     * Test hashtags auto-link without index URL.
     */
    public function testHashtagAutolinkNoIndex()
    {
        $rawDescription = 'blabla #hashtag x#nothashtag';
        $autolinkedDescription = hashtag_autolink($rawDescription);

        $this->assertContains($this->getHashtagLink('hashtag'), $autolinkedDescription);
        $this->assertNotContains(' #hashtag', $autolinkedDescription);
        $this->assertNotContains('>#nothashtag', $autolinkedDescription);
    }

    /**
     * Util function to build an hashtag link.
     *
     * @param string $hashtag Hashtag name.
     * @param string $index   Index URL.
     *
     * @return string HTML hashtag link.
     */
    private function getHashtagLink($hashtag, $index = '')
    {
        $hashtagLink = '<a href="'. $index .'?addtag=$1" title="Hashtag $1">#$1</a>';
        return str_replace('$1', $hashtag, $hashtagLink);
    }
}

// old style mock: PHPUnit doesn't allow function mock

/**
 * Returns code 200 or html content type.
 *
 * @param resource $ch   cURL resource
 * @param int      $type cURL info type
 *
 * @return int|string 200 or 'text/html'
 */
function ut_curl_getinfo_ok($ch, $type)
{
    switch ($type) {
        case CURLINFO_RESPONSE_CODE:
            return 200;
        case CURLINFO_CONTENT_TYPE:
            return 'text/html; charset=utf-8';
    }
}

/**
 * Returns code 200 or html content type without charset.
 *
 * @param resource $ch   cURL resource
 * @param int      $type cURL info type
 *
 * @return int|string 200 or 'text/html'
 */
function ut_curl_getinfo_no_charset($ch, $type)
{
    switch ($type) {
        case CURLINFO_RESPONSE_CODE:
            return 200;
        case CURLINFO_CONTENT_TYPE:
            return 'text/html';
    }
}

/**
 * Invalid response code.
 *
 * @param resource $ch   cURL resource
 * @param int      $type cURL info type
 *
 * @return int|string 404 or 'text/html'
 */
function ut_curl_getinfo_rc_ko($ch, $type)
{
    switch ($type) {
        case CURLINFO_RESPONSE_CODE:
            return 404;
        case CURLINFO_CONTENT_TYPE:
            return 'text/html; charset=utf-8';
    }
}

/**
 * Invalid content type.
 *
 * @param resource $ch   cURL resource
 * @param int      $type cURL info type
 *
 * @return int|string 200 or 'text/plain'
 */
function ut_curl_getinfo_ct_ko($ch, $type)
{
    switch ($type) {
        case CURLINFO_RESPONSE_CODE:
            return 200;
        case CURLINFO_CONTENT_TYPE:
            return 'text/plain';
    }
}

/**
 * Invalid response code and content type.
 *
 * @param resource $ch   cURL resource
 * @param int      $type cURL info type
 *
 * @return int|string 404 or 'text/plain'
 */
function ut_curl_getinfo_rs_ct_ko($ch, $type)
{
    switch ($type) {
        case CURLINFO_RESPONSE_CODE:
            return 404;
        case CURLINFO_CONTENT_TYPE:
            return 'text/plain';
    }
}
