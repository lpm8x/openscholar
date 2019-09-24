# OpenScholar

[![Build Status](https://travis-ci.org/openscholar/openscholar.svg?branch=8.x-1.x-dev)](https://travis-ci.org/openscholar/openscholar)

[![codecov](https://codecov.io/gh/openscholar/openscholar/branch/8.x-1.x-dev/graph/badge.svg)](https://codecov.io/gh/openscholar/openscholar)

A website building platform for universities, research centers, departments, labs and faculty.

## Installation

### Prerequisites

1. [Composer](https://getcomposer.org/download)

After that:

```
git clone --branch 8.x-1.x-dev https://github.com/openscholar/openscholar.git some-dir
cd some-dir
composer install --no-dev
```

With `composer require ...` you can download new dependencies to your installation.

```
cd some-dir
composer require drupal/devel:~1.0
```

## Development setup

### Prerequisites

1. [Docker](https://docs.docker.com/install)
2. Add `127.0.0.1 home.d8.theopenscholar.com` to your hosts file.

After that:

```bash

git clone https://github.com/jaybeaton/traefik-helper.git traefik-helper
cd traefik-helper
./traefik-helper.sh up -d
cd ..
git clone --branch 8.x-1.x-dev https://github.com/openscholar/openscholar.git some-dir
cd some-dir
cp defaults/.env .
cp defaults/settings.local.php web/sites/default
cp defaults/settings.php web/sites/default
docker-compose up -d
docker-compose exec php composer install
./drush.sh site-install openscholar -vvv -y --db-url=mysql://osd8dev:drupal@mariadb/osd8dev --existing-config --account-pass=ADMIN_PASSWORD
./drush.sh updb -y
./drush.sh entup -y
./drush.sh cim -y
make
./drush.sh cr
```

Access your development setup from http://home.d8.theopenscholar.com

## Note:
As the Docker image traefik has a new release, if you have downloaded the latest traefik-helper, then it needs modification.
In traefik-helper.yml file (path: `/tmp/traefik-helper.yml`), if you find image is using latest traefik version like shown below:
```
version: '2'

services:
  traefik:
    image: traefik
```
modify it to use the 1.7 verson as `image: traefik:1.7` to fix Docker errors.
