# Server configuration

## Requirements

### Operating system and web server

Shaarli can be hosted on dedicated/virtual servers, or shared hosting. The smallest DigitalOcean VPS (Droplet with 1 CPU, 1 GiB RAM and 25 GiB SSD) costs about $5/month and will run any Shaarli installation without problems.

You need write access to the Shaarli installation directory - you should have received instructions from your hosting provider on how to connect to the server using SSH (or FTP for shared hosts).

Examples in this documentation are given for [Debian](https://www.debian.org/), a GNU/Linux distribution widely used in server environments. Please adapt them to your specific Linux distribution.

### Network and domain name

Try to host the server in a region that is geographically close to your users.

A **domain name** ([DNS record](https://opensource.com/article/17/4/introduction-domain-name-system-dns)) pointing to the server's public IP address is required to obtain a SSL/TLS certificate and setup HTTPS to secure client traffic to your Shaarli instance.

You can obtain a domain name from a [registrar](https://en.wikipedia.org/wiki/Domain_name_registrar) ([1](https://www.ovh.co.uk/domains), [2](https://www.gandi.net/en/domain)), or from free subdomain providers ([1](https://freedns.afraid.org/)). If you don't have a domain name, please set up a private domain name ([FQDN](ttps://en.wikipedia.org/wiki/Fully_qualified_domain_name)) in your clients' [hosts files](https://en.wikipedia.org/wiki/Hosts_(file)) to access the server (direct access by IP address can result in unexpected behavior).

Setup a **firewall** (using `iptables`, [ufw](https://www.digitalocean.com/community/tutorials/how-to-set-up-a-firewall-with-ufw-on-debian-10), [fireHOL](https://firehol.org/) or any frontend of your choice) to deny all incoming traffic except `tcp/80` and `tcp/443`, which are needed to access the web server (and any other posrts you might need, like SSH). If the server is in a private network behind a NAT, ensure these **ports are forwarded** to the server.

Shaarli makes outbound HTTP/HTTPS connections to websites you bookmark to fetch page information (title, thumbnails), the server must then have access to the Internet as well, and a working DNS resolver.


### Screencast

Here is a screencast of the installation procedure

[![asciicast](https://asciinema.org/a/z3RXxcJIRgWk0jM2ws6EnUFgO.svg)](https://asciinema.org/a/z3RXxcJIRgWk0jM2ws6EnUFgO)

--------------------------------------------------------------------------------

### PHP

Supported PHP versions:

Version | Status | Shaarli compatibility
:---:|:---:|:---:
7.3 | Supported | Yes
7.2 | Supported | Yes
7.1 | Supported | Yes
7.0 | EOL: 2018-12-03 | Yes (up to Shaarli 0.10.x)
5.6 | EOL: 2018-12-31 | Yes (up to Shaarli 0.10.x)
5.5 | EOL: 2016-07-10 | Yes
5.4 | EOL: 2015-09-14 | Yes (up to Shaarli 0.8.x)
5.3 | EOL: 2014-08-14 | Yes (up to Shaarli 0.8.x)

Required PHP extensions:

Extension | Required? | Usage
---|:---:|---
[`openssl`](http://php.net/manual/en/book.openssl.php) | requires | OpenSSL, HTTPS
[`php-json`](http://php.net/manual/en/book.json.php) | required | configuration parsing
[`php-simplexml`](https://www.php.net/manual/en/book.simplexml.php) | required | REST API (Slim framework)
[`php-mbstring`](http://php.net/manual/en/book.mbstring.php) | CentOS, Fedora, RHEL, Windows, some hosting providers | multibyte (Unicode) string support
[`php-gd`](http://php.net/manual/en/book.image.php) | optional | required to use thumbnails
[`php-intl`](http://php.net/manual/en/book.intl.php) | optional | localized text sorting (e.g. `e->è->f`)
[`php-curl`](http://php.net/manual/en/book.curl.php) | optional | using cURL for fetching webpages and thumbnails in a more robust way
[`php-gettext`](http://php.net/manual/en/book.gettext.php) | optional | Use the translation system in gettext mode (faster)

Some [plugins](Plugins.md) may require additional configuration.


## SSL/TLS (HTTPS)

We recommend setting up [HTTPS](https://en.wikipedia.org/wiki/HTTPS) on your webserver for secure communication between clients and the server.

### Let's Encrypt

For public-facing web servers this can be done using free SSL/TLS certificates from [Let's Encrypt](https://en.wikipedia.org/wiki/Let's_Encrypt), a non-profit certificate authority provididing free certificates.

 - [How to secure Apache with Let's Encrypt](https://www.digitalocean.com/community/tutorials/how-to-secure-apache-with-let-s-encrypt-on-debian-10)
 - [How to secure Nginx with Let's Encrypt](https://www.digitalocean.com/community/tutorials/how-to-secure-nginx-with-let-s-encrypt-on-debian-10)
 - [How To Use Certbot Standalone Mode to Retrieve Let's Encrypt SSL Certificates](https://www.digitalocean.com/community/tutorials/how-to-use-certbot-standalone-mode-to-retrieve-let-s-encrypt-ssl-certificates-on-debian-10).

In short:

```bash
# install certbot
sudo apt install certbot

# stop your webserver if you already have one running
# certbot in standalone mode needs to bind to port 80 (only needed on initial generation)
sudo systemctl stop apache2
sudo systemctl stop nginx

# generate initial certificates
# Let's Encrypt ACME servers must be able to access your server! port forwarding and firewall must be properly configured
sudo certbot certonly --standalone --noninteractive --agree-tos --email "admin@shaarli.mydomain.org" -d shaarli.mydomain.org
# this will generate a private key and certificate at /etc/letsencrypt/live/shaarli.mydomain.org/{privkey,fullchain}.pem

# restart the web server
sudo systemctl start apache2
sudo systemctl start nginx
```

On apache `2.4.43+`, you can also delegate LE certificate management to [mod_md](https://httpd.apache.org/docs/2.4/mod/mod_md.html) [[1](https://www.cyberciti.biz/faq/how-to-secure-apache-with-mod_md-lets-encrypt-on-ubuntu-20-04-lts/)] in which case you don't need certbot and manual SSL configuration in virtualhosts.

### Self-signed

If you don't want to rely on a certificate authority, or the server can only be accessed from your own network, you can also generate self-signed certificates. Not that this will generate security warnings in web browsers/clients trying to access Shaarli:

- [How To Create a Self-Signed SSL Certificate for Apache](https://www.digitalocean.com/community/tutorials/how-to-create-a-self-signed-ssl-certificate-for-apache-on-debian-10)
- [How To Create a Self-Signed SSL Certificate for Nginx](https://www.digitalocean.com/community/tutorials/how-to-create-a-self-signed-ssl-certificate-for-nginx-on-debian-10)

--------------------------------------------------------------------------------

## Examples

The following examples assume a Debian-based operating system is installed. On other distributions you may have to adapt details such as package installation procedures, configuration file locations, and webserver username/group (`www-data` or `httpd` are common values). In these examples we assume the document root for your web server/virtualhost is at `/var/www/shaarli.mydomain.org/`:

```bash
# create the document root (replace with your own domain name)
sudo mkdir -p /var/www/shaarli.mydomain.org/
```

You can install Shaarli at the root of your virtualhost, or in a subdirectory as well. See [Directory structure](Directory-structure)


### Apache

```bash
# Install apache + mod_php and PHP modules
sudo apt update
sudo apt install apache2 libapache2-mod-php php-json php-mbstring php-gd php-intl php-curl php-gettext

# Edit the virtualhost configuration file with your favorite editor (replace the example domain name)
sudo nano /etc/apache2/sites-available/shaarli.mydomain.org.conf
```

```apache
<VirtualHost *:80>
    ServerName shaarli.mydomain.org
    DocumentRoot /var/www/shaarli.mydomain.org/

    # Redirect HTTP requests to HTTPS, except Let's Encrypt ACME challenge requests
    RewriteEngine on
    RewriteRule ^.well-known/acme-challenge/ - [L]
    RewriteCond %{HTTP_HOST} =shaarli.mydomain.org
    RewriteRule  ^ https://shaarli.mydomain.org%{REQUEST_URI} [END,NE,R=permanent]
    # If you are using mod_md, use this instead
    #MDCertificateAgreement accepted
    #MDContactEmail admin@shaarli.mydomain.org
    #MDPrivateKeys RSA 4096
</VirtualHost>

<VirtualHost *:443>
    ServerName   shaarli.mydomain.org
    DocumentRoot /var/www/shaarli.mydomain.org/

    # SSL/TLS configuration for Let's Encrypt certificates acquired with certbot standalone
    SSLEngine             on
    SSLCertificateFile    /etc/letsencrypt/live/shaarli.mydomain.org/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/shaarli.mydomain.org/privkey.pem
    # Let's Encrypt settings from https://github.com/certbot/certbot/blob/master/certbot-apache/certbot_apache/_internal/tls_configs/current-options-ssl-apache.conf
    SSLProtocol             all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite          ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384
    SSLHonorCipherOrder     off
    SSLSessionTickets       off
    SSLOptions +StrictRequire

    # SSL/TLS configuration for Let's Encrypt certificates acquired with mod_md
    #MDomain shaarli.mydomain.org

    # SSL/TLS configuration (for self-signed certificates)
    #SSLEngine             on
    #SSLCertificateFile    /etc/ssl/certs/ssl-cert-snakeoil.pem
    #SSLCertificateKeyFile /etc/ssl/private/ssl-cert-snakeoil.key

    # Optional, log PHP errors, useful for debugging
    #php_flag  log_errors on
    #php_flag  display_errors on
    #php_value error_reporting 2147483647
    #php_value error_log /var/log/apache2/shaarli-php-error.log

    <Directory /var/www/shaarli.mydomain.org/>
        # Required for .htaccess support
        AllowOverride All
        Require all granted
    </Directory>

    <LocationMatch "/\.">
        # Prevent accessing dotfiles
        RedirectMatch 404 ".*"
    </LocationMatch>

    <LocationMatch "\.(?:ico|css|js|gif|jpe?g|png)$">
        # allow client-side caching of static files
        Header set Cache-Control "max-age=2628000, public, must-revalidate, proxy-revalidate"
    </LocationMatch>

    # serve the Shaarli favicon from its custom location
    Alias favicon.ico /var/www/shaarli.mydomain.org/images/favicon.ico

</VirtualHost>
```

```bash
# Enable the virtualhost
sudo a2ensite shaarli.mydomain.org

# mod_ssl must be enabled to use TLS/SSL certificates
# https://httpd.apache.org/docs/current/mod/mod_ssl.html
sudo a2enmod ssl

# mod_rewrite must be enabled to use the REST API
# https://httpd.apache.org/docs/current/mod/mod_rewrite.html
sudo a2enmod rewrite

# mod_headers must be enabled to set custom headers from the server config
sudo a2enmod headers

# mod_version must only be enabled if you use Apache 2.2 or lower
# https://httpd.apache.org/docs/current/mod/mod_version.html
# sudo a2enmod version

# restart the apache service
sudo systemctl restart apache2
```

See [How to install the Apache web server](https://www.digitalocean.com/community/tutorials/how-to-install-the-apache-web-server-on-debian-10) for a complete guide.


### Nginx

This examples uses nginx and the [PHP-FPM](https://www.digitalocean.com/community/tutorials/how-to-install-linux-nginx-mariadb-php-lemp-stack-on-debian-10#step-3-%E2%80%94-installing-php-for-processing) PHP interpreter. Nginx and PHP-FPM must be running using the same user and group, here we assume the user/group to be `www-data:www-data`.


```bash
# install nginx and php-fpm
sudo apt update
sudo apt install nginx php-fpm

# Edit the virtualhost configuration file with your favorite editor
sudo nano /etc/nginx/sites-available/shaarli.mydomain.org
```

```nginx
server {
    listen       80;
    server_name  shaarli.mydomain.org;

    # redirect all plain HTTP requests to HTTPS
    return 301 https://shaarli.mydomain.org$request_uri;
}

server {
    listen       443 ssl;
    server_name  shaarli.mydomain.org;
    root         /var/www/shaarli.mydomain.org;

    # log file locations
    # combined log format prepends the virtualhost/domain name to log entries
    access_log  /var/log/nginx/access.log combined;
    error_log   /var/log/nginx/error.log;

    # paths to private key and certificates for SSL/TLS
    ssl_certificate      /etc/ssl/shaarli.mydomain.org.crt;
    ssl_certificate_key  /etc/ssl/private/shaarli.mydomain.org.key;

    # Let's Encrypt SSL settings from https://github.com/certbot/certbot/blob/master/certbot-nginx/certbot_nginx/_internal/tls_configs/options-ssl-nginx.conf
    ssl_session_cache shared:le_nginx_SSL:10m;
    ssl_session_timeout 1440m;
    ssl_session_tickets off;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;
    ssl_ciphers "ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384";

    # increase the maximum file upload size if needed: by default nginx limits file upload to 1MB (413 Entity Too Large error)
    client_max_body_size 100m;

    # relative path to shaarli from the root of the webserver
    location / {
        # default index file when no file URI is requested
        index index.php;
        try_files $uri /index.php$is_args$args;
    }

    location ~ (index)\.php$ {
        try_files $uri =404;
        # slim API - split URL path into (script_filename, path_info)
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        # pass PHP requests to PHP-FPM
        fastcgi_pass   unix:/var/run/php-fpm/php-fpm.sock;
        fastcgi_index  index.php;
        include        fastcgi.conf;
    }

    location ~ \.php$ {
        # deny access to all other PHP scripts
        # disable this if you host other PHP applications on the same virtualhost
        deny all;
    }

    location ~ /\. {
        # deny access to dotfiles
        deny all;
    }

    location ~ ~$ {
        # deny access to temp editor files, e.g. "script.php~"
        deny all;
    }

    location = /favicon.ico {
        # serve the Shaarli favicon from its custom location
        alias /var/www/shaarli/images/favicon.ico;
    }

    # allow client-side caching of static files
    location ~* \.(?:ico|css|js|gif|jpe?g|png)$ {
        expires    max;
        add_header Cache-Control "public, must-revalidate, proxy-revalidate";
        # HTTP 1.0 compatibility
        add_header Pragma public;
    }

}
```

```bash
# enable the configuration/virtualhost
sudo ln -s /etc/nginx/sites-available/shaarli.mydomain.org /etc/nginx/sites-enabled/shaarli.mydomain.org
# reload nginx configuration
sudo systemctl reload nginx
```

See [How to install the Nginx web server](https://www.digitalocean.com/community/tutorials/how-to-install-nginx-on-debian-10) for a complete guide.


## Reverse proxies

If Shaarli is hosted on a server behind a [reverse proxy](https://en.wikipedia.org/wiki/Reverse_proxy) (i.e. there is a proxy server between clients and the web server hosting Shaarli), configure it accordingly. See [Reverse proxy](Reverse-proxy.md) configuration.



## Allow import of large browser bookmarks export

Web browser bookmark exports can be large due to the presence of base64-encoded images and favicons/long subfolder names. Edit the PHP configuration file.

- Apache: `/etc/php/<PHP_VERSION>/apache2/php.ini`
- Nginx + PHP-FPM: `/etc/php/<PHP_VERSION>/fpm/php.ini` (in addition to `client_max_body_size` in the [Nginx configuration](#nginx))

```ini
[...]
# (optional) increase the maximum file upload size:
post_max_size = 100M
[...]
# (optional) increase the maximum file upload size:
upload_max_filesize = 100M
```

To verify PHP settings currently set on the server, create a `phpinfo.php` in your webserver's document root

```bash
# example
echo '<?php phpinfo(); ?>' | sudo tee /var/www/shaarli.mydomain.org/phpinfo.php
#give read-only access to this file to the webserver user
sudo chown www-data:root /var/www/shaarli.mydomain.org/phpinfo.php
sudo chmod 0400 /var/www/shaarli.mydomain.org/phpinfo.php
```

Access the file from a web browser (eg. <https://shaarli.mydomain.org/phpinfo.php> and look at the _Loaded Configuration File_ and _Scan this dir for additional .ini files_ entries

It is recommended to remove the `phpinfo.php` when no longer needed as it publicly discloses details about your webserver configuration.


## Robots and crawlers

To opt-out of indexing your Shaarli instance by search engines, create a `robots.txt` file at the root of your virtualhost:

```
User-agent: *
Disallow: /
```

By default Shaarli already disallows indexing of your local copy of the documentation by default, using `<meta name="robots">` HTML tags. Your Shaarli instance may still be indexed by various robots on the public Internet, that do not respect this header or the robots standard.

- [Robots exclusion standard](https://en.wikipedia.org/wiki/Robots_exclusion_standard)
- [Introduction to robots.txt](https://support.google.com/webmasters/answer/6062608?hl=en)
- [Robots meta tag, data-nosnippet, and X-Robots-Tag specifications](https://developers.google.com/search/reference/robots_meta_tag)
- [About robots.txt](http://www.robotstxt.org)
- [About the robots META tag](https://www.robotstxt.org/meta.html)


## Fail2ban

[fail2ban](http://www.fail2ban.org/wiki/index.php/Main_Page) is an intrusion prevention framework that reads server (Apache, SSH, etc.) and uses `iptables` profiles to block brute-force attempts. You need to create a filter to detect shaarli login failures in logs, and a jail configuation to configure the behavior when failed login attempts are detected:

```ini
# /etc/fail2ban/filter.d/shaarli-auth.conf
[INCLUDES]
before = common.conf
[Definition]
failregex = \s-\s<HOST>\s-\sLogin failed for user.*$
ignoreregex = 
```

```ini
# /etc/fail2ban/jail.local
[shaarli-auth]
enabled  = true
port     = https,http
filter   = shaarli-auth
logpath  = /var/www/shaarli.mydomain.org/data/log.txt
# allow 3 login attempts per IP address
# (over a period specified by findtime = in /etc/fail2ban/jail.conf)
maxretry = 3
# permanently ban the IP address after reaching the limit
bantime = -1
```

Then restart the service: `sudo systemctl restart fail2ban`

#### References

- [Apache/PHP - error log per VirtualHost - StackOverflow](http://stackoverflow.com/q/176)
- [Apache - PHP: php_value vs php_admin_value and the use of php_flag explained](https://ma.ttias.be/php-php_value-vs-php_admin_value-and-the-use-of-php_flag-explained/)
- [Server-side TLS (Apache) - Mozilla](https://wiki.mozilla.org/Security/Server_Side_TLS#Apache)
- [Nginx Beginner's guide](http://nginx.org/en/docs/beginners_guide.html)
- [Nginx ngx_http_fastcgi_module](http://nginx.org/en/docs/http/ngx_http_fastcgi_module.html)
- [Nginx Pitfalls](http://wiki.nginx.org/Pitfalls)
- [Nginx PHP configuration examples - Karl Blessing](http://kbeezie.com/nginx-configuration-examples/)
- [Apache 2.4 documentation](https://httpd.apache.org/docs/2.4/)
- [Apache mod_proxy](https://httpd.apache.org/docs/2.4/mod/mod_proxy.html)
- [Apache Reverse Proxy Request Headers](https://httpd.apache.org/docs/2.4/mod/mod_proxy.html#x-headers)
- [HAProxy documentation](https://cbonte.github.io/haproxy-dconv/)
- [Nginx documentation](https://nginx.org/en/docs/)
- [`X-Forwarded-Proto`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-Forwarded-Proto)
- [`X-Forwarded-Host`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-Forwarded-Host)
- [`X-Forwarded-For`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-Forwarded-For)
- [Server-side TLS (Nginx) - Mozilla](https://wiki.mozilla.org/Security/Server_Side_TLS#Nginx)
- [How to Create Self-Signed SSL Certificates with OpenSSL](http://www.xenocafe.com/tutorials/linux/centos/openssl/self_signed_certificates/index.php)
- [How do I create my own Certificate Authority?](https://workaround.org/certificate-authority)
- [Travis configuration](https://github.com/shaarli/Shaarli/blob/master/.travis.yml)
- [PHP: Supported versions](http://php.net/supported-versions.php)
- [PHP: Unsupported versions (EOL/End-of-life)](http://php.net/eol.php)
- [PHP 7 Changelog](http://php.net/ChangeLog-7.php)
- [PHP 5 Changelog](http://php.net/ChangeLog-5.php)
- [PHP: Bugs](https://bugs.php.net/)
- [Transport Layer Security](https://en.wikipedia.org/wiki/Transport_Layer_Security)
- Hosting providers: [DigitalOcean](https://www.digitalocean.com/) ([1](https://www.digitalocean.com/docs/droplets/overview/), [2](https://www.digitalocean.com/pricing/), [3](https://www.digitalocean.com/docs/droplets/how-to/create/), [How to Add SSH Keys to Droplets](https://www.digitalocean.com/docs/droplets/how-to/add-ssh-keys/), [4](https://www.digitalocean.com/community/tutorials/initial-server-setup-with-debian-8), [5](https://www.digitalocean.com/community/tutorials/an-introduction-to-securing-your-linux-vps)), [Gandi](https://www.gandi.net/en), [OVH](https://www.ovh.co.uk/), [RackSpace](https://www.rackspace.com/), etc.


