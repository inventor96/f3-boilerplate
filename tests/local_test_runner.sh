#!/bin/bash
cd "$(dirname "$0")"
mysql -u f3_boilerplate_test --password="testing" f3_boilerplate_test < ../app/db/testing.sql
ln -f ../app/config/config.testing.php ../app/config/config.php
php test_runner.php || true
ln -f ../app/config/config.dev.php ../app/config/config.php