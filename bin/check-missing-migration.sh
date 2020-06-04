#!/bin/bash
#
# Helper script to generate migrations and ensure no migrations are missed
#
# Usage:
#
#   bin/check-missing-migration.sh
#
# To automatically generate a migration if one is necessary:
#
#   bin/check-missing-migration.sh --generate
#
#
# This script is aware of the following variables:
#   SCHEMA_CHECK_DB_NAME database name to use when doing schema checks, will have all data removed!
#   SCHEMA_CHECK_HOST database host to connect to
#   SCHEMA_CHECK_USER database user
#   SCHEMA_CHECK_PASSWORD database password, optional. Do not set if there's no password
#
#   SCHEMA_COMPARISON_URL URL to a dump of the current production database's schema
#   SCHEMA_CURRENT_VERSIONS_URL URL to a dump of migrations that have been installed to production
#
set -e

# Get directory containing this script
DIR="$( cd "$( dirname "$0" )" && pwd )"

# Load local environment, if present
if [ -e "${DIR}/../.env.local" ]; then
  source "${DIR}/../.env.local"
fi

if [ -z ${SCHEMA_CHECK_DB_NAME+x} ]; then echo "SCHEMA_CHECK_DB_NAME is required"; exit 1; fi
if [ -z ${SCHEMA_CHECK_HOST+x} ]; then echo "SCHEMA_CHECK_HOST is required"; exit 1; fi
if [ -z ${SCHEMA_CHECK_USER+x} ]; then echo "SCHEMA_CHECK_USER is required"; exit 1; fi

if [ -z ${SCHEMA_COMPARISON_URL+x} ]; then echo "SCHEMA_COMPARISON_URL is required"; exit 1; fi
if [ -z ${SCHEMA_CURRENT_VERSIONS_URL+x} ]; then echo "SCHEMA_CURRENT_VERSIONS_URL is required"; exit 1; fi

##################################################
# No configuration necessary past here

# Private vars used for password in different contexts
SCHEMA_CHECK_PASSWORD_IN_URL=""
SCHEMA_CHECK_PASSWORD_IN_COMMAND=""
if [[ "$SCHEMA_CHECK_PASSWORD" != "" ]]; then
  SCHEMA_CHECK_PASSWORD_IN_URL=":${SCHEMA_CHECK_PASSWORD}"
  SCHEMA_CHECK_PASSWORD_IN_COMMAND="\"-p${SCHEMA_CHECK_PASSWORD}\""
fi

# Build temporarty database URL from environment variables
export DATABASE_URL="mysql://${SCHEMA_CHECK_USER}${SCHEMA_CHECK_PASSWORD_IN_URL}@${SCHEMA_CHECK_HOST}/${SCHEMA_CHECK_DB_NAME}?charset=UTF-8"

# drop database
echo "Dropping database used for schema diff"
bin/console doctrine:database:drop --if-exists --force

# start over with production database
echo "Creating database used for schema diff"
bin/console doctrine:database:create

# copy schema from prod
echo "Getting latest schema"
curl --silent "$SCHEMA_COMPARISON_URL" | mysql -h "$SCHEMA_CHECK_HOST" -u "$SCHEMA_CHECK_USER" $SCHEMA_CHECK_PASSWORD_IN_COMMAND "$SCHEMA_CHECK_DB_NAME"
echo "Getting current database versions"
curl --silent "$SCHEMA_CURRENT_VERSIONS_URL" | mysql -h "$SCHEMA_CHECK_HOST" -u "$SCHEMA_CHECK_USER" $SCHEMA_CHECK_PASSWORD_IN_COMMAND "$SCHEMA_CHECK_DB_NAME"

# run migrations that exist but have not been applied to production yet
echo "Running pending migrations"
bin/console -n doctrine:migrations:migrate

# make sure there aren't any still remaining (exit code 1 means "not found", other exit codes are errors)
HAS_CHANGES=$(bin/console doctrine:schema:update --dump-sql | grep "statements will be executed" || [[ $? == 1 ]])

if [[ "$HAS_CHANGES" != "" ]]; then
  echo "There are database changes not present in any migration!"
  echo

  bin/console doctrine:schema:update --dump-sql

  # Generate the migration if desired
  if [[ "$1" == "--generate" ]]; then bin/console doctrine:migrations:diff; fi

  exit 1
fi

echo
echo "OK - Schema is current"
