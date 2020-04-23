## Development

### Local Development with Symfony Server and sqlite

Requirements:

 * [Composer](https://getcomposer.org/)
 * [Symfony CLI application](https://symfony.com/download)
 * PHP configured with support for sqlite
 * [`yarn` command](https://yarnpkg.com/getting-started/install)

1. Copy `.env.local-sqlite.dist` to `.env.local`

    `cp .env.local-sqlite .env.local`

2. Install dependencies with composer

    `composer install`
    
3. Install dependencies with yarn and run initial build

    ```
    yarn install
    yarn dev
    ```
   
4. Start the Symfony web server

    `symfony serve`
    
5. Access at http://localhost:8080/ (or wherever `symfony serve` indicates)