#!/bin/sh
# Створює тестову БД і дає на неї права звичайному юзеру
# (у MySQL MYSQL_USER за замовчуванням бачить лише MYSQL_DATABASE).
set -e

mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<EOSQL
CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}${TEST_DB_SUFFIX:-_test}\`;
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}${TEST_DB_SUFFIX:-_test}\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
EOSQL
