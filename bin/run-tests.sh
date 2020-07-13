#!/usr/bin/env bash
#
# Runs tests for this project.
#
# Usage:
#   $ ./bin/run-tests.sh

# Abort script if any command encounters an error
set -e

# Get directory containing this script
DIR="$( cd "$( dirname "$0" )" && pwd )"

# Run from project root
cd "${DIR}/../"

# Refresh test database
bin/console doctrine:database:drop --env=test --force
bin/console doctrine:database:create --env=test
bin/console doctrine:schema:update --env=test --force

# Execute using Symfony's PHPUnit bridge
# Usage: https://symfony.com/doc/4.4/components/phpunit_bridge.html#usage
./vendor/bin/simple-phpunit
