#Backup, restore, import and export
 * [Backup and restore the datastore file](#backup-and-restore-the-datastore-file)[](.html)
 * [Export links as...](#export-links-as)[](.html)
 * [Import links from...](#import-links-from)[](.html)
 * [Import Shaarli links to Firefox](#import-shaarli-links-to-firefox)[](.html)


----------------------

## Backup and restore the datastore file

Backup the file `data/datastore.php` (by FTP or SSH). Restore by putting the file back in place.

Example command:
```bash
rsync -avzP my.server.com:/var/www/shaarli/data/datastore.php datastore-$(date +%Y-%m-%d_%H%M).php
```

## Export links as...

To export links as an HTML file, under _Tools > Export_, choose:
- _Export all_ to export both public and private links
- _Export public_ to export public links only
- _Export private_ to export private links only

Restore by using the `Import` feature.
* This can be done using the [shaarchiver](https://github.com/nodiscc/shaarchiver) tool.[](.html)

Example command: 
```bash
./export-bookmarks.py --url=https://my.server.com/shaarli --username=myusername --password=mysupersecretpassword --download-dir=./ --type=all
```

## Import links from...


### Diigo

If you export your bookmark from Diigo, make sure you use the Delicious export, not the Netscape export. (Their Netscape export is broken, and they don't seem to be interested in fixing it.)


### Mister Wong

See [this issue](https://github.com/sebsauvage/Shaarli/issues/146) for import tweaks.[](.html)

### SemanticScuttle

To correctly import the tags from a [SemanticScuttle](http://semanticscuttle.sourceforge.net/) HTML export, edit the HTML file before importing and replace all occurences of `tags=` (lowercase) to `TAGS=` (uppercase).[](.html)

### Scuttle

Shaarli cannot import data directly from [Scuttle](https://github.com/scronide/scuttle). However, you can use this third party tool: https://github.com/q2apro/scuttle-to-shaarli to export the Scuttle database to the Netscape HTML format compatible with the Shaarli importer.[](.html)

## Import Shaarli links to Firefox

 * Export your Shaarli links as described above.
   * For compatibility reasons, check `Prepend note permalinks with this Shaarli instance's URL (useful to import bookmarks in a web browser)`
 * In Firefox, open the bookmark manager (not the sidebar! `Bookmarks menu > Show all bookmarks` or `Ctrl+Shift+B`)
 * Select `Import and Backup > Import bookmarks in HTML format`

Your bookmarks will be imported in Firefox, ready to use, with tags and descriptions retained. "Self" (notes) shaares will still point to the Shaarli instance you exported them from, but the note text can be viewed directly in the bookmark properties inside your browser. Depending on the number of bookmarks, the import can take some time.

You may be interested in these Firefox addons to manage links imported from Shaarli

 * [Bookmark Deduplicator](https://addons.mozilla.org/en-US/firefox/addon/bookmark-deduplicator/) - provides an easy way to deduplicate your bookmarks[](.html)
 * [TagSieve](https://addons.mozilla.org/en-US/firefox/addon/tagsieve/) - browse your bookmarks by their tags[](.html)
