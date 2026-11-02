$ErrorActionPreference = "Stop"
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
composer install
php artisan key:generate
Write-Host "Configurez DB_* dans .env et créez la base MySQL, puis exécutez :"
Write-Host "php artisan migrate --seed"
Write-Host "php artisan storage:link"
Write-Host "php artisan serve"
