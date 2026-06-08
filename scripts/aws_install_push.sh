#!/bin/bash
# Run on AWS EC2 from /var/www/html
set -e
cd "$(dirname "$0")/.."

echo "==> DB config (password: osas)"
if [ ! -f config/db_connect.local.php ]; then
  cp config/db_connect.aws.example.php config/db_connect.local.php
  echo "Created config/db_connect.local.php"
fi

echo "==> Composer"
if [ -f composer.phar ]; then
  php composer.phar install --no-dev --optimize-autoloader
elif command -v composer >/dev/null; then
  composer install --no-dev --optimize-autoloader
else
  curl -sS https://getcomposer.org/installer | php
  php composer.phar install --no-dev --optimize-autoloader
fi

echo "==> Migration + schema repair"
php scripts/setup_push.php
php scripts/fix_push_schema.php

if [ ! -f app/config/push_config.local.php ]; then
  echo ""
  echo "Create app/config/push_config.local.php with VAPID keys."
  echo "Generate on PC: npx web-push generate-vapid-keys"
fi

php scripts/verify_push_setup.php
echo ""
echo "Test: curl -s https://eosas.duckdns.org/api/push.php?action=vapid-key"
