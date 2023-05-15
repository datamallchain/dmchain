<?php

// FIXME! Namespaces...
require_once 'ConfigIO.php';
require_once 'ConfigJson.php';
require_once 'ConfigPhp.php';

/**
 * Class ConfigManager
 *
 * Manages all Shaarli's settings.
 * See the documentation for more information on settings:
 *   - doc/Shaarli-configuration.html
 *   - https://github.com/shaarli/Shaarli/wiki/Shaarli-configuration
 */
class ConfigManager
{
    /**
     * @var string Flag telling a setting is not found.
     */
    protected static $NOT_FOUND = 'NOT_FOUND';

    /**
     * @var string Config folder.
     */
    protected $configFile;

    /**
     * @var array Loaded config array.
     */
    protected $loadedConfig;

    /**
     * @var ConfigIO implementation instance.
     */
    protected $configIO;

    /**
     * Constructor.
     *
     * @param string $configFile Configuration file path without extension.
     */
    public function __construct($configFile = 'data/config')
    {
        $this->configFile = $configFile;
        $this->initialize();
    }

    /**
     * Reset the ConfigManager instance.
     */
    public function reset()
    {
        $this->initialize();
    }

    /**
     * Rebuild the loaded config array from config files.
     */
    public function reload()
    {
        $this->load();
    }

    /**
     * Initialize the ConfigIO and loaded the conf.
     */
    protected function initialize()
    {
        if (file_exists($this->configFile . '.php')) {
            $this->configIO = new ConfigPhp();
        } else {
            $this->configIO = new ConfigJson();
        }
        $this->load();
    }

    /**
     * Load configuration in the ConfigurationManager.
     */
    protected function load()
    {
        $this->loadedConfig = $this->configIO->read($this->getConfigFileExt());
        $this->setDefaultValues();
    }

    /**
     * Get a setting.
     *
     * Supports nested settings with dot separated keys.
     * Eg. 'config.stuff.option' will find $conf[config][stuff][option],
     * or in JSON:
     *   { "config": { "stuff": {"option": "mysetting" } } } }
     *
     * @param string $setting Asked setting, keys separated with dots.
     * @param string $default Default value if not found.
     *
     * @return mixed Found setting, or the default value.
     */
    public function get($setting, $default = '')
    {
        // During the ConfigIO transition, map legacy settings to the new ones.
        if ($this->configIO instanceof ConfigPhp && isset(ConfigPhp::$LEGACY_KEYS_MAPPING[$setting])) {
            $setting = ConfigPhp::$LEGACY_KEYS_MAPPING[$setting];
        }

        $settings = explode('.', $setting);
        $value = self::getConfig($settings, $this->loadedConfig);
        if ($value === self::$NOT_FOUND) {
            return $default;
        }
        return $value;
    }

    /**
     * Set a setting, and eventually write it.
     *
     * Supports nested settings with dot separated keys.
     *
     * @param string $setting    Asked setting, keys separated with dots.
     * @param string $value      Value to set.
     * @param bool   $write      Write the new setting in the config file, default false.
     * @param bool   $isLoggedIn User login state, default false.
     *
     * @throws Exception Invalid
     */
    public function set($setting, $value, $write = false, $isLoggedIn = false)
    {
        if (empty($setting) || ! is_string($setting)) {
            throw new Exception('Invalid setting key parameter. String expected, got: '. gettype($setting));
        }

        // During the ConfigIO transition, map legacy settings to the new ones.
        if ($this->configIO instanceof ConfigPhp && isset(ConfigPhp::$LEGACY_KEYS_MAPPING[$setting])) {
            $setting = ConfigPhp::$LEGACY_KEYS_MAPPING[$setting];
        }

        $settings = explode('.', $setting);
        self::setConfig($settings, $value, $this->loadedConfig);
        if ($write) {
            $this->write($isLoggedIn);
        }
    }

    /**
     * Check if a settings exists.
     *
     * Supports nested settings with dot separated keys.
     *
     * @param string $setting    Asked setting, keys separated with dots.
     *
     * @return bool true if the setting exists, false otherwise.
     */
    public function exists($setting)
    {
        // During the ConfigIO transition, map legacy settings to the new ones.
        if ($this->configIO instanceof ConfigPhp && isset(ConfigPhp::$LEGACY_KEYS_MAPPING[$setting])) {
            $setting = ConfigPhp::$LEGACY_KEYS_MAPPING[$setting];
        }

        $settings = explode('.', $setting);
        $value = self::getConfig($settings, $this->loadedConfig);
        if ($value === self::$NOT_FOUND) {
            return false;
        }
        return true;
    }

    /**
     * Call the config writer.
     *
     * @param bool $isLoggedIn User login state.
     *
     * @return bool True if the configuration has been successfully written, false otherwise.
     *
     * @throws MissingFieldConfigException: a mandatory field has not been provided in $conf.
     * @throws UnauthorizedConfigException: user is not authorize to change configuration.
     * @throws IOException: an error occurred while writing the new config file.
     */
    public function write($isLoggedIn)
    {
        // These fields are required in configuration.
        $mandatoryFields = array(
            'credentials.login',
            'credentials.hash',
            'credentials.salt',
            'security.session_protection_disabled',
            'general.timezone',
            'general.title',
            'general.header_link',
            'privacy.default_private_links',
            'redirector.url',
        );

        // Only logged in user can alter config.
        if (is_file($this->getConfigFileExt()) && !$isLoggedIn) {
            throw new UnauthorizedConfigException();
        }

        // Check that all mandatory fields are provided in $conf.
        foreach ($mandatoryFields as $field) {
            if (! $this->exists($field)) {
                throw new MissingFieldConfigException($field);
            }
        }

        return $this->configIO->write($this->getConfigFileExt(), $this->loadedConfig);
    }

