<?php

/**
 * Class Updater.
 * Used to update stuff when a new Shaarli's version is reached.
 * Update methods are ran only once, and the stored in a JSON file.
 */
class Updater
{
    /**
     * @var array Updates which are already done.
     */
    protected $doneUpdates;

    /**
     * @var LinkDB instance.
     */
    protected $linkDB;

    /**
     * @var bool True if the user is logged in, false otherwise.
     */
    protected $isLoggedIn;

    /**
     * @var ReflectionMethod[] List of current class methods.
     */
    protected $methods;

    /**
     * Object constructor.
     *
     * @param array   $doneUpdates Updates which are already done.
     * @param LinkDB  $linkDB      LinkDB instance.
     * @param boolean $isLoggedIn  True if the user is logged in.
     */
    public function __construct($doneUpdates, $linkDB, $isLoggedIn)
    {
        $this->doneUpdates = $doneUpdates;
        $this->linkDB = $linkDB;
        $this->isLoggedIn = $isLoggedIn;

        // Retrieve all update methods.
        $class = new ReflectionClass($this);
        $this->methods = $class->getMethods();
    }

    /**
     * Run all new updates.
     * Update methods have to start with 'updateMethod' and return true (on success).
     *
     * @return array An array containing ran updates.
     *
     * @throws UpdaterException If something went wrong.
     */
    public function update()
    {
        $updatesRan = array();

        // If the user isn't logged in, exit without updating.
        if ($this->isLoggedIn !== true) {
            return $updatesRan;
        }

        if ($this->methods == null) {
            throw new UpdaterException('Couldn\'t retrieve Updater class methods.');
        }

        foreach ($this->methods as $method) {
            // Not an update method or already done, pass.
            if (! startsWith($method->getName(), 'updateMethod')
                || in_array($method->getName(), $this->doneUpdates)
            ) {
                continue;
            }

            try {
                $method->setAccessible(true);
                $res = $method->invoke($this);
                // Update method must return true to be considered processed.
                if ($res === true) {
                    $updatesRan[] = $method->getName();
                }
            } catch (Exception $e) {
                throw new UpdaterException($method, $e);
            }
        }

        $this->doneUpdates = array_merge($this->doneUpdates, $updatesRan);

        return $updatesRan;
    }

    /**
     * @return array Updates methods already processed.
     */
    public function getDoneUpdates()
    {
        return $this->doneUpdates;
    }

    /**
     * Move deprecated options.php to config.php.
     *
     * Milestone 0.9 (old versioning) - shaarli/Shaarli#41:
     *    options.php is not supported anymore.
     */
    public function updateMethodMergeDeprecatedConfigFile()
    {
        $conf = ConfigManager::getInstance();

        if (is_file($conf->get('path.data_dir') . '/options.php')) {
            include $conf->get('path.data_dir') . '/options.php';

            // Load GLOBALS into config
            $allowedKeys = array_merge(ConfigPhp::$ROOT_KEYS);
            $allowedKeys[] = 'config';
            foreach ($GLOBALS as $key => $value) {
                if (in_array($key, $allowedKeys)) {
                    $conf->set($key, $value);
                }
            }
            $conf->write($this->isLoggedIn);
            unlink($conf->get('path.data_dir').'/options.php');
        }

        return true;
    }

    /**
     * Rename tags starting with a '-' to work with tag exclusion search.
     */
    public function updateMethodRenameDashTags()
    {
        $conf = ConfigManager::getInstance();
        $linklist = $this->linkDB->filterSearch();
        foreach ($linklist as $link) {
            $link['tags'] = preg_replace('/(^| )\-/', '$1', $link['tags']);
            $link['tags'] = implode(' ', array_unique(LinkFilter::tagsStrToArray($link['tags'], true)));
            $this->linkDB[$link['linkdate']] = $link;
        }
        $this->linkDB->savedb($conf->get('path.page_cache'));
        return true;
    }

