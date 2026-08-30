#!/bin/sh
# Runs once, the first time the postgres volume is initialised.
#
# why: the test suite runs against a real PostgreSQL database (see phpunit.xml)
# rather than SQLite, because the app uses PostgreSQL-specific SQL - ILIKE in
# Post::scopeSearch, for one. Testing on SQLite would pass while production
# broke. This creates the companion "<database>_test" database so that
# `git clone && make up && make test` works with no manual setup.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    SELECT 'CREATE DATABASE "${POSTGRES_DB}_test" OWNER "${POSTGRES_USER}"'
    WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '${POSTGRES_DB}_test')\gexec
EOSQL

echo "test database ${POSTGRES_DB}_test is ready"
