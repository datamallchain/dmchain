<?php

namespace Shaarli\Netscape;

use DateTime;
use DateTimeZone;
use Exception;
use Katzgrau\KLogger\Logger;
use Psr\Log\LogLevel;
use Shaarli\Bookmark\LinkDB;
use Shaarli\Config\ConfigManager;
use Shaarli\History;
use Shaarli\NetscapeBookmarkParser\NetscapeBookmarkParser;

/**
 * Utilities to import and export bookmarks using the Netscape format
 * TODO: Not static, use a container.
 */
class NetscapeBookmarkUtils
{

    /**
     * Filters links and adds Netscape-formatted fields
     *
     * Added fields:
     * - timestamp  link addition date, using the Unix epoch format
     * - taglist    comma-separated tag list
     *
     * @param LinkDB $linkDb         Link datastore
     * @param string $selection      Which links to export: (all|private|public)
     * @param bool   $prependNoteUrl Prepend note permalinks with the server's URL
     * @param string $indexUrl       Absolute URL of the Shaarli index page
     *
     * @throws Exception Invalid export selection
     *
     * @return array The links to be exported, with additional fields
     */
    public static function filterAndFormat($linkDb, $selection, $prependNoteUrl, $indexUrl)
    {
        // see tpl/export.html for possible values
        if (!in_array($selection, array('all', 'public', 'private'))) {
            throw new Exception(t('Invalid export selection:') . ' "' . $selection . '"');
        }

        $bookmarkLinks = array();
        foreach ($linkDb as $link) {
            if ($link['private'] != 0 && $selection == 'public') {
                continue;
            }
            if ($link['private'] == 0 && $selection == 'private') {
                continue;
            }
            $date = $link['created'];
            $link['timestamp'] = $date->getTimestamp();
            $link['taglist'] = str_replace(' ', ',', $link['tags']);

            if (is_note($link['url']) && $prependNoteUrl) {
                $link['url'] = $indexUrl . $link['url'];
            }

            $bookmarkLinks[] = $link;
        }

        return $bookmarkLinks;
    }

    /**
     * Generates an import status summary
     *
     * @param string $filename       name of the file to import
     * @param int    $filesize       size of the file to import
     * @param int    $importCount    how many links were imported
     * @param int    $overwriteCount how many links were overwritten
     * @param int    $skipCount      how many links were skipped
     * @param int    $duration       how many seconds did the import take
     *
     * @return string Summary of the bookmark import status
     */
    private static function importStatus(
        $filename,
        $filesize,
        $importCount = 0,
        $overwriteCount = 0,
        $skipCount = 0,
        $duration = 0
    ) {
        $status = sprintf(t('File %s (%d bytes) '), $filename, $filesize);
        if ($importCount == 0 && $overwriteCount == 0 && $skipCount == 0) {
            $status .= t('has an unknown file format. Nothing was imported.');
        } else {
            $status .= vsprintf(
                t(
                    'was successfully processed in %d seconds: '
                    . '%d links imported, %d links overwritten, %d links skipped.'
                ),
                [$duration, $importCount, $overwriteCount, $skipCount]
            );
        }
        return $status;
    }

    /**
     * Imports Web bookmarks from an uploaded Netscape bookmark dump
     *
     * @param array         $post    Server $_POST parameters
     * @param array         $files   Server $_FILES parameters
     * @param LinkDB        $linkDb  Loaded LinkDB instance
     * @param ConfigManager $conf    instance
     * @param History       $history History instance
     *
     * @return string Summary of the bookmark import status
     */
    public static function import($post, $files, $linkDb, $conf, $history)
    {
        $start = time();
        $filename = $files['filetoupload']['name'];
        $filesize = $files['filetoupload']['size'];
        $data = file_get_contents($files['filetoupload']['tmp_name']);

        if (preg_match('/<!DOCTYPE NETSCAPE-Bookmark-file-1>/i', $data) === 0) {
            return self::importStatus($filename, $filesize);
        }

        // Overwrite existing links?
        $overwrite = !empty($post['overwrite']);

        // Add tags to all imported links?
        if (empty($post['default_tags'])) {
            $defaultTags = array();
        } else {
            $defaultTags = preg_split(
                '/[\s,]+/',
                escape($post['default_tags'])
            );
        }

        // links are imported as public by default
        $defaultPrivacy = 0;

        $parser = new NetscapeBookmarkParser(
            true,                           // nested tag support
            $defaultTags,                   // additional user-specified tags
            strval(1 - $defaultPrivacy),    // defaultPub = 1 - defaultPrivacy
            $conf->get('resource.data_dir') // log path, will be overridden
        );
        $logger = new Logger(
            $conf->get('resource.data_dir'),
            !$conf->get('dev.debug') ? LogLevel::INFO : LogLevel::DEBUG,
            [
                'prefix' => 'import.',
                'extension' => 'log',
            ]
        );
        $parser->setLogger($logger);
        $bookmarks = $parser->parseString($data);

        $importCount = 0;
        $overwriteCount = 0;
        $skipCount = 0;

        foreach ($bookmarks as $bkm) {
            $private = $defaultPrivacy;
            if (empty($post['privacy']) || $post['privacy'] == 'default') {
                // use value from the imported file
                $private = $bkm['pub'] == '1' ? 0 : 1;
            } elseif ($post['privacy'] == 'private') {
                // all imported links are private
                $private = 1;
            } elseif ($post['privacy'] == 'public') {
                // all imported links are public
                $private = 0;
            }

            $newLink = array(
                'title' => $bkm['title'],
                'url' => $bkm['uri'],
                'description' => $bkm['note'],
                'private' => $private,
                'tags' => $bkm['tags']
            );

            $existingLink = $linkDb->getLinkFromUrl($bkm['uri']);

            if ($existingLink !== false) {
                if ($overwrite === false) {
                    // Do not overwrite an existing link
                    $skipCount++;
                    continue;
                }

                // Overwrite an existing link, keep its date
                $newLink['id'] = $existingLink['id'];
                $newLink['created'] = $existingLink['created'];
                $newLink['updated'] = new DateTime();
                $newLink['shorturl'] = $existingLink['shorturl'];
                $linkDb[$existingLink['id']] = $newLink;
                $importCount++;
                $overwriteCount++;
                continue;
            }

            // Add a new link - @ used for UNIX timestamps
            $newLinkDate = new DateTime('@' . strval($bkm['time']));
            $newLinkDate->setTimezone(new DateTimeZone(date_default_timezone_get()));
            $newLink['created'] = $newLinkDate;
            $newLink['id'] = $linkDb->getNextId();
            $newLink['shorturl'] = link_small_hash($newLink['created'], $newLink['id']);
            $linkDb[$newLink['id']] = $newLink;
            $importCount++;
        }

        $linkDb->save($conf->get('resource.page_cache'));
        $history->importLinks();

        $duration = time() - $start;
        return self::importStatus(
            $filename,
            $filesize,
            $importCount,
            $overwriteCount,
            $skipCount,
            $duration
        );
    }
}
