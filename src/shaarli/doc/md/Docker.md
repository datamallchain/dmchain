# Docker

[Docker](https://docs.docker.com/get-started/overview/) is an open platform for developing, shipping, and running applications

## Install Docker

Install [Docker](https://docs.docker.com/engine/install/), by following the instructions relevant to your OS / distribution, and start the service. For example on [Debian](https://docs.docker.com/engine/install/debian/):

```bash
# update your package lists
sudo apt update
# remove old versions
sudo apt-get remove docker docker-engine docker.io containerd runc
# install requirements
sudo apt-get install apt-transport-https ca-certificates curl gnupg-agent software-properties-common
# add docker's GPG signing key
curl -fsSL https://download.docker.com/linux/debian/gpg | sudo apt-key add -
# add the repository
sudo add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/debian $(lsb_release -cs) stable"
# install docker engine
sudo apt-get update
sudo apt-get install docker-ce docker-ce-cli containerd.io
# Start and enable Docker service
sudo systemctl enable docker && sudo systemctl start docker
# verify that Docker is properly configured
sudo docker run hello-world
```

In order to run Docker commands as a non-root user, you must add the `docker` group to this user:

```bash
# Add docker group as secondary group
sudo usermod -aG docker your-user
# Reboot or logout
# Then verify that Docker is properly configured, as "your-user"
docker run hello-world
```

## Get and run a Shaarli image

Shaarli images are available on [DockerHub](https://hub.docker.com/r/shaarli/shaarli/) `shaarli/shaarli`:

- `latest`: latest branch (last release)
- `stable`: stable branch (last release in previous major version)
- `master`: master branch (development branch)

These images are built automatically on DockerHub and rely on:

- [Alpine Linux](https://www.alpinelinux.org/)
- [PHP7-FPM](http://php-fpm.org/)
- [Nginx](http://nginx.org/)

Additional Dockerfiles are provided for the `arm32v7` platform, relying on [Linuxserver.io Alpine armhf images](https://hub.docker.com/r/lsiobase/alpine.armhf/). These images must be built using [`docker build`](https://docs.docker.com/engine/reference/commandline/build/) on an `arm32v7` machine or using an emulator such as [qemu](https://resin.io/blog/building-arm-containers-on-any-x86-machine-even-dockerhub/).

Here is an example of how to run Shaarli latest image using Docker:

```bash
# download the 'latest' image from dockerhub
docker pull shaarli/shaarli

# create persistent data volumes/directories on the host
docker volume create shaarli-data
docker volume create shaarli-cache

# create a new container using the Shaarli image
# --detach: run the container in background
# --name: name of the created container/instance
# --publish: map the host's :8000 port to the container's :80 port
# --rm: automatically remove the container when it exits
# --volume: mount persistent volumes in the container ($volume_name:$volume_mountpoint)
docker run --detach \
           --name myshaarli \
           --publish 8000:80 \
           --rm \
           --volume shaarli-data:/var/www/shaarli/data \
           --volume shaarli-cache:/var/www/shaarli/cache \
           shaarli/shaarli:latest

# verify that the container is running
docker ps | grep myshaarli

# to completely remove the container
docker stop myshaarli # stop the running container
docker ps | grep myshaarli # verify the container is no longer running
docker ps -a | grep myshaarli # verify the container is stopped
docker rm myshaarli # destroy the container
docker ps -a | grep myshaarli # verify th container has been destroyed

```

After running `docker run` command, your Shaarli instance should be available on the host machine at [localhost:8000](http://localhost:8000). In order to access your instance through a reverse proxy, we recommend using our [Docker Compose](#docker-compose) build.

## Docker Compose

A [Compose file](https://docs.docker.com/compose/compose-file/) is a common format for defining and running multi-container Docker applications.

A `docker-compose.yml` file can be used to run a persistent/autostarted shaarli service using [Docker Compose](https://docs.docker.com/compose/) or in a [Docker stack](https://docs.docker.com/engine/reference/commandline/stack_deploy/).

Shaarli provides configuration file for Docker Compose, that will setup a Shaarli instance, a [Træfik](https://containo.us/traefik/) instance (reverse proxy) with [Let's Encrypt](https://letsencrypt.org/) certificates, a Docker network, and volumes for Shaarli data and Træfik TLS configuration and certificates.

Download docker-compose from the [release page](https://docs.docker.com/compose/install/):

```bash
$ sudo curl -L "https://github.com/docker/compose/releases/download/1.26.2/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
$ sudo chmod +x /usr/local/bin/docker-compose
```

To run Shaarli container and its reverse proxy, you can execute the following commands:

```bash
# create a new directory to store the configuration:
$ mkdir shaarli && cd shaarli
# Download the latest version of Shaarli's docker-compose.yml
$ curl -L https://raw.githubusercontent.com/shaarli/Shaarli/latest/docker-compose.yml -o docker-compose.yml
# Create the .env file and fill in your VPS and domain information
# (replace <MY_SHAARLI_DOMAIN> and <MY_CONTACT_EMAIL> with your actual information)
$ echo 'SHAARLI_VIRTUAL_HOST=shaarli.mydomain.org' > .env
$ echo 'SHAARLI_LETSENCRYPT_EMAIL=admin@mydomain.org' >> .env
# Pull the Docker images
$ docker-compose pull
# Run!
$ docker-compose up -d
```

After a few seconds, you should be able to access your Shaarli instance at [https://shaarli.mydomain.org](https://shaarli.mydomain.org) (replace your own domain name).

## Running dockerized Shaarli as a systemd service

It is possible to start a dockerized Shaarli instance as a systemd service (systemd is the service management tool on several distributions). After installing Docker, use the following steps to run your shaarli container Shaarli to run on system start.

As root, create `/etc/systemd/system/docker.shaarli.service`:

```ini
[Unit]
Description=Shaarli Bookmark Manager Container
After=docker.service
Requires=docker.service


[Service]
Restart=always

# Put any environment you want in an included file, like $host- or $domainname in this example
EnvironmentFile=/etc/sysconfig/box-environment

# It's just an example..
ExecStart=/usr/bin/docker run \
  -p 28010:80 \
  --name ${hostname}-shaarli \
  --hostname shaarli.${domainname} \
  -v /srv/docker-volumes-local/shaarli-data:/var/www/shaarli/data:rw \
  -v /etc/localtime:/etc/localtime:ro \
  shaarli/shaarli:latest

ExecStop=/usr/bin/docker rm -f ${hostname}-shaarli

[Install]
WantedBy=multi-user.target
```

```bash
# reload systemd services definitions
systemctl daemon-reload
# start the servie and enable it a boot time
systemctl enable docker.shaarli.service --now
# verify that the service is running
systemctl status docker.*
# inspect system log if needed
journalctl -f
```



## Docker cheatsheet

```bash
# pull/update an image
$ docker pull shaarli/shaarli:release
# run a container from an image
$ docker run shaarli/shaarli:latest
# list available images
$ docker images ls
# list running containers
$ docker ps
# list running AND stopped containers
$ docker ps -a
# run a command in a running container
$ docker exec -ti <container-name-or-first-letters-of-id> bash
# follow logs of a running container
$ docker logs -f <container-name-or-first-letters-of-id>
# delete unused images to free up disk space
$ docker system prune --images
# delete unused volumes to free up disk space (CAUTION all data in unused volumes will be lost)
$ docker system prunt --volumes
# delete unused containers
$ docker system prune
```


## References

- [Docker: using volumes](https://docs.docker.com/storage/volumes/)
- [Dockerfile best practices](https://docs.docker.com/articles/dockerfile_best-practices/)
- [Dockerfile reference](https://docs.docker.com/reference/builder/)
- [DockerHub: GitHub automated build](https://docs.docker.com/docker-hub/github/)
- [DockerHub: Repositories](https://docs.docker.com/userguide/dockerrepos/)
- [DockerHub: Teams and organizations](https://docs.docker.com/docker-hub/orgs/)
- [Get Docker CE for Debian](https://docs.docker.com/install/linux/docker-ce/debian/)
- [Install Docker Compose](https://docs.docker.com/compose/install/)
- [Interactive Docker training portal](https://www.katacoda.com/courses/docker/) on [Katakoda](https://www.katacoda.com/)
- [Service management: Nginx in the foreground](http://nginx.org/en/docs/ngx_core_module.html#daemon)
- [Service management: Using supervisord](https://docs.docker.com/articles/using_supervisord/)
- [Volumes](https://docs.docker.com/storage/volumes/)
- [Volumes](https://docs.docker.com/userguide/dockervolumes/)
- [Where are Docker images stored?](http://blog.thoward37.me/articles/where-are-docker-images-stored/)
- [docker create](https://docs.docker.com/engine/reference/commandline/create/)
- [Docker Documentation](https://docs.docker.com/)
- [docker exec](https://docs.docker.com/engine/reference/commandline/exec/)
- [docker images](https://docs.docker.com/engine/reference/commandline/images/)
- [docker logs](https://docs.docker.com/engine/reference/commandline/logs/)
- [docker logs](https://docs.docker.com/engine/reference/commandline/logs/)
- [Docker Overview](https://docs.docker.com/engine/docker-overview/)
- [docker ps](https://docs.docker.com/engine/reference/commandline/ps/)
- [docker pull](https://docs.docker.com/engine/reference/commandline/pull/)
- [docker run](https://docs.docker.com/engine/reference/commandline/run/)
- [docker-compose logs](https://docs.docker.com/compose/reference/logs/)
- Træfik: [Getting Started](https://docs.traefik.io/), [Docker backend](https://docs.traefik.io/configuration/backends/docker/), [Let's Encrypt](https://docs.traefik.io/user-guide/docker-and-lets-encrypt/), [Docker image](https://hub.docker.com/_/traefik/)