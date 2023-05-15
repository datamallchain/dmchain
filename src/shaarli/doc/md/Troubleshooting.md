# Troubleshooting

First of all, ensure that both the [web server](Server-configuration.md) and [Shaarli](Shaarli-configuration.md) are correctly configured.


## Login

### I forgot my password!

Delete the file `data/config.json.php` and display the page again. You will be asked for a new login/password.

### I'm locked out - Login bruteforce protection

Login form is protected against brute force attacks: 4 failed logins will ban the IP address from login for 30 minutes. Banned IPs can still browse Shaares.

- To remove the current IP bans, delete the file `data/ipbans.php`
- To list all login attempts, see `data/log.txt` (succesful/failed logins, bans/lifted bans)

--------------------------------------

## Browser issues

### Redirection issues (HTTP Referer)

Shaarli relies on `HTTP_REFERER` for some functions (like redirects and clicking on tags). If you have disabled or altered/spoofed [HTTP referers](https://en.wikipedia.org/wiki/HTTP_referer) in your browser, some features of Shaarli may not work as expected (depending on configuration and installed plugins), notably redirections between pages.

Firefox Referer settings are available by browsing `about:config` and are documented [here](https://wiki.mozilla.org/Security/Referrer). `network.http.referer.spoofSource = true` in particular is known to break some functionality in Shaarli.


### Firefox, localhost and redirections

`localhost` is not a proper Fully Qualified Domain Name (FQDN); if Firefox has been set up to spoof referers, or only accept requests from the same base domain/host,
Shaarli redirections will not work properly. To solve this, assign a local domain to your host, e.g. `localhost.lan` in your [hosts file](https://en.wikipedia.org/wiki/Hosts_(file)) and browse Shaarli at http://localhost.lan/.

-----------------------------------------

## Hosting problems

### Old PHP versions

- On hosts (such as **free.fr**) which only support PHP 5.6, Shaarli [v0.10.4](https://github.com/shaarli/Shaarli/releases/tag/v0.10.4) is the maximum supported version. At the root of your webspace create a `sessions` directory and a `.htaccess` file containing:

```xml
<IfDefine Free>
php56 1
</IfDefine>
<Files ".ht*">
Order allow,deny
Deny from all
Satisfy all
</Files>
Options -Indexes
```

- If you have an error such as: `Parse error: syntax error, unexpected '=', expecting '(' in /links/index.php on line xxx`, it means that your host is using PHP 4, not PHP 5. Shaarli requires PHP 5.1. Try changing the file extension to `.php5`
- On **1and1** : If you add the link from the page (and not from the bookmarklet), Shaarli will no be able to get the title of the page. You will have to enter it manually. (Because they have disabled the ability to download a file through HTTP).
- If you have the error `Warning: file_get_contents() [function.file-get-contents]: URL file-access is disabled in the server configuration in /…/index.php on line xxx`, it means that your host has disabled the ability to fetch a file by HTTP in the php config (Typically in 1and1 hosting). Bad host. Change host. Or comment the following lines:

```php
//list($status,$headers,$data) = getHTTP($url,4); // Short timeout to keep the application responsive.
// FIXME: Decode charset according to charset specified in either 1) HTTP response headers or 2) <head> in html
//if (strpos($status,'200 OK')) $title=html_extract_title($data);
```

- On hosts (such as **free.fr**) which forbid outgoing HTTP requests, some thumbnails will not work.
- On hosts (such as **free.fr**) which limit the number of FTP connections, setup your FTP client accordingly (else some files may be missing after upload).
- On **lost-oasis**, RSS doesn't work correctly, because of this message at the begining of the RSS/ATOM feed : `<? // tout ce qui est charge ici (generalement des includes et require) est charge en permanence. ?>`. To fix this, remove this message from `php-include/prepend.php`


### Dates are not properly formatted

Shaarli tries to sniff the language of the browser (using `HTTP_ACCEPT_LANGUAGE` headers)
and choose a date format accordingly. But Shaarli can only use the date formats
(and more generally speaking, the locales) provided by the webserver.
So even if you have a browser in French, you may end up with dates in US format
(it's the case on sebsauvage.net :-( )

### My session expires! I can't stay logged in

This can be caused by several things:

- Your php installation may not have a proper directory setup for session files. (eg. on Free.fr you need to create a  `session` directory on the root of your website.) You may need to create the session directory of set it up.
- Most hosts regularly clean the temporary and session directories. Your host may be cleaning those directories too aggressively (eg.OVH hosts), forcing an expire of the session. You may want to set the session directory in your web root. (eg. Create the `sessions` subdirectory and add `ini_set('session.save_path', $_SERVER['DOCUMENT_ROOT'].'/../sessions');`. Make sure this directory is not browsable !)
- If your IP address changes during surfing, Shaarli will force expire your session for security reasons (to prevent session cookie hijacking). This can happen when surfing from WiFi or 3G (you may have switched WiFi/3G access point), or in some corporate/university proxies which use load balancing (and may have proxies with several external IP addresses).
- Some browser addons may interfer with HTTP headers (ipfuck/ipflood/GreaseMonkey…). Try disabling those.
- You may be using OperaTurbo or OperaMini, which use their own proxies which may change from time to time.
- If you have another application on the same webserver where Shaarli is installed, these application may forcefully expire php sessions.


### Old apache versions, Internal Server Error

If you hosting provider only provides apache 2.2 and no support for `mod_version`, `.htaccess` files may cause 500 errors (Internal Server Error). See [this workaround](https://github.com/shaarli/Shaarli/issues/1196#issuecomment-412271085).


### Sessions do not seem to work correctly on your server

Follow the instructions in the error message. Make sure you are accessing shaarli via a direct IP address or a proper hostname. If you have **no dots** in the hostname (e.g. `localhost` or `http://my-webserver/shaarli/`), some browsers will not store cookies at all (this respects the [HTTP cookie specification](http://curl.haxx.se/rfc/cookie_spec.html)).

----------------------------------------------------------

## Upgrades

### You must specify an integer as a key

In `v0.8.1` we changed how Shaare keys are handled (from timestamps to incremental integers). Take a look at `data/updates.txt` content.


### `updates.txt` contains `updateMethodDatastoreIds`

Try to delete it and refresh your page while being logged in.

### `updates.txt` doesn't exist or doesn't contain `updateMethodDatastoreIds`

1. Create `data/updates.txt` if it doesn't exist
2. Paste this string in the update file `;updateMethodRenameDashTags;`
3. Login to Shaarli
4. Delete the update file
5. Refresh



--------------------------------------------------------

## Import/export

### Importing shaarli data to Firefox

- In Firefox, open the bookmark manager (`Bookmarks menu > Show all bookmarks` or `Ctrl+Shift+B`), select `Import and Backup > Import bookmarks in HTML format`
- Make sure the `Prepend note permalinks with this Shaarli instance's URL` box is checked when exporting, so that text-only/notes Shaares still point to the Shaarli instance you exported them from.
- Depending on the number of bookmarks, the import can take some time.

You may be interested in these Firefox addons to manage bookmarks imported from Shaarli

- [Bookmark Deduplicator](https://addons.mozilla.org/en-US/firefox/addon/bookmark-deduplicator/) - provides an easy way to deduplicate your bookmarks
- [TagSieve](https://addons.mozilla.org/en-US/firefox/addon/tagsieve/) - browse your bookmarks by their tags

### Diigo

If you export your bookmark from Diigo, make sure you use the Delicious export, not the Netscape export. (Their Netscape export is broken, and they don't seem to be interested in fixing it.)

### Mister Wong

See [this issue](https://github.com/sebsauvage/Shaarli/issues/146) for import tweaks.

### SemanticScuttle

To correctly import the tags from a [SemanticScuttle](http://semanticscuttle.sourceforge.net/) HTML export, edit the HTML file before importing and replace all occurences of `tags=` (lowercase) to `TAGS=` (uppercase).

### Scuttle

Shaarli cannot import data directly from [Scuttle](https://github.com/scronide/scuttle).

However, you can use the third-party [scuttle-to-shaarli](https://github.com/q2apro/scuttle-to-shaarli)
tool to export the Scuttle database to the Netscape HTML format compatible with the Shaarli importer.

### Refind.com

You can use the third-party tool [Derefind](https://github.com/ShawnPConroy/Derefind) to convert refind.com bookmark exports to a format that can be imported into Shaarli.


-------------------------------------------------------

## Other

### The bookmarklet doesn't work

Websites which enforce Content Security Policy (CSP), such as github.com, disallow usage of bookmarklets. Unfortunately, there is nothing Shaarli can do about it ([1](https://github.com/shaarli/Shaarli/issues/196), [2](https://bugzilla.mozilla.org/show_bug.cgi?id=866522), [3](https://code.google.com/p/chromium/issues/detail?id=233903).

Under Opera, you can't drag'n drop the button: You have to right-click on it and add a bookmark to your personal toolbar.


### Changing the timestamp for a shaare

- Look for `<input type="hidden" name="lf_linkdate" value="{$link.linkdate}">` in `tpl/editlink.tpl` (line 14)
- Replace `type="hidden"` with `type="text"` from this line
- A new date/time field becomes available in the edit/new Shaare dialog.
- You can set the timestamp manually by entering it in the format `YYYMMDD_HHMMS`.


-------------------------------------------------------

## Support

If the solutions above did not help, please:

- Come and ask question on the [Gitter chat](https://gitter.im/shaarli/Shaarli) (also reachable via [IRC](https://irc.gitter.im/))
- Search for [issues](https://github.com/shaarli/Shaarli/issues) and [Pull Requests](https://github.com/shaarli/Shaarli/pulls)
    - if you find one that is related to the issue, feel free to comment and provide additional details (host/Shaarli setup...)
    - check issues labeled [`feature`](https://github.com/shaarli/Shaarli/labels/feature), [`enhancement`](https://github.com/shaarli/Shaarli/labels/enhancement), and [`plugin`](https://github.com/shaarli/Shaarli/labels/plugin) if you would like a feature added to Shaarli.
    - else, [open a new issue](https://github.com/shaarli/Shaarli/issues/new), and provide information about the problem:
        - _what happens?_ - display glitches, invalid data, security flaws...
        - _what is your configuration?_  - OS, server version, activated extensions, web browser...
        - _is it reproducible?_