    /**
     * Move old configuration in PHP to the new config system in JSON format.
     *
     * Will rename 'config.php' into 'config.save.php' and create 'config.json.php'.
     * It will also convert legacy setting keys to the new ones.
     */
    public function updateMethodConfigToJson()
    {
        $conf = ConfigManager::getInstance();

        // JSON config already exists, nothing to do.
        if ($conf->getConfigIO() instanceof ConfigJson) {
            return true;
        }

        $configPhp = new ConfigPhp();
        $configJson = new ConfigJson();
        $oldConfig = $configPhp->read($conf::$CONFIG_FILE . '.php');
        rename($conf->getConfigFile(), $conf::$CONFIG_FILE . '.save.php');
        $conf->setConfigIO($configJson);
        $conf->reload();

        $legacyMap = array_flip(ConfigPhp::$LEGACY_KEYS_MAPPING);
        foreach (ConfigPhp::$ROOT_KEYS as $key) {
            $conf->set($legacyMap[$key], $oldConfig[$key]);
        }

        // Set sub config keys (config and plugins)
        $subConfig = array('config', 'plugins');
        foreach ($subConfig as $sub) {
            foreach ($oldConfig[$sub] as $key => $value) {
                if (isset($legacyMap[$sub .'.'. $key])) {
                    $configKey = $legacyMap[$sub .'.'. $key];
                } else {
                    $configKey = $sub .'.'. $key;
                }
                $conf->set($configKey, $value);
            }
        }

        try{
            $conf->write($this->isLoggedIn);
            return true;
        } catch (IOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    /**
     * Escape settings which have been manually escaped in every request in previous versions:
     *   - general.title
     *   - general.header_link
     *   - extras.redirector
     *
     * @return bool true if the update is successful, false otherwise.
     */
    public function escapeUnescapedConfig()
    {
        $conf = ConfigManager::getInstance();
        try {
            $conf->set('general.title', escape($conf->get('general.title')));
            $conf->set('general.header_link', escape($conf->get('general.header_link')));
            $conf->set('extras.redirector', escape($conf->get('extras.redirector')));
            $conf->write($this->isLoggedIn);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
        return true;
    }
}

/**
 * Class UpdaterException.
 */
class UpdaterException extends Exception
{
    /**
     * @var string Method where the error occurred.
     */
    protected $method;

    /**
     * @var Exception The parent exception.
     */
    protected $previous;

    /**
     * Constructor.
     *
     * @param string         $message  Force the error message if set.
     * @param string         $method   Method where the error occurred.
     * @param Exception|bool $previous Parent exception.
     */
    public function __construct($message = '', $method = '', $previous = false)
    {
        $this->method = $method;
        $this->previous = $previous;
        $this->message = $this->buildMessage($message);
    }

    /**
     * Build the exception error message.
     *
     * @param string $message Optional given error message.
     *
     * @return string The built error message.
     */
    private function buildMessage($message)
    {
        $out = '';
        if (! empty($message)) {
            $out .= $message . PHP_EOL;
        }

        if (! empty($this->method)) {
            $out .= 'An error occurred while running the update '. $this->method . PHP_EOL;
        }

        if (! empty($this->previous)) {
            $out .= '  '. $this->previous->getMessage();
        }

        return $out;
    }
}

/**
 * Read the updates file, and return already done updates.
 *
 * @param string $updatesFilepath Updates file path.
 *
 * @return array Already done update methods.
 */
function read_updates_file($updatesFilepath)
{
    if (! empty($updatesFilepath) && is_file($updatesFilepath)) {
        $content = file_get_contents($updatesFilepath);
        if (! empty($content)) {
            return explode(';', $content);
        }
    }
    return array();
}

/**
 * Write updates file.
 *
 * @param string $updatesFilepath Updates file path.
 * @param array  $updates         Updates array to write.
 *
 * @throws Exception Couldn't write version number.
 */
function write_updates_file($updatesFilepath, $updates)
{
    if (empty($updatesFilepath)) {
        throw new Exception('Updates file path is not set, can\'t write updates.');
    }

    $res = file_put_contents($updatesFilepath, implode(';', $updates));
    if ($res === false) {
        throw new Exception('Unable to write updates in '. $updatesFilepath . '.');
    }
}
