#!/usr/bin/env php
<?php
/**
 * Automates initial setup and common post-pull tasks
 *
 * Some commands are always executed:
 *  - composer install
 *  - web asset installation (dev unless --web-assets-prod is specified)
 *
 * See help text below for example usage
 */

// ---------------------------------------------------------------------------
// CLI Options and usage

$cliOpts = getopt(
    '', // no short options
    [
        // Specify the file to copy to .env.local (relative to project root)
        'local-env-from:',
        'create-database',
        'rebuild-database',
        'sync-database',
        'fixtures',
        // If true, production web assets will be compiled
        // This determines 'yarn run dev' vs. 'yarn run build'
        'web-assets-prod',

        // Aliases for common scenarios
        'for-local-development',

        'help',
    ]
);

// Flag to allow overriding the "is it a production server" check
$I_SAY_ITS_NOT_PROD = false;

if (isset($cliOpts['help'])) {
    $helpText = <<<HELP_TEXT
Usage
------------------------------

php bin/setup.php [options...]

Available Options
------------------------------

    --local-env-from=<environment file>     Copy specified file to .env.local
    --create-database                       Create a copy of the database (cannot already be created)
    --rebuild-database                      Drop and then create the database
    --sync-database                         Synchronize database with schema specified by the source code
    --fixtures                              Load fixtures
    --web-assets-prod                       When compiling web assets configure them for production use
    
    --for-local-development                 Alias to perform first-time setup for local development
    
Examples
------------------------------

First-time project setup (Docker):

    docker-compose exec app /app/bin/setup.php --local-env-from=.env.docker --rebuild-database
    
First-time project setup (Symfony Server):

    bin/setup.php --for-local-development
    
After updating from source control:

    bin/setup.php --post-update


HELP_TEXT;
    print $helpText;
    exit();
}

// ---------------------------------------------------------------------------
// Configure which stages should run based on CLI arguments

// Map of stage name => should it run?
$stages = [
    // Should a .env.local file be created?
    'create-local-env' => isset($cliOpts['local-env-from']) ? true : false,
    'composer-install' => true,
    'create-database' => isset($cliOpts['create-database']) ? true : false,
    'sync-database' => isset($cliOpts['sync-database']) ? true : false,
    'fixtures' => isset($cliOpts['fixtures']) ? true : false,
    // "dev" or "prod" build is determined by presence of --web-assets-prod flag
    'web-assets' => true,
];

// Aliases

if (isset($cliOpts['rebuild-database'])) {
    $stages['drop-database'] = true;
    $stages['create-database'] = true;
    $stages['sync-database'] = true;
    $stages['fixtures'] = true;
}

if (isset($cliOpts['for-local-development'])) {
    $I_SAY_ITS_NOT_PROD = true;
    $stages['create-local-env'] = true;
    $cliOpts['local-env-from'] = '.env.sqlite.dist';
    $stages['create-database'] = true;
    $stages['fixtures'] = true;
}

if (isset($cliOpts['post-update'])) {
    $stages['sync-database'] = true;
}

//
// Execute stages

if ($stages['create-local-env']) {
    $sourceFile = makePathFromRoot($cliOpts['local-env-from']);
    $targetFile = makePathFromRoot('.env.local');

    // Source file must exist
    if (!file_exists($sourceFile)) {
        writelnf('File does not exist: %s', $sourceFile);
        exit(1);
    }

    writelnf('Creating .env.local from %s', $sourceFile);

    // Target file cannot exist
    if (file_exists($targetFile)) {
        writelnf('ABORT: .env.local already exists');
        exit(1);
    }

    $r = copy($sourceFile, $targetFile);
    if (!$r) {
        writelnf('File copy failed');
        exit(1);
    }
}

if ($stages['composer-install']) {
    writelnf('Installing dependencies with composer');
    print mustRunCommand("composer install");
}

if ($stages['drop-database']) {
    writelnf('Dropping database');
    requireNotProduction();
    print mustRunSfCommand('doctrine:database:drop', [ '--force', '--if-exists' ]);
}

if ($stages['create-database']) {
    writelnf('Creating database');
    requireNotProduction();
    print mustRunSfCommand('doctrine:database:create', [ '--if-not-exists' ]);
}

if ($stages['sync-database']) {
    writelnf('Synchronizing database');
    print mustRunSfCommand('doctrine:schema:update', [ '--force' ]);
}

if ($stages['fixtures']) {
    writelnf('Loading fixtures');
    requireNotProduction();
    print mustRunSfCommand('doctrine:fixtures:load', [ '--no-interaction' ]);
}

if ($stages['web-assets']) {
    $assetSubcommand = isset($cliOpts['web-assets-prod']) ? 'build' : 'dev';
    writelnf('Building web assets');
    print mustRunCommand('yarn', [ 'install' ]);
    print mustRunCommand('yarn', ['run', $assetSubcommand]);
}


// ---------------------------------------------------------------------------
// Utility functions

function mustRunCommand($command, $args = [])
{
    list($output, $exitCode) = runCommandWithExitCode($command, $args);
    if ($exitCode !== 0) {
        print $output;
        exit($exitCode);
    }

    return $output;
}

function runCommand($command, $args = [])
{
    list($output, $exitCode) = runCommandWithExitCode($command, $args);

    return $output;
}

function mustRunSfCommand($command, $args = [])
{
    list($output, $exitCode) = runSfCommandWithExitCode($command, $args);
    if ($exitCode !== 0) {
        print $output;
        exit($exitCode);
    }

    return $output;
}

function runSfCommand($command, $args = [])
{
    list($output, $exitCode) = runSfCommandWithExitCode($command, $args);

    return $output;
}

function runSfCommandWithExitCode($command, $args = [])
{
    $sfBinPath = makePathFromRoot('bin', 'console');

    $command = sprintf('%s %s', $sfBinPath, $command);

    return runCommandWithExitCode($command, $args);
}

function runCommandWithExitCode($command, $args = [])
{
    $output = [];
    $exitCode = -1;

    $commandStr = $command;
    if ($args) $commandStr .= ' ' . join(" ", $args);

    exec($commandStr, $output, $exitCode);

    $strOutput = join(PHP_EOL, $output) . PHP_EOL;

    return [ $strOutput, $exitCode ];
}

function makePath()
{
    $args = func_get_args();

    return join(DIRECTORY_SEPARATOR, $args);
}

function makePathFromRoot()
{
    $rootDir = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..');
    $args = func_get_args();
    array_unshift($args, $rootDir);

    return call_user_func_array('makePath', $args);
}

function writelnf($message = '')
{
    if (func_num_args() == 0) {
        print PHP_EOL;
        return;
    }

    call_user_func_array('printf', func_get_args());
    print PHP_EOL;
}

function requireNotProduction()
{
    global $I_SAY_ITS_NOT_PROD;

    // SFAPP_IS_PRODUCTION environment variable always wins
    $hasProdEnvFlag = getenv('SFAPP_IS_PRODUCTION');
    if ($hasProdEnvFlag !== false) {
        writelnf("ABORT: cannot run, this host has the SFAPP_IS_PRODUCTION environment variable defined");
        exit(1);
    }

    // Believe the user
    if ($I_SAY_ITS_NOT_PROD) return;

    // Default to checking for something that indicates it's not production
    $prodTest = getenv('SFAPP_NOT_PRODUCTION');
    // Variable contents must evaluate to something truthy
    if ($prodTest != true) {
        writelnf("ABORT: this operation cannot be performed on a production host. (set environment variable SFAPP_NOT_PRODUCTION to true to bypass this check)");
        exit(1);
    }
}
