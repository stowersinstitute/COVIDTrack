# Symfony Based COVID Sample Tracker

## Development Environment Install

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