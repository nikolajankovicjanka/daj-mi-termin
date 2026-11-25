#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if [[ ! -e .env ]]; then
  umask 077
  sed -e "s|^APP_KEY=$|APP_KEY=base64:$(openssl rand -base64 32)|" \
      -e "s|^DB_PASSWORD=$|DB_PASSWORD=$(openssl rand -hex 24)|" \
      -e "s|^DB_ROOT_PASSWORD=$|DB_ROOT_PASSWORD=$(openssl rand -hex 24)|" \
      .env.example > .env
fi
docker compose up -d --build
if [[ "${1:-}" == "--demo" ]]; then
  docker compose exec -T -u www-data app php artisan db:seed
fi
