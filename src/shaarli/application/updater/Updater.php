<?php

namespace Shaarli\Updater;

use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\Updater\Exception\UpdaterException;

/**
 * Class Updater.
 * Used to update stuff when a new Shaarli's version is reached.
 * Update methods are ran only once, and the stored in a TXT file.
 */
class Updater
{
    /**
     * @var array Updates which are already done.
     */
    protected $doneUpdates;

    /**
     * @var BookmarkServiceInterface instance.
     */
    protected $bookmarkService;

    /**
     * @var ConfigManager $conf Configuration Manager instance.
     */
    protected $conf;

    /**
     * @var bool True if the user is logged in, false otherwise.
     */
    protected $isLoggedIn;

    /**
     * @var \ReflectionMethod[] List of current class methods.
     */
    protected $methods;

    /**
     * Object constructor.
     *
     * @param array                    $doneUpdates Updates which are already done.
     * @param BookmarkServiceInterface $linkDB      LinksService instance.
     * @param ConfigManager            $conf        Configuration Manager instance.
     * @param boolean                  $isLoggedIn  True if the user is logged in.
     */
    public function __construct($doneUpdates, $linkDB, $conf, $isLoggedIn)
    {
        $this->doneUpdates = $doneUpdates;
        $this->bookmarkService = $linkDB;
        $this->conf = $conf;
        $this->isLoggedIn = $isLoggedIn;

        // Retrieve all update methods.
        $class = new \ReflectionClass($this);
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
        $updatesRan = [];

        // If the user isn't logged in, exit without updating.
        if ($this->isLoggedIn !== true) {
            return $updatesRan;
        }

        if ($this->methods === null) {
            throw new UpdaterException('Couldn\'t retrieve LegacyUpdater class methods.');
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
            } catch (\Exception $e) {
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

    public function readUpdates(string $updatesFilepath): array
    {
        return UpdaterUtils::read_updates_file($updatesFilepath);
    }

    public function writeUpdates(string $updatesFilepath, array $updates): void
    {
        UpdaterUtils::write_updates_file($updatesFilepath, $updates);
    }

    /**
     * With the Slim routing system, default header link should be `./` instead of `?`.
     * Otherwise you can not go back to the home page. Example: `/picture-wall` -> `/picture-wall?` instead of `/`.
     */
    public function updateMethodRelativeHomeLink(): bool
    {
        $link = trim($this->conf->get('general.header_link'));
        if ($link[0] === '?') {
            $link = './'. ltrim($link, '?');

            $this->conf->set('general.header_link', $link, true, true);
        }

        return true;
    }

    /**
     * With the Slim routing system, note bookmarks URL formatted `?abcdef`
     * should be replaced with `/shaare/abcdef`
     */
    public function updateMethodMigrateExistingNotesUrl(): bool
    {
        $updated = false;

        foreach ($this->bookmarkService->search() as $bookmark) {
            if ($bookmark->isNote()
                && startsWith($bookmark->getUrl(), '?')
                && 1 === preg_match('/^\?([a-zA-Z0-9-_@]{6})($|&|#)/', $bookmark->getUrl(), $match)
            ) {
                $updated = true;
                $bookmark = $bookmark->setUrl('/shaare/' . $match[1]);

                $this->bookmarkService->set($bookmark, false);
            }
        }

        if ($updated) {
            $this->bookmarkService->save();
        }

        return true;
    }
}
