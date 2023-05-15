<?php

namespace Shaarli;

use Shaarli\Config\ConfigManager;

/**
 * Class LanguagesTest.
 */
class LanguagesTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var string Config file path (without extension).
     */
    protected static $configFile = 'tests/utils/config/configJson';

    /**
     * @var ConfigManager
     */
    protected $conf;

    /**
     *
     */
    protected function setUp(): void
    {
        $this->conf = new ConfigManager(self::$configFile);
    }

    /**
     * Test t() with a simple non identified value.
     */
    public function testTranslateSingleNotIDGettext()
    {
        $this->conf->set('translation.mode', 'gettext');
        new Languages('en', $this->conf);
        $text = 'abcdé 564 fgK';
        $this->assertEquals($text, t($text));
    }

    /**
     * Test t() with a simple identified value in gettext mode.
     */
    public function testTranslateSingleIDGettext()
    {
        $this->conf->set('translation.mode', 'gettext');
        new Languages('en', $this->conf);
        $text = 'permalink';
        $this->assertEquals($text, t($text));
    }

    /**
     * Test t() with a non identified plural form in gettext mode.
     */
    public function testTranslatePluralNotIDGettext()
    {
        $this->conf->set('translation.mode', 'gettext');
        new Languages('en', $this->conf);
        $text = 'sandwich';
        $nText = 'sandwiches';
        $this->assertEquals('sandwiches', t($text, $nText, 0));
        $this->assertEquals('sandwich', t($text, $nText, 1));
        $this->assertEquals('sandwiches', t($text, $nText, 2));
    }

    /**
     * Test t() with an identified plural form in gettext mode.
     */
    public function testTranslatePluralIDGettext()
    {
        $this->conf->set('translation.mode', 'gettext');
        new Languages('en', $this->conf);
        $text = 'shaare';
        $nText = 'shaares';
        // In english, zero is followed by plural form
        $this->assertEquals('shaares', t($text, $nText, 0));
        $this->assertEquals('shaare', t($text, $nText, 1));
        $this->assertEquals('shaares', t($text, $nText, 2));
    }

    /**
     * Test t() with a simple non identified value.
     */
    public function testTranslateSingleNotIDPhp()
    {
        $this->conf->set('translation.mode', 'php');
        new Languages('en', $this->conf);
        $text = 'abcdé 564 fgK';
        $this->assertEquals($text, t($text));
    }

    /**
     * Test t() with a simple identified value in PHP mode.
     */
    public function testTranslateSingleIDPhp()
    {
        $this->conf->set('translation.mode', 'php');
        new Languages('en', $this->conf);
        $text = 'permalink';
        $this->assertEquals($text, t($text));
    }

    /**
     * Test t() with a non identified plural form in PHP mode.
     */
    public function testTranslatePluralNotIDPhp()
    {
        $this->conf->set('translation.mode', 'php');
        new Languages('en', $this->conf);
        $text = 'sandwich';
        $nText = 'sandwiches';
        $this->assertEquals('sandwiches', t($text, $nText, 0));
        $this->assertEquals('sandwich', t($text, $nText, 1));
        $this->assertEquals('sandwiches', t($text, $nText, 2));
    }

    /**
     * Test t() with an identified plural form in PHP mode.
     */
    public function testTranslatePluralIDPhp()
    {
        $this->conf->set('translation.mode', 'php');
        new Languages('en', $this->conf);
        $text = 'shaare';
        $nText = 'shaares';
        // In english, zero is followed by plural form
        $this->assertEquals('shaares', t($text, $nText, 0));
        $this->assertEquals('shaare', t($text, $nText, 1));
        $this->assertEquals('shaares', t($text, $nText, 2));
    }

    /**
     * Test t() with an invalid language set in the configuration in gettext mode.
     */
    public function testTranslateWithInvalidConfLanguageGettext()
    {
        $this->conf->set('translation.mode', 'gettext');
        $this->conf->set('translation.language', 'nope');
        new Languages('fr', $this->conf);
        $text = 'grumble';
        $this->assertEquals($text, t($text));
    }

    /**
     * Test t() with an invalid language set in the configuration in PHP mode.
     */
    public function testTranslateWithInvalidConfLanguagePhp()
    {
        $this->conf->set('translation.mode', 'php');
        $this->conf->set('translation.language', 'nope');
        new Languages('fr', $this->conf);
        $text = 'grumble';
        $this->assertEquals($text, t($text));
    }

    /**
     * Test t() with an invalid language set with auto language in gettext mode.
     */
    public function testTranslateWithInvalidAutoLanguageGettext()
    {
        $this->conf->set('translation.mode', 'gettext');
        new Languages('nope', $this->conf);
        $text = 'grumble';
        $this->assertEquals($text, t($text));
    }

    /**
     * Test t() with an invalid language set with auto language in PHP mode.
     */
    public function testTranslateWithInvalidAutoLanguagePhp()
    {
        $this->conf->set('translation.mode', 'php');
        new Languages('nope', $this->conf);
        $text = 'grumble';
        $this->assertEquals($text, t($text));
    }

    /**
     * Test t() with an extension language file coming from the theme in gettext mode
     */
    public function testTranslationThemeExtensionGettext()
    {
        $this->conf->set('translation.mode', 'gettext');
        $this->conf->set('raintpl_tpl', 'tests/utils/customtpl/');
        $this->conf->set('theme', 'dummy');
        new Languages('en', $this->conf);
        $txt = 'rooster'; // ignore me poedit
        $this->assertEquals('rooster', t($txt, $txt, 1, 'dummy'));
    }

    /**
     * Test t() with an extension language file coming from the theme in PHP mode
     */
    public function testTranslationThemeExtensionPhp()
    {
        $this->conf->set('translation.mode', 'php');
        $this->conf->set('raintpl_tpl', 'tests/utils/customtpl/');
        $this->conf->set('theme', 'dummy');
        new Languages('en', $this->conf);
        $txt = 'rooster'; // ignore me poedit
        $this->assertEquals('rooster', t($txt, $txt, 1, 'dummy'));
    }

    /**
     * Test t() with an extension language file in gettext mode
     */
    public function testTranslationExtensionGettext()
    {
        $this->conf->set('translation.mode', 'gettext');
        $this->conf->set('translation.extensions.test', 'tests/utils/languages/');
        new Languages('en', $this->conf);
        $txt = 'car'; // ignore me poedit
        $this->assertEquals('car', t($txt, $txt, 1, 'test'));
        $this->assertEquals('Search', t('Search', 'Search', 1, 'test'));
    }

    /**
     * Test t() with an extension language file in PHP mode
     */
    public function testTranslationExtensionPhp()
    {
        $this->conf->set('translation.mode', 'php');
        $this->conf->set('translation.extensions.test', 'tests/utils/languages/');
        new Languages('en', $this->conf);
        $txt = 'car'; // ignore me poedit
        $this->assertEquals('car', t($txt, $txt, 1, 'test'));
        $this->assertEquals('Search', t('Search', 'Search', 1, 'test'));
    }
}
