#Upgrade and migration
## Preparation
### Backup your data

Shaarli stores all user data under the `data` directory:
- `data/config.php` - main configuration file
- `data/datastore.php` - bookmarked links
- `data/ipbans.php` - banned IP addresses

See [Shaarli configuration](Shaarli-configuration.html) for more information about Shaarli resources.

It is recommended to backup this repository _before_ starting updating/upgrading Shaarli:
- users with SSH access: copy or archive the directory to a temporary location
- users with FTP access: download a local copy of your Shaarli installation using your favourite client

### Migrating data from a previous installation
As all user data is kept under `data`, this is the only directory you need to worry about when migrating to a new installation, which corresponds to the following steps:

- backup the `data` directory
- install or update Shaarli:
    - fresh installation - see [Download and installation](Download-and-installation.html)
    - update - see the following sections
- check or restore the `data` directory

## Upgrading from release archives
All tagged revisions can be downloaded as tarballs or ZIP archives from the [releases](https://github.com/shaarli/Shaarli/releases) page.[](.html)

We _recommend_ using the releases from the `stable` branch, which are available as:
- gzipped tarball - https://github.com/shaarli/Shaarli/archive/stable.tar.gz
- ZIP archive - https://github.com/shaarli/Shaarli/archive/stable.zip

Once downloaded, extract the archive locally and update your remote installation (e.g. via FTP) -be sure you keep the contents of the `data` directory!

After upgrading, access your fresh Shaarli installation from a web browser; the configuration will then be automatically updated, and new settings added to `data/config.php` (see [Shaarli configuration](Shaarli-configuration.html) for more details).

## Upgrading with Git
### Updating a community Shaarli
If you have installed Shaarli from the [community Git repository](Download#clone-with-git-recommended), simply [pull new changes](https://www.git-scm.com/docs/git-pull) from your local clone:[](.html)

```bash
$ cd /path/to/shaarli
$ git pull

From github.com:shaarli/Shaarli
 * branch            master     -> FETCH_HEAD
Updating ebd67c6..521f0e6
Fast-forward
 application/Url.php   | 1 +
 shaarli_version.php   | 2 +-
 tests/Url/UrlTest.php | 1 +
 3 files changed, 3 insertions(+), 1 deletion(-)
```

Shaarli >= `v0.8.x`: install/update third-party PHP dependencies using [Composer](https://getcomposer.org/):[](.html)

```bash
$ composer update --no-dev

Loading composer repositories with package information
Updating dependencies
  - Installing shaarli/netscape-bookmark-parser (v1.0.1)
    Downloading: 100%
```

### Migrating and upgrading from Sebsauvage's repository
If you have installed Shaarli from [Sebsauvage's original Git repository](https://github.com/sebsauvage/Shaarli), you can use [Git remotes](https://git-scm.com/book/en/v2/Git-Basics-Working-with-Remotes) to update your working copy.[](.html)

The following guide assumes that:
- you have a basic knowledge of Git [branching](https://git-scm.com/book/en/v2/Git-Branching-Branches-in-a-Nutshell) and [remote repositories](https://git-scm.com/book/en/v2/Git-Basics-Working-with-Remotes)[](.html)
- the default remote is named `origin` and points to Sebsauvage's repository
- the current branch is `master`
    - if you have personal branches containing customizations, you will need to [rebase them](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) after the upgrade; beware though, a lot of changes have been made since the community fork has been created, so things are very likely to break![](.html)
- the working copy is clean:
    - no versioned file has been locally modified
    - no untracked files are present

#### Step 0: show repository information
```bash
$ cd /path/to/shaarli

$ git remote -v
origin	https://github.com/sebsauvage/Shaarli (fetch)
origin	https://github.com/sebsauvage/Shaarli (push)

$ git branch -vv
* master 029f75f [origin/master] Update README.md[](.html)

$ git status
On branch master
Your branch is up-to-date with 'origin/master'.
nothing to commit, working directory clean
```

#### Step 1: update Git remotes
```
$ git remote rename origin sebsauvage
$ git remote -v
sebsauvage	https://github.com/sebsauvage/Shaarli (fetch)
sebsauvage	https://github.com/sebsauvage/Shaarli (push)

$ git remote add origin https://github.com/shaarli/Shaarli
$ git fetch origin

remote: Counting objects: 3015, done.
remote: Compressing objects: 100% (19/19), done.
remote: Total 3015 (delta 446), reused 457 (delta 446), pack-reused 2550
Receiving objects: 100% (3015/3015), 2.59 MiB | 918.00 KiB/s, done.
Resolving deltas: 100% (1899/1899), completed with 48 local objects.
From https://github.com/shaarli/Shaarli
 * [new branch]      master     -> origin/master[](.html)
 * [new branch]      stable     -> origin/stable[](.html)
[...][](.html)
 * [new tag]         v0.6.4     -> v0.6.4[](.html)
 * [new tag]         v0.7.0     -> v0.7.0[](.html)
```

#### Step 2: use the stable community branch

```bash
$ git checkout origin/stable -b stable
Branch stable set up to track remote branch stable from origin.
Switched to a new branch 'stable'

$ git branch -vv
  master 029f75f [sebsauvage/master] Update README.md[](.html)
* stable 890afc3 [origin/stable] Merge pull request #509 from ArthurHoaro/v0.6.5[](.html)
```

Shaarli >= `v0.8.x`: install/update third-party PHP dependencies using [Composer](https://getcomposer.org/):[](.html)

```bash
$ composer update --no-dev

Loading composer repositories with package information
Updating dependencies
  - Installing shaarli/netscape-bookmark-parser (v1.0.1)
    Downloading: 100%
```

Optionally, you can delete information related to the legacy version:

```bash
$ git branch -D master
Deleted branch master (was 029f75f).

$ git remote remove sebsauvage

$ git remote -v
origin	https://github.com/shaarli/Shaarli (fetch)
origin	https://github.com/shaarli/Shaarli (push)

$ git gc
Counting objects: 3317, done.
Delta compression using up to 8 threads.
Compressing objects: 100% (1237/1237), done.
Writing objects: 100% (3317/3317), done.
Total 3317 (delta 2050), reused 3301 (delta 2034)to
```

#### Step 3: configuration
After migrating, access your fresh Shaarli installation from a web browser; the configuration will then be automatically updated, and new settings added to `data/config.php` (see [Shaarli configuration](Shaarli-configuration.html) for more details).
