<?php
/**
 * Utilities' tests
 */

require_once 'application/Utils.php';
require_once 'tests/utils/ReferenceSessionIdHashes.php';

// Initialize reference data before PHPUnit starts a session
ReferenceSessionIdHashes::genAllHashes();


/**
 * Unitary tests for Shaarli utilities
 */
class UtilsTest extends PHPUnit_Framework_TestCase
{
    // Session ID hashes
    protected static $sidHashes = null;

    /**
     * Assign reference data
     */
    public static function setUpBeforeClass()
    {
        self::$sidHashes = ReferenceSessionIdHashes::getHashes();
    }

    /**
     * Represent a link by its hash
     */
    public function testSmallHash()
    {
        $this->assertEquals('CyAAJw', smallHash('http://test.io'));
        $this->assertEquals(6, strlen(smallHash('https://github.com')));
    }

    /**
     * Look for a substring at the beginning of a string
     */
    public function testStartsWithCaseInsensitive()
    {
        $this->assertTrue(startsWith('Lorem ipsum', 'lorem', false));
        $this->assertTrue(startsWith('Lorem ipsum', 'LoReM i', false));
    }

    /**
     * Look for a substring at the beginning of a string (case-sensitive)
     */
    public function testStartsWithCaseSensitive()
    {
        $this->assertTrue(startsWith('Lorem ipsum', 'Lorem', true));
        $this->assertFalse(startsWith('Lorem ipsum', 'lorem', true));
        $this->assertFalse(startsWith('Lorem ipsum', 'LoReM i', true));
    }

    /**
     * Look for a substring at the beginning of a string (Unicode)
     */
    public function testStartsWithSpecialChars()
    {
        $this->assertTrue(startsWith('å!ùµ', 'å!', false));
        $this->assertTrue(startsWith('µ$åù', 'µ$', true));
    }

    /**
     * Look for a substring at the end of a string
     */
    public function testEndsWithCaseInsensitive()
    {
        $this->assertTrue(endsWith('Lorem ipsum', 'ipsum', false));
        $this->assertTrue(endsWith('Lorem ipsum', 'm IpsUM', false));
    }

    /**
     * Look for a substring at the end of a string (case-sensitive)
     */
    public function testEndsWithCaseSensitive()
    {
        $this->assertTrue(endsWith('lorem Ipsum', 'Ipsum', true));
        $this->assertFalse(endsWith('lorem Ipsum', 'ipsum', true));
        $this->assertFalse(endsWith('lorem Ipsum', 'M IPsuM', true));
    }

    /**
     * Look for a substring at the end of a string (Unicode)
     */
    public function testEndsWithSpecialChars()
    {
        $this->assertTrue(endsWith('å!ùµ', 'ùµ', false));
        $this->assertTrue(endsWith('µ$åù', 'åù', true));
    }

    /**
     * Check valid date strings, according to a DateTime format
     */
    public function testCheckValidDateFormat()
    {
        $this->assertTrue(checkDateFormat('Ymd', '20150627'));
        $this->assertTrue(checkDateFormat('Y-m-d', '2015-06-27'));
    }

    /**
     * Check erroneous date strings, according to a DateTime format
     */
    public function testCheckInvalidDateFormat()
    {
        $this->assertFalse(checkDateFormat('Ymd', '2015'));
        $this->assertFalse(checkDateFormat('Y-m-d', '2015-06'));
        $this->assertFalse(checkDateFormat('Ymd', 'DeLorean'));
    }

    /**
     * Test generate location with valid data.
     */
    public function testGenerateLocation() {
        $ref = 'http://localhost/?test';
        $this->assertEquals($ref, generateLocation($ref, 'localhost'));
        $ref = 'http://localhost:8080/?test';
        $this->assertEquals($ref, generateLocation($ref, 'localhost:8080'));
        $ref = '?localreferer#hash';
        $this->assertEquals($ref, generateLocation($ref, 'localhost:8080'));
    }

    /**
     * Test generate location - anti loop.
     */
    public function testGenerateLocationLoop() {
        $ref = 'http://localhost/?test';
        $this->assertEquals('?', generateLocation($ref, 'localhost', array('test')));
    }

    /**
     * Test generate location - from other domain.
     */
    public function testGenerateLocationOut() {
        $ref = 'http://somewebsite.com/?test';
        $this->assertEquals('?', generateLocation($ref, 'localhost'));
    }

    /**
     * Test is_session_id_valid with a valid ID - TEST ALL THE HASHES!
     *
     * This tests extensively covers all hash algorithms / bit representations
     */
    public function testIsAnyHashSessionIdValid()
    {
        foreach (self::$sidHashes as $algo => $bpcs) {
            foreach ($bpcs as $bpc => $hash) {
                $this->assertTrue(is_session_id_valid($hash));
            }
        }
    }

    /**
     * Test is_session_id_valid with a valid ID - SHA-1 hashes
     */
    public function testIsSha1SessionIdValid()
    {
        $this->assertTrue(is_session_id_valid(sha1('shaarli')));
    }

    /**
     * Test is_session_id_valid with a valid ID - SHA-256 hashes
     */
    public function testIsSha256SessionIdValid()
    {
        $this->assertTrue(is_session_id_valid(hash('sha256', 'shaarli')));
    }

    /**
     * Test is_session_id_valid with a valid ID - SHA-512 hashes
     */
    public function testIsSha512SessionIdValid()
    {
        $this->assertTrue(is_session_id_valid(hash('sha512', 'shaarli')));
    }

    /**
     * Test is_session_id_valid with invalid IDs.
     */
    public function testIsSessionIdInvalid()
    {
        $this->assertFalse(is_session_id_valid(''));
        $this->assertFalse(is_session_id_valid(array()));
        $this->assertFalse(
            is_session_id_valid('c0ZqcWF3VFE2NmJBdm1HMVQ0ZHJ3UmZPbTFsNGhkNHI=')
        );
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
     * Test testSpace2nbsp.
     */
    public function testSpace2nbsp()
    {
        $text = '  Are you   thrilled  by flags   ?'. PHP_EOL .' Really?';
        $expectedText = '&nbsp; Are you &nbsp; thrilled &nbsp;by flags &nbsp; ?'. PHP_EOL .'&nbsp;Really?';
        $processedText = space2nbsp($text);
        $this->assertEquals($expectedText, $processedText);
    }
}
