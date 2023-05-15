#Release Shaarli
See  [Git - Maintaining a project - Tagging your [](.html)
releases](http://git-scm.com/book/en/v2/Distributed-Git-Maintaining-a-Project#Tagging-Your-Releases).

## Prerequisites
This guide assumes that you have:
- a GPG key matching your GitHub authentication credentials
    - i.e., the email address identified by the GPG key is the same as the one in your `~/.gitconfig` 
- a GitHub fork of Shaarli
- a local clone of your Shaarli fork, with the following remotes:
    - `origin` pointing to your GitHub fork
    - `upstream` pointing to the main Shaarli repository
- maintainer permissions on the main Shaarli repository (to push the signed tag)
- [Composer](https://getcomposer.org/) and [Pandoc](http://pandoc.org/) need to be installed[](.html)

## Increment the version code, create and push a signed tag
### Bump Shaarli's version
```bash
$ cd /path/to/shaarli

# create a new branch
$ git fetch upstream
$ git checkout upstream/master -b v0.5.0

# bump the version number
$ vim index.php shaarli_version.php

# rebuild the documentation from the wiki
$ make htmldoc

# commit the changes
$ git add index.php shaarli_version.php doc
$ git commit -s -m "Bump version to v0.5.0"

# push the commit on your GitHub fork
$ git push origin v0.5.0
```

### Create and merge a Pull Request
This one is pretty straightforward ;-)

### Create and push a signed tag
```bash
# update your local copy
$ git checkout master
$ git fetch upstream
$ git pull upstream master

# create a signed tag
$ git tag -s -m "Release v0.5.0" v0.5.0

# push it to "upstream"
$ git push --tags upstream
```

### Verify a signed tag
[`v0.5.0`](https://github.com/shaarli/Shaarli/releases/tag/v0.5.0) is the first GPG-signed tag pushed on the Community Shaarli.[](.html)

Let's have a look at its signature!

```bash
$ cd /path/to/shaarli
$ git fetch upstream

# get the SHA1 reference of the tag
$ git show-ref tags/v0.5.0
f7762cf803f03f5caf4b8078359a63783d0090c1 refs/tags/v0.5.0

# verify the tag signature information
$ git verify-tag f7762cf803f03f5caf4b8078359a63783d0090c1
gpg: Signature made Thu 30 Jul 2015 11:46:34 CEST using RSA key ID 4100DF6F
gpg: Good signature from "VirtualTam <virtualtam@flibidi.net>" [ultimate][](.html)
```

## Generate and upload all-in-one release archives
Users with a shared hosting may have:
- no SSH access
- no possibility to install PHP packages or server extensions
- no possibility to run scripts

To ease Shaarli installations, it is possible to generate and upload additional release archives,
that will contain Shaarli code plus all required third-party libraries:

```bash
$ make release_archive
```

This will create the following archives:
- `shaarli-vX.Y.Z-full.tar`
- `shaarli-vX.Y.Z-full.zip`

The archives need to be manually uploaded on the previously created GitHub release.
