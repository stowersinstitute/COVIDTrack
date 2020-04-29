# COVID Sample Tracker

## Development Environment - Docker

1. [Download and install Docker](https://www.docker.com/)
1. `docker-compose up -d`
1. `docker-compose exec app /app/bin/setup.php --local-env-from=.env.docker --rebuild-database` – This configures .env, creates database, loads fake data
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

Recompile frontend assets after changing `package.json`, using `yarn add`, or changing CSS:

    $ docker-compose exec app yarn dev

Alternatively you can run `yarn watch` for automatic recompiling.


## Development Environment - Symfony Server

Instead of using Docker, develop with tools installed directly on the host machine:

 * [PHP 7.1.10+](https://www.php.net/) configured with support for sqlite
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

    $ bin/console doctrine:fixtures:load -n

Or add the `--append` flag to keep existing database data and append fake data:

    $ bin/console doctrine:fixtures:load -n --append

Create new fixtures in `src/DataFixtures/AppFixtures.php`

### Frontend Frameworks

* [AdminLTE Theme 2.4.18](https://github.com/ColorlibHQ/AdminLTE) application theme – [Documentation](https://adminlte.io/docs/2.4/installation)
* [Bootstrap 3.4.1](https://getbootstrap.com/docs/3.4/components/) has built-in styles and components
* [Font Awesome 4.7.0](https://fontawesome.com/v4.7.0/) for extra icons