    /**
     * Set the config file path (without extension).
     *
     * @param string $configFile File path.
     */
    public function setConfigFile($configFile)
    {
        $this->configFile = $configFile;
    }

    /**
     * Return the configuration file path (without extension).
     *
     * @return string Config path.
     */
    public function getConfigFile()
    {
        return $this->configFile;
    }

    /**
     * Get the configuration file path with its extension.
     *
     * @return string Config file path.
     */
    public function getConfigFileExt()
    {
        return $this->configFile . $this->configIO->getExtension();
    }

    /**
     * Recursive function which find asked setting in the loaded config.
     *
     * @param array $settings Ordered array which contains keys to find.
     * @param array $conf   Loaded settings, then sub-array.
     *
     * @return mixed Found setting or NOT_FOUND flag.
     */
    protected static function getConfig($settings, $conf)
    {
        if (!is_array($settings) || count($settings) == 0) {
            return self::$NOT_FOUND;
        }

        $setting = array_shift($settings);
        if (!isset($conf[$setting])) {
            return self::$NOT_FOUND;
        }

        if (count($settings) > 0) {
            return self::getConfig($settings, $conf[$setting]);
        }
        return $conf[$setting];
    }

    /**
     * Recursive function which find asked setting in the loaded config.
     *
     * @param array $settings Ordered array which contains keys to find.
     * @param mixed $value
     * @param array $conf   Loaded settings, then sub-array.
     *
     * @return mixed Found setting or NOT_FOUND flag.
     */
    protected static function setConfig($settings, $value, &$conf)
    {
        if (!is_array($settings) || count($settings) == 0) {
            return self::$NOT_FOUND;
        }

        $setting = array_shift($settings);
        if (count($settings) > 0) {
            return self::setConfig($settings, $value, $conf[$setting]);
        }
        $conf[$setting] = $value;
    }

    /**
     * Set a bunch of default values allowing Shaarli to start without a config file.
     */
    protected function setDefaultValues()
    {
        $this->setEmpty('resource.data_dir', 'data');
        $this->setEmpty('resource.config', 'data/config.php');
        $this->setEmpty('resource.datastore', 'data/datastore.php');
        $this->setEmpty('resource.ban_file', 'data/ipbans.php');
        $this->setEmpty('resource.updates', 'data/updates.txt');
        $this->setEmpty('resource.log', 'data/log.txt');
        $this->setEmpty('resource.update_check', 'data/lastupdatecheck.txt');
        $this->setEmpty('resource.raintpl_tpl', 'tpl/');
        $this->setEmpty('resource.raintpl_tmp', 'tmp/');
        $this->setEmpty('resource.thumbnails_cache', 'cache');
        $this->setEmpty('resource.page_cache', 'pagecache');

        $this->setEmpty('security.ban_after', 4);
        $this->setEmpty('security.ban_duration', 1800);
        $this->setEmpty('security.session_protection_disabled', false);
        $this->setEmpty('security.open_shaarli', false);

        $this->setEmpty('general.header_link', '?');
        $this->setEmpty('general.links_per_page', 20);
        $this->setEmpty('general.enabled_plugins', array('qrcode'));

        $this->setEmpty('updates.check_updates', false);
        $this->setEmpty('updates.check_updates_branch', 'stable');
        $this->setEmpty('updates.check_updates_interval', 86400);

        $this->setEmpty('feed.rss_permalinks', true);
        $this->setEmpty('feed.show_atom', false);

        $this->setEmpty('privacy.default_private_links', false);
        $this->setEmpty('privacy.hide_public_links', false);
        $this->setEmpty('privacy.hide_timestamps', false);

        $this->setEmpty('thumbnail.enable_thumbnails', true);
        $this->setEmpty('thumbnail.enable_localcache', true);

        $this->setEmpty('redirector.url', '');
        $this->setEmpty('redirector.encode_url', true);

        $this->setEmpty('plugins', array());
    }

    /**
     * Set only if the setting does not exists.
     *
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
     */
    public function setEmpty($key, $value)
    {
        if (! $this->exists($key)) {
            $this->set($key, $value);
        }
    }

    /**
     * @return ConfigIO
     */
    public function getConfigIO()
    {
        return $this->configIO;
    }

    /**
     * @param ConfigIO $configIO
     */
    public function setConfigIO($configIO)
    {
        $this->configIO = $configIO;
    }
}

/**
 * Exception used if a mandatory field is missing in given configuration.
 */
class MissingFieldConfigException extends Exception
{
    public $field;

    /**
     * Construct exception.
     *
     * @param string $field field name missing.
     */
    public function __construct($field)
    {
        $this->field = $field;
        $this->message = 'Configuration value is required for '. $this->field;
    }
}

/**
 * Exception used if an unauthorized attempt to edit configuration has been made.
 */
class UnauthorizedConfigException extends Exception
{
    /**
     * Construct exception.
     */
    public function __construct()
    {
        $this->message = 'You are not authorized to alter config.';
    }
}
