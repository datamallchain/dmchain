<?php
namespace Shaarli\Plugin\Qrcode;

/**
 * PluginQrcodeTest.php
 */

use Shaarli\Plugin\PluginManager;
use Shaarli\Render\TemplatePage;

require_once 'plugins/qrcode/qrcode.php';

/**
 * Class PluginQrcodeTest
 * Unit test for the QR-Code plugin
 */
class PluginQrcodeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Reset plugin path
     */
    protected function setUp(): void
    {
        PluginManager::$PLUGINS_PATH = 'plugins';
    }

    /**
     * Test render_linklist hook.
     */
    public function testQrcodeLinklist()
    {
        $str = 'http://randomstr.com/test';
        $data = array(
            'title' => $str,
            'links' => array(
                array(
                    'url' => $str,
                )
            )
        );

        $data = hook_qrcode_render_linklist($data);
        $link = $data['links'][0];
        // data shouldn't be altered
        $this->assertEquals($str, $data['title']);
        $this->assertEquals($str, $link['url']);

        // plugin data
        $this->assertEquals(1, count($link['link_plugin']));
        $this->assertNotFalse(strpos($link['link_plugin'][0], $str));
    }

    /**
     * Test render_footer hook.
     */
    public function testQrcodeFooter()
    {
        $str = 'stuff';
        $data = array($str => $str);
        $data['_PAGE_'] = TemplatePage::LINKLIST;

        $data = hook_qrcode_render_footer($data);
        $this->assertEquals($str, $data[$str]);
        $this->assertEquals(1, count($data['js_files']));

        $data = array($str => $str);
        $data['_PAGE_'] = $str;
        $this->assertEquals($str, $data[$str]);
        $this->assertArrayNotHasKey('js_files', $data);
    }
}
