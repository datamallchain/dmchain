<?php
/**
 * Url's tests
 */

require_once 'application/Url.php';

/**
 * Unitary tests for unparse_url()
 */
class UnparseUrlTest extends PHPUnit_Framework_TestCase
{
    /**
     * Thanks for building nothing
     */
    public function testUnparseEmptyArray()
    {
        $this->assertEquals('', unparse_url(array()));
    }

    /**
     * Rebuild a full-featured URL
     */
    public function testUnparseFull()
    {
        $ref = 'http://username:password@hostname:9090/path'
              .'?arg1=value1&arg2=value2#anchor';
        $this->assertEquals($ref, unparse_url(parse_url($ref)));
    }
}

/**
 * Unitary tests for URL utilities
 */
class UrlTest extends PHPUnit_Framework_TestCase
{
    // base URL for tests
    protected static $baseUrl = 'http://domain.tld:3000';

    /**
     * Helper method
     */
    private function assertUrlIsCleaned($query='', $fragment='')
    {
        $url = new Url(self::$baseUrl.$query.$fragment);
        $url->cleanup();
        $this->assertEquals(self::$baseUrl, $url->__toString());
    }

    /**
     * Instantiate an empty URL
     */
    public function testEmptyConstruct()
    {
        $this->assertEquals('', new Url(''));
    }

    /**
     * Instantiate a URL
     */
    public function testConstruct()
    {
        $ref = 'http://username:password@hostname:9090/path'
              .'?arg1=value1&arg2=value2#anchor';
        $this->assertEquals($ref, new Url($ref));
    }

    /**
     * URL cleanup - nothing to do
     */
    public function testNoCleanup()
    {
        // URL with no query nor fragment
        $this->assertUrlIsCleaned();

        // URL with no annoying elements
        $ref = self::$baseUrl.'?p1=val1&p2=1234#edit';
        $url = new Url($ref);
        $this->assertEquals($ref, $url->cleanup());
    }

    /**
     * URL cleanup - annoying fragment
     */
    public function testCleanupFragment()
    {
        $this->assertUrlIsCleaned('', '#tk.rss_all');
        $this->assertUrlIsCleaned('', '#xtor=RSS-');
        $this->assertUrlIsCleaned('', '#xtor=RSS-U3ht0tkc4b');
    }

    /**
     * URL cleanup - single annoying query parameter
     */
    public function testCleanupSingleQueryParam()
    {
        $this->assertUrlIsCleaned('?action_object_map=junk');
        $this->assertUrlIsCleaned('?action_ref_map=Cr4p!');
        $this->assertUrlIsCleaned('?action_type_map=g4R84g3');

        $this->assertUrlIsCleaned('?fb_stuff=v41u3');
        $this->assertUrlIsCleaned('?fb=71m3w4573');

        $this->assertUrlIsCleaned('?utm_campaign=zomg');
        $this->assertUrlIsCleaned('?utm_medium=numnum');
        $this->assertUrlIsCleaned('?utm_source=c0d3');
        $this->assertUrlIsCleaned('?utm_term=1n4l');

        $this->assertUrlIsCleaned('?xtor=some-url');
    }

    /**
     * URL cleanup - multiple annoying query parameters
     */
    public function testCleanupMultipleQueryParams()
    {
        $this->assertUrlIsCleaned('?xtor=some-url&fb=som3th1ng');
        $this->assertUrlIsCleaned(
            '?fb=stuff&utm_campaign=zomg&utm_medium=numnum&utm_source=c0d3'
        );
    }

    /**
     * URL cleanup - multiple annoying query parameters, annoying fragment
     */
    public function testCleanupMultipleQueryParamsAndFragment()
    {
        $this->assertUrlIsCleaned('?xtor=some-url&fb=som3th1ng', '#tk.rss_all');
    }

    /**
     * Nominal case - the URL contains both useful and annoying parameters
     */
    public function testCleanupMixedContent()
    {
        // ditch annoying query params and fragment, keep useful params
        $url = new Url(
            self::$baseUrl
            .'?fb=zomg&my=stuff&utm_medium=numnum&is=kept#tk.rss_all'
        );
        $this->assertEquals(self::$baseUrl.'?my=stuff&is=kept', $url->cleanup());


        // ditch annoying query params, keep useful params and fragment
        $url = new Url(
            self::$baseUrl
            .'?fb=zomg&my=stuff&utm_medium=numnum&is=kept#again'
        );
        $this->assertEquals(
            self::$baseUrl.'?my=stuff&is=kept#again',
            $url->cleanup()
        );
    }
}
