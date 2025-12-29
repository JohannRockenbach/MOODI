#!/bin/bash

# Mostrar lo que se está ejecutando
set -e

echo "--- Iniciando Despliegue MOODI ---"

# 1. Borrar .env intruso si existe (para forzar uso de variables de Render)
echo "Limpiando archivos de configuración..."
rm -f .env

# 2. Limpiar cachés de Laravel
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 3. Forzar caché de configuración (Lee variables de Neon)
php artisan config:cache

# 4. Arreglar imágenes (Storage)
echo "Vinculando storage..."
rm -rf public/storage
php artisan storage:link

# 5. Iniciar Apache
echo "Iniciando Servidor Web..."
apache2-foreground