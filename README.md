# COVID Sample Tracker

## Development Environment - Docker

1. [Download and install Docker](https://www.docker.com/)
1. `cp .env.docker .env.local`
1. `docker-compose up -d`
1. `docker-compose exec app /bin/bash` This puts you in the docker container.
    1. `composer install`
    1. `bin/console doctrine:schema:build --force`
    1. `yarn install`
    1. `yarn dev`
    1. Open Docker Application URL <http://localhost:8880/samples/>

Enter the container before running any PHP, Symfony, or yarn commands:

```bash
docker-compose exec app /bin/bash
``` 

If changing `Dockerfile`, rebuild the containers:

```bash
docker-compose up --build -d
```

#### Docker JS and CSS assets

Recompile frontend assets after changing `package.json`, using `yarn add`, or changing CSS.

1. `docker-compose exec app /bin/bash` This puts you in the docker container.
1. `yarn dev`

Alternatively you can run `yarn watch` for automatic recompiling.


## Development Environment - Symfony Server

Instead of using Docker, develop with tools installed directly on the host machine:

 * [PHP 7.4+](https://www.php.net/) configured with support for sqlite
 * [Composer](https://getcomposer.org/)
 * [Symfony CLI application](https://symfony.com/download)
 * [SQLite](https://www.sqlite.org/download.html)
 * [`yarn` command](https://yarnpkg.com/getting-started/install)

1. Copy `.env.sqlite.dist` to `.env.local`

    `cp .env.sqlite.dist .env.local`

2. Install dependencies with composer

    `composer install`
    
3. Install dependencies with yarn and run initial build

    ```
    yarn install
    yarn dev
    ```
    
4. Create database

	```
    bin/console doctrine:schema:create
    ```
   
5. Start the Symfony web server

    `symfony serve`
    
6. Access at http://localhost:8080/ (or wherever `symfony serve` indicates)

## Data Fixtures

Fake data is managed by [DoctrineFixturesBundle](https://symfony.com/doc/master/bundles/DoctrineFixturesBundle/index.html).

To load fake data and clear out all existing database data:

    $ php bin/console doctrine:fixtures:load -n

Or add the `--append` flag to keep existing database data and append fake data:

    $ php bin/console doctrine:fixtures:load -n --append

Create new fixtures in `src/DataFixtures/AppFixtures.php`

### CSS Framework

[Bootstrap 3.4.1](https://getbootstrap.com/docs/3.4/components/) has built-in styles and components.
