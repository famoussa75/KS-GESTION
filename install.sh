#!/usr/bin/env bash
set -euo pipefail
[ -f .env ] || cp .env.example .env
composer install
php artisan key:generate
printf '\nConfigurez maintenant DB_* dans .env, créez la base MySQL, puis lancez :\n'
printf '  php artisan migrate --seed\n  php artisan storage:link\n  php artisan serve\n'
