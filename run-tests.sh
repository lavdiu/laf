#!/bin/bash
set -e

echo "=== Installing dependencies ==="
composer install --no-interaction --prefer-dist --quiet --ignore-platform-req=php

echo ""
echo "=== Running Unit Tests ==="
vendor/bin/phpunit --testsuite Unit
echo ""

echo "=== Running Integration Tests (MariaDB) ==="
export LAF_DB_ENGINE=mysql
export LAF_DB_HOST=mariadb
export LAF_DB_PORT=3306
export LAF_DB_NAME=laf_test
export LAF_DB_USER=root
export LAF_DB_PASS=root
vendor/bin/phpunit --testsuite Integration
echo ""

unset LAF_DB_ENGINE LAF_DB_HOST LAF_DB_PORT LAF_DB_NAME LAF_DB_USER LAF_DB_PASS

echo "=== Running Integration Tests (PostgreSQL) ==="
export LAF_DB_ENGINE=postgres
export LAF_DB_HOST=postgres
export LAF_DB_PORT=5432
export LAF_DB_NAME=laf_test
export LAF_DB_USER=root
export LAF_DB_PASS=root
vendor/bin/phpunit --testsuite Integration
echo ""

echo "=== All tests passed ==="
