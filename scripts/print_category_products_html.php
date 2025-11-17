<?php

// Boots the Laravel application and prints the HTML that the CategoryResource's
// 'productos' TextColumn would render for each category. This helps verify the
// inline expand/collapse subtable server-side without opening Filament.

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Category;
use Illuminate\Support\Str;

$categories = Category::with('products')->get();

foreach ($categories as $record) {
    echo "=== Category {$record->id} - {$record->name} ===\n";

    $id = $record->id;
    $products = $record->products;

    $html = '<div>';
    $html .= '<button onclick="document.getElementById(\'cat-products-' . $id . '\').classList.toggle(\'hidden\')" class="filament-button filament-button-size-sm">Mostrar productos</button>';
    $html .= '<div id="cat-products-' . $id . '" class="hidden mt-2">';

    if ($products->isEmpty()) {
        $html .= '<div style="margin-top:.5rem">No hay productos en esta categoría.</div>';
    } else {
        $html .= '<table style="width:100%; border-collapse: collapse">';
        $html .= '<thead><tr><th style="text-align:left; padding:8px">Nombre</th><th style="text-align:left; padding:8px">Cantidad</th><th style="text-align:left; padding:8px">Descripción / Ingredientes</th><th style="text-align:left; padding:8px">Acciones</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($products as $p) {
            $name = htmlspecialchars($p->name ?? '', ENT_QUOTES, 'UTF-8');
            $stock = htmlspecialchars($p->stock ?? '-', ENT_QUOTES, 'UTF-8');
            $desc = htmlspecialchars(Str::limit($p->description, 200), ENT_QUOTES, 'UTF-8');
            $editUrl = htmlspecialchars(\App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $p->id]), ENT_QUOTES, 'UTF-8');

            $html .= '<tr>';
            $html .= '<td style="padding:8px">' . $name . '</td>';
            $html .= '<td style="padding:8px">' . $stock . '</td>';
            $html .= '<td style="padding:8px">' . $desc . '</td>';
            $html .= '<td style="padding:8px">';
            $html .= '<a href="' . $editUrl . '" class="filament-link">Editar</a>';
            $html .= '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    }

    $html .= '</div></div>';

    echo $html . "\n\n";
}

echo "Done.\n";
