#!/bin/bash
set -e

# This script runs automatically when the PostgreSQL container is first initialized
# It creates the TimescaleDB extension for the application database

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    -- Enable TimescaleDB extension
    CREATE EXTENSION IF NOT EXISTS timescaledb;

    -- Grant all privileges on the database
    GRANT ALL PRIVILEGES ON DATABASE "$POSTGRES_DB" TO "$POSTGRES_USER";

    -- Grant schema permissions
    GRANT ALL ON SCHEMA public TO "$POSTGRES_USER";
EOSQL

echo "TimescaleDB extension enabled for database: $POSTGRES_DB"
