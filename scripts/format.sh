#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
# Bind the checkout explicitly: the regular app service runs the image's copy.
docker compose run --rm --no-deps -T --user "$(id -u):$(id -g)" \
  -e COMPOSER_HOME=/tmp/composer -v "$PWD/backend:/source" app sh -eu -c '
    mkdir -p /tmp/termin-format
    composer require --working-dir=/tmp/termin-format --no-interaction --no-progress laravel/pint:1.32.3
    cd /source
    /tmp/termin-format/vendor/bin/pint app bootstrap/app.php bootstrap/providers.php config database routes public/index.php artisan
  '
cd frontend
# Explicit flags also support checkouts created before .prettierrc.json existed.
npx --yes prettier@3.10.1 --write --semi --single-quote --print-width 100 \
  src index.html "*.ts" tsconfig.json package.json
