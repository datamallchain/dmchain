<?php

/**
 * PluginMarkdownTest.php
 */

require_once 'application/Utils.php';
require_once 'plugins/markdown/markdown.php';

/**
 * Class PlugQrcodeTest
 * Unit test for the QR-Code plugin
 */
class PluginMarkdownTest extends PHPUnit_Framework_TestCase
{
    /**
     * Reset plugin path
     */
    function setUp()
    {
        PluginManager::$PLUGINS_PATH = 'plugins';
    }

    /**
     * Test render_linklist hook.
     * Only check that there is basic markdown rendering.
     */
    function testMarkdownLinklist()
    {
        $markdown = '# My title' . PHP_EOL . 'Very interesting content.';
        $data = array(
            'links' => array(
                0 => array(
                    'description' => $markdown,
                ),
            ),
        );

        $data = hook_markdown_render_linklist($data);
        $this->assertNotFalse(strpos($data['links'][0]['description'], '<h1>'));
        $this->assertNotFalse(strpos($data['links'][0]['description'], '<p>'));
    }

    /**
     * Test render_daily hook.
     * Only check that there is basic markdown rendering.
     */
    function testMarkdownDaily()
    {
        $markdown = '# My title' . PHP_EOL . 'Very interesting content.';
        $data = array(
            // Columns data
            'cols' => array(
                // First, second, third.
                0 => array(
                    // nth link
                    0 => array(
                        'formatedDescription' => $markdown,
                    ),
                ),
            ),
        );

        $data = hook_markdown_render_daily($data);
        $this->assertNotFalse(strpos($data['cols'][0][0]['formatedDescription'], '<h1>'));
        $this->assertNotFalse(strpos($data['cols'][0][0]['formatedDescription'], '<p>'));
    }

    /**
     * Test reverse_text2clickable().
     */
    function testReverseText2clickable()
    {
        $text = 'stuff http://hello.there/is=someone#here otherstuff';
        $clickableText = text2clickable($text, '');
        $reversedText = reverse_text2clickable($clickableText);
        $this->assertEquals($text, $reversedText);
    }

    /**
     * Test reverse_nl2br().
     */
    function testReverseNl2br()
    {
        $text = 'stuff' . PHP_EOL . 'otherstuff';
        $processedText = nl2br($text);
        $reversedText = reverse_nl2br($processedText);
        $this->assertEquals($text, $reversedText);
    }

    /**
     * Test reverse_space2nbsp().
     */
    function testReverseSpace2nbsp()
    {
        $text = ' stuff' . PHP_EOL . '  otherstuff  and another';
        $processedText = space2nbsp($text);
        $reversedText = reverse_space2nbsp($processedText);
        $this->assertEquals($text, $reversedText);
    }

    /**
     * Test sanitize_html().
     */
    function testSanitizeHtml()
    {
        $input = '< script src="js.js"/>';
        $input .= '< script attr>alert(\'xss\');</script>';
        $input .= '<style> * { display: none }</style>';
        $output = escape($input);
        $input .= '<a href="#" onmouseHover="alert(\'xss\');" attr="tt">link</a>';
        $output .= '<a href="#"  attr="tt">link</a>';
        $this->assertEquals($output, sanitize_html($input));
        // Do not touch escaped HTML.
        $input = escape($input);
        $this->assertEquals($input, sanitize_html($input));
    }

    /**
     * Test the no markdown tag.
     */
    function testNoMarkdownTag()
    {
        $str = 'All _work_ and `no play` makes Jack a *dull* boy.';
        $data = array(
            'links' => array(array(
                'description' => $str,
                'tags' => NO_MD_TAG
            ))
        );

        $data = hook_markdown_render_linklist($data);
        $this->assertEquals($str, $data['links'][0]['description']);

        $data = array(
            // Columns data
            'cols' => array(
                // First, second, third.
                0 => array(
                    // nth link
                    0 => array(
                        'formatedDescription' => $str,
                        'tags' => NO_MD_TAG
                    ),
                ),
            ),
        );

        $data = hook_markdown_render_daily($data);
        $this->assertEquals($str, $data['cols'][0][0]['formatedDescription']);
    }
}
