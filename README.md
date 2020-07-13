# COVID Sample Tracker

## Configuration

### Enabling LDAP / Active Directory Authentication

The following environment variables are available:

 * `LDAP_HOST` (required) LDAP server hostname
 * `LDAP_PORT` (default 389) port to communicate on
 * `LDAP_ENCRYPTION` (default none) Valid values are none, ssl, tls
 * `LDAP_PROTOCOL_VERSION` (default 3)
 * `LDAP_REFERRALS` (default false)
 * `LDAP_AUTH_BASE_DN` DN to use when searching for users
 * `LDAP_AUTH_SEARCH_DN` User to log in as when checking if a user or their credentials are valid (eg. "user@EXAMPLE.COM" or "cn=read-only-admin,dc=example,dc=com")
 * `LDAP_AUTH_SEARCH_PASSWORD` Password to use when authenticating as `LDAP_AUTH_SEARCH_DN` 
 * `LDAP_AUTH_USER_DN_FORMAT` DN to use when looking up a user to authenticate. For example: `{username}@company.com` 
 
**Active Directory Example**

Add to `.env.local` or otherwise define them in the environment.

```
LDAP_HOST=directory.contoso.com
LDAP_AUTH_BASE_DN=DC=contoso,DC=com
LDAP_AUTH_SEARCH_DN=serviceaccount@CONTOSO.COM
LDAP_AUTH_SEARCH_PASSWORD=hunter2
LDAP_AUTH_USER_DN_FORMAT={username}@CONTOSO.COM
```


## Development Environment - Docker

1. [Download and install Docker](https://www.docker.com/)
1. `docker-compose up -d`
1. `docker-compose exec app /app/bin/setup.php --local-env-from=.env.docker --rebuild-database` – This configures .env, creates database, loads fake data
1. Open Docker Application URL <http://localhost:8880/specimens/>

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

#### Docker Environment Variables

Such as configuring for LDAP or setup.php script. Edit `docker-compose.yml` in each service's environment section:

    # docker-compose.yml
    services:
        app:
            environment:
                SFAPP_NOT_PRODUCTION: "true"


## Development Environment - Symfony Server

Instead of using Docker, develop with tools installed directly on the host machine:

 * [PHP 7.1.3+](https://www.php.net/) configured with support for sqlite
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

## Running Automated Tests

Tests written using [PHPUnit](https://phpunit.de/) and executed using [Symfony PHPUnit Bridge](https://symfony.com/doc/4.4/testing.html).

Run test suite from command-line of a local development environment:

    $ bin/run-tests.sh

To run on a CI server, create a fresh local development environment then run:

    $ git clone ...
    $ bin/setup.php --for-local-development
    $ bin/run-tests.sh

## Data Fixtures

Fake data is managed by [DoctrineFixturesBundle](https://symfony.com/doc/master/bundles/DoctrineFixturesBundle/index.html).

To load fake data and clear out all existing database data:

    $ bin/console doctrine:fixtures:load -n

Or add the `--append` flag to keep existing database data and append fake data:

    $ bin/console doctrine:fixtures:load -n --append

Create new fixtures in `src/DataFixtures/AppFixtures.php`

## Login to dev environment with fixtures loaded

These users are available when fixtures are loaded. Same username/password:

* ctadmin - Sysadmin / Developer
* coordinator - Study Coordinator
* mediaprep - Media Prep Team (specimen collection kit management)
* samplecollection - Sample Collection Team
* testingtech - Viral Testing Team / Results
* analysistech - Viral Analysis Team
* kiosk - Kiosk UI

## Development Environment - Monitoring Sent Email

Use [Mailhog](https://github.com/mailhog/MailHog) as a Docker image to see email sent by the application:

    $ docker run --rm -p8025:8025 -p1025:1025 mailhog/mailhog

Add environment var `MAILER_DSN` to file .env.local:

    # .env.local
    MAILER_DSN=smtp://127.0.0.1:1025

Perform actions that would normally send an application email.

View mail in Mailhog: <http://0.0.0.0:8025>

### Frontend Frameworks

* [AdminLTE Theme 2.4.18](https://github.com/ColorlibHQ/AdminLTE) application theme – [Documentation](https://adminlte.io/docs/2.4/installation)
* [Bootstrap 3.4.1](https://getbootstrap.com/docs/3.4/components/) has built-in styles and components
* [Font Awesome 5.13](https://fontawesome.com/icons?d=gallery&m=free) for extra icons

### User Documentation

[User Documentation](https://confluence.stowers.org/x/GQB8CQ) for using software from user's perspective is available.
