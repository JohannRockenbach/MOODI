#!/usr/bin/env bash
# Salir si hay errores
set -o errexit

# 1. Instalar dependencias de PHP
composer install --no-dev --optimize-autoloader

# 2. Instalar dependencias de Node y Compilar Assets (Vite)
npm install
npm run build

# 3. Limpiar cachés (Importante en producción)
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

# 4. Correr migraciones (Opcional, pero recomendado para mantener la DB al día)
php artisan migrate --force