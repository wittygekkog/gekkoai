#!/bin/bash
# Install or refresh this Laravel app on a Hestia web domain.
# Run from the application root after the code is on disk.
# Database settings come from the environment so they are not stored in git.
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ -z "${DB_DATABASE:-}" || -z "${DB_USERNAME:-}" || -z "${DB_PASSWORD:-}" ]]; then
    echo "DB_DATABASE, DB_USERNAME, and DB_PASSWORD are required." >&2
    exit 1
fi

APP_URL="${APP_URL:-https://gekkoai.wittygekko.com}"

echo "== composer"
composer install --no-dev --optimize-autoloader --no-interaction

if [[ ! -f .env ]]; then
    cp .env.example .env
fi

php -r '
$env = file_get_contents(".env");
$values = [
    "APP_NAME" => "GekkoAi",
    "APP_ENV" => "production",
    "APP_DEBUG" => "false",
    "APP_URL" => getenv("APP_URL"),
    "LOG_LEVEL" => "error",
    "DB_CONNECTION" => "mysql",
    "DB_HOST" => "localhost",
    "DB_PORT" => "3306",
    "DB_DATABASE" => getenv("DB_DATABASE"),
    "DB_USERNAME" => getenv("DB_USERNAME"),
    "DB_PASSWORD" => getenv("DB_PASSWORD"),
];
foreach ($values as $key => $value) {
    $line = $key."=".$value;
    if (preg_match("/^".preg_quote($key, "/")."=.*/m", $env)) {
        $env = preg_replace("/^".preg_quote($key, "/")."=.*/m", $line, $env);
    } else {
        $env .= PHP_EOL.$line;
    }
}
file_put_contents(".env", $env);
'

if ! grep -q "^APP_KEY=base64:" .env; then
    php artisan key:generate --force
fi

echo "== migrate and seed"
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link || true
php artisan config:cache
php artisan view:cache

chmod -R ug+rwx storage bootstrap/cache
echo "== done"
