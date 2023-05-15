# [Shaarli](https://github.com/shaarli/Shaarli/) documentation

The personal, minimalist, super-fast, database free, bookmarking service.

Do you want to share the links you discover?
Shaarli is a minimalist link sharing service that you can install on your own server.
It is designed to be personal (single-user), fast and handy.

<!--- TODO screenshots --->

Here you can find some info on how to use, configure, tweak and solve problems with your Shaarli.
For general information, read the [README](https://github.com/shaarli/Shaarli/blob/master/README.md).

If you have any questions or ideas, please join the [chat](https://gitter.im/shaarli/Shaarli) (also reachable via [IRC](https://irc.gitter.im/)), post them in our [general discussion](https://github.com/shaarli/Shaarli/issues/308) or read the current [issues](https://github.com/shaarli/Shaarli/issues).

If you've found a bug, please create a [new issue](https://github.com/shaarli/Shaarli/issues/new).

If you would like a feature added to Shaarli, check the issues labeled [`feature`](https://github.com/shaarli/Shaarli/labels/feature), [`enhancement`](https://github.com/shaarli/Shaarli/labels/enhancement), and [`plugin`](https://github.com/shaarli/Shaarli/labels/plugin).

* [GitHub project page](https://github.com/shaarli/Shaarli)
* [Online documentation](https://shaarli.readthedocs.io/) (this page)
* [Latest Shaarli releases](https://github.com/shaarli/Shaarli/releases)
* [Changelog](https://github.com/shaarli/Shaarli/blob/master/CHANGELOG.md)


### Demo

You can use this [public demo instance of Shaarli](https://demo.shaarli.org).
It runs the latest development version of Shaarli and is updated/reset daily.

Login: `demo`; Password: `demo`

<!---- TODO review everything below this point --->


## Features

Shaarli can be used:

- to share, comment and save interesting links and news.
- to bookmark useful/frequent personal links (as private links) and share them between computers.
- as a minimal blog/microblog/writing platform (no character limit).
- as a read-it-later list (for example items tagged `readlater`).
- to draft and save articles/posts/ideas.
- to keep code snippets.
- to keep notes and documentation.
- as a shared clipboard/notepad/pastebin between machines.
- as a todo list.
- to store playlists (e.g. with the `music` or `video` tags).
- to keep extracts/comments from webpages that may disappear.
- to keep track of ongoing discussions (for example items tagged `discussion`).
- [to feed RSS aggregators](http://shaarli.chassegnouf.net/?9Efeiw) (planets) with specific tags.
- to feed other social networks, blogs... using RSS feeds and external services (dlvr.it, ifttt.com ...).

### Interface

- minimalist design (simple is beautiful)
- FAST
- ATOM and RSS feeds
- views:
    - paginated link list (with image and video thumbnails)
    - tag cloud
    - picture wall: image and video thumbnails (with lazy loading)
    - daily: newspaper-like daily digest
    - daily RSS feed
- permalinks for easy reference
- links can be public or private
- thumbnail generation for images and video services
- URL cleanup: automatic removal of `?utm_source=...`, `fb=...`
- extensible through [plugins](https://shaarli.readthedocs.io/en/master/Plugins/#plugin-usage)

### Tag, view and search your links

- add a custom title and description to archived links
- add tags to classify and search links
  - features tag autocompletion, renaming, merging and deletion
- full-text and tag search

### Easy setup

- dead-simple installation: drop the files, open the page
- links are stored in a file
    - compact storage
    - no database required
    - easy backup: simply copy the datastore file
- import and export links as Netscape bookmarks

### Accessibility

- bookmarlet to share links in one click
- support for mobile browsers
- degrades gracefully with Javascript disabled
- easy page customization through HTML/CSS/RainTPL

### Security

- discreet pop-up notification when a new release is available
- bruteforce protection on the login form
- protected against [XSRF](http://en.wikipedia.org/wiki/Cross-site_request_forgery) and session cookie hijacking

<!---- TODO Limitations --->

### REST API

Easily extensible by any client using the REST API exposed by Shaarli.

See the [API documentation](http://shaarli.github.io/api-documentation/).

## About

### Shaarli community fork

This friendly fork is maintained by the Shaarli community at https://github.com/shaarli/Shaarli

This is a community fork of the original [Shaarli](https://github.com/sebsauvage/Shaarli/) project by [Sébastien Sauvage](http://sebsauvage.net/).

The original project is currently unmaintained, and the developer [has informed us](https://github.com/sebsauvage/Shaarli/issues/191) that he would have no time to work on Shaarli in the near future.

The Shaarli community has carried on the work to provide [many patches](https://github.com/shaarli/Shaarli/compare/sebsauvage:master...master) for [bug fixes and enhancements](https://github.com/shaarli/Shaarli/issues?q=is%3Aclosed+) in this repository, and will keep maintaining the project for the foreseeable future, while keeping Shaarli simple and efficient.


### Contributing

If you'd like to help, please:
- have a look at the open [issues](https://github.com/shaarli/Shaarli/issues)
and [pull requests](https://github.com/shaarli/Shaarli/pulls)
- feel free to report bugs (feedback is much appreciated)
- suggest new features and improvements to both code and [documentation](https://github.com/shaarli/Shaarli/tree/master/doc/md/)
- propose solutions to existing problems
- submit pull requests :-)


### License

Shaarli is [Free Software](http://en.wikipedia.org/wiki/Free_software). See [COPYING](https://github.com/shaarli/Shaarli/blob/master/COPYING) for a detail of the contributors and licenses for each individual component. A list of contributors is available [here](https://github.com/shaarli/Shaarli/blob/master/AUTHORS).

