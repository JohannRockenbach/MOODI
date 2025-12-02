#!/bin/bash

# Script para limpiar caché de Livewire/Filament después de eliminar WeatherOverview

echo "🧹 Limpiando cachés de Laravel/Filament/Livewire..."
echo "=========================================="

cd "$(dirname "$0")/.." || exit

echo ""
echo "1. Limpiando caché de Filament..."
php artisan filament:clear-cached-components

echo ""
echo "2. Limpiando todas las cachés de Laravel..."
php artisan optimize:clear

echo ""
echo "3. Reconstruyendo cachés..."
php artisan config:cache
php artisan route:cache

echo ""
echo "=========================================="
echo "✅ Cachés limpiadas y reconstruidas"
echo ""
echo "⚠️  IMPORTANTE: Ahora debes limpiar la caché del NAVEGADOR:"
echo ""
echo "   Firefox: Ctrl + Shift + Delete"
echo "   Chrome: Ctrl + Shift + Delete"
echo "   O simplemente: Ctrl + F5 (forzar recarga)"
echo ""
echo "   O abre el navegador en modo incógnito: Ctrl + Shift + N"
echo "=========================================="
