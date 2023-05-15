<?php
namespace Shaarli\Plugin\Isso;

use DateTime;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Config\ConfigManager;
use Shaarli\Plugin\PluginManager;

require_once 'plugins/isso/isso.php';

/**
 * Class PluginIssoTest
 *
 * Test the Isso plugin (comment system).
 */
class PluginIssoTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Reset plugin path
     */
    public function setUp()
    {
        PluginManager::$PLUGINS_PATH = 'plugins';
    }

    /**
     * Test Isso init without errors.
     */
    public function testIssoInitNoError()
    {
        $conf = new ConfigManager('');
        $conf->set('plugins.ISSO_SERVER', 'value');
        $errors = isso_init($conf);
        $this->assertEmpty($errors);
    }

    /**
     * Test Isso init with errors.
     */
    public function testIssoInitError()
    {
        $conf = new ConfigManager('');
        $errors = isso_init($conf);
        $this->assertNotEmpty($errors);
    }

    /**
     * Test render_linklist hook with valid settings to display the comment form.
     */
    public function testIssoDisplayed()
    {
        $conf = new ConfigManager('');
        $conf->set('plugins.ISSO_SERVER', 'value');

        $str = 'http://randomstr.com/test';
        $date = '20161118_100001';
        $data = array(
            'title' => $str,
            'links' => array(
                array(
                    'id' => 12,
                    'url' => $str,
                    'created' => DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, $date),
                )
            )
        );

        $data = hook_isso_render_linklist($data, $conf);

        // data shouldn't be altered
        $this->assertEquals($str, $data['title']);
        $this->assertEquals($str, $data['links'][0]['url']);

        // plugin data
        $this->assertEquals(1, count($data['plugin_end_zone']));
        $this->assertNotFalse(strpos(
            $data['plugin_end_zone'][0],
            'data-isso-id="'. $data['links'][0]['id'] .'"'
        ));
        $this->assertNotFalse(strpos(
            $data['plugin_end_zone'][0],
            'data-title="'. $data['links'][0]['id'] .'"'
        ));
        $this->assertNotFalse(strpos($data['plugin_end_zone'][0], 'embed.min.js'));
    }

    /**
     * Test isso plugin when multiple bookmarks are displayed (shouldn't be displayed).
     */
    public function testIssoMultipleLinks()
    {
        $conf = new ConfigManager('');
        $conf->set('plugins.ISSO_SERVER', 'value');

        $str = 'http://randomstr.com/test';
        $date1 = '20161118_100001';
        $date2 = '20161118_100002';
        $data = array(
            'title' => $str,
            'links' => array(
                array(
                    'id' => 12,
                    'url' => $str,
                    'shorturl' => $short1 = 'abcd',
                    'created' => DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, $date1),
                ),
                array(
                    'id' => 13,
                    'url' => $str . '2',
                    'shorturl' => $short2 = 'efgh',
                    'created' => DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, $date2),
                ),
            )
        );

        $processed = hook_isso_render_linklist($data, $conf);
        // link_plugin should be added for the icon
        $this->assertContains('<a href="?'. $short1 .'#isso-thread">', $processed['links'][0]['link_plugin'][0]);
        $this->assertContains('<a href="?'. $short2 .'#isso-thread">', $processed['links'][1]['link_plugin'][0]);
    }

    /**
     * Test isso plugin when using search (shouldn't be displayed).
     */
    public function testIssoNotDisplayedWhenSearch()
    {
        $conf = new ConfigManager('');
        $conf->set('plugins.ISSO_SERVER', 'value');

        $str = 'http://randomstr.com/test';
        $date = '20161118_100001';
        $data = array(
            'title' => $str,
            'links' => array(
                array(
                    'id' => 12,
                    'url' => $str,
                    'shorturl' => $short1 = 'abcd',
                    'created' => DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, $date),
                )
            ),
            'search_term' => $str
        );

        $processed = hook_isso_render_linklist($data, $conf);

        // link_plugin should be added for the icon
        $this->assertContains('<a href="?'. $short1 .'#isso-thread">', $processed['links'][0]['link_plugin'][0]);
    }

    /**
     * Test isso plugin without server configuration (shouldn't be displayed).
     */
    public function testIssoWithoutConf()
    {
        $data = 'abc';
        $conf = new ConfigManager('');
        $processed = hook_isso_render_linklist($data, $conf);
        $this->assertEquals($data, $processed);
    }
}
