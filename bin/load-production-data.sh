#!/bin/bash
#
# Loads production data into the current database
#
set -e

# Get directory containing this script
DIR="$( cd "$( dirname "$0" )" && pwd )"

# Load local environment, if present
if [ -e "${DIR}/../.env.local" ]; then
  source "${DIR}/../.env.local"
fi

if [ -z ${DATABASE_NAME+x} ]; then echo "DATABASE_NAME is required"; exit 1; fi
if [ -z ${DATABASE_HOST+x} ]; then echo "DATABASE_HOST is required"; exit 1; fi
if [ -z ${DATABASE_USER+x} ]; then echo "DATABASE_USER is required"; exit 1; fi

if [ -z ${PROD_DATA_PATH+x} ]; then echo "PROD_DATA_PATH is required"; exit 1; fi

##################################################
# No configuration necessary past here

# If true, load fixtures
DO_FIXTURES=1
# If true, load migrations
DO_MIGRATIONS=1

#
# Parse command line arguments
while test $# -gt 0; do
    case "$1" in
        --help)
            shift
            echo "Available options: "
            echo "  --skip-fixtures    Skip loading fixtures after loading database dump"
            echo "  --skip-migrations  Skip executing migrations after loading database dump"
            echo
            exit
            ;;
        --skip-fixtures)
            shift
            DO_FIXTURES=0
            ;;
        --skip-migrations)
            shift
            DO_MIGRATIONS=0
            ;;
        *)
            shift
            break
            ;;
    esac
done

# Confirm
read -p "Drop database '$DATABASE_NAME' and replace with production data? [yn] " -r
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    exit 1
fi

PROD_DATA_CACHE_FILE="${DIR}/../var/cache/prod-data.sql"
if [ -e "${PROD_DATA_CACHE_FILE}" ]; then
  echo "Loading from $PROD_DATA_CACHE_FILE (remove this file to download latest data)"
  echo ""
else
    echo "Downloading prod data to ${PROD_DATA_CACHE_FILE}"
    scp "$PROD_DATA_PATH" "${PROD_DATA_CACHE_FILE}.gz"
    gunzip "${PROD_DATA_CACHE_FILE}"
fi

# Assign password to variable mysql client recognizes
export MYSQL_PWD=$DATABASE_PASSWORD

echo "Dropping database..."
echo "drop database if exists ${DATABASE_NAME}" | mysql -h "$DATABASE_HOST" -u "$DATABASE_USER"
echo "create database \`${DATABASE_NAME}\`" | mysql -h "$DATABASE_HOST" -u "$DATABASE_USER"
echo ""

echo "Loading production data into ${DATABASE_NAME}..."
mysql -h "$DATABASE_HOST" -u "$DATABASE_USER" "$DATABASE_NAME" < "$PROD_DATA_CACHE_FILE"
echo ""

if [ "1" = "$DO_MIGRATIONS" ]; then
  echo "Applying any pending migrations..."
  bin/console -n doctrine:migrations:migrate
  echo ""
else
  echo "[!] Skipping migrations"
fi

if [ "1" = "$DO_FIXTURES" ]; then
  echo "Merging fixtures..."
  bin/console doctrine:fixtures:load --no-interaction --append --group=users
  echo ""
else
  echo "[!] Skipping fixtures"
fi

echo "Production data import finished!"
