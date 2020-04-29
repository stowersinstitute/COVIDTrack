# Symfony Based COVID Sample Tracker

## Development Environment Install

### Docker
1. `cp .env.docker .env.local`
1. `docker-compose up -d`
1. `docker-compose exec app /bin/bash` This puts you in the docker container.
    1. `composer install`
    1. `bin/console doctrine:schema:build --force`
    1. `yarn install`
    1. `yarn dev`
    
This will initialize the environment. After that you only need to run yarn if you add a library to `package.json` (or vie `yarn add`) or update CSS. Within the app docker container you can run `yarn dev` to update the compiled CSS and JS. Alternatively you can run `yarn watch` for auto recompiling.

Just remember if you need to run php or yarn commands jump in to the container first by doing the following command.

```bash
docker-compose exec app /bin/bash
``` 

Docker Application URL: <http://localhost:8880/samples/>

### Local Development with Symfony Server and sqlite

Requirements:

 * [Composer](https://getcomposer.org/)
 * [Symfony CLI application](https://symfony.com/download)
 * PHP configured with support for sqlite
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

### Data Fixtures

Fake data is managed by [DoctrineFixturesBundle](https://symfony.com/doc/master/bundles/DoctrineFixturesBundle/index.html).

To load fake data and clear out all existing database data:

    $ php bin/console doctrine:fixtures:load -n

Or add the `--append` flag to keep existing database data and append fake data:

    $ php bin/console doctrine:fixtures:load -n --append

Create new fixtures in `src/DataFixtures/AppFixtures.php`

### Frontend Frameworks

* [Bootstrap 3.4.1](https://getbootstrap.com/docs/3.4/components/) has built-in styles and components
* [Font Awesome 4.7.0](https://fontawesome.com/v4.7.0/) for extra icons
