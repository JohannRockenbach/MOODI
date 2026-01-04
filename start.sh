#!/bin/bash

set -e

echo "--- Iniciando Despliegue MOODI ---"

echo "Limpiando archivos de configuración..."
rm -f .env

php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache

echo "Vinculando storage..."
rm -rf public/storage
php artisan storage:link


echo "Publicando estilos de Filament..."
php artisan filament:assets


echo "Iniciando Servidor Web..."
apache2-foreground