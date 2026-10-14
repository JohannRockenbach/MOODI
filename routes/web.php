<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;

Route::get('/', function () {
    $productHasAvailability = Schema::hasColumn('products', 'is_available');
    $categoryHasDisplayOrder = Schema::hasColumn('categories', 'display_order');

    $categories = Category::query()
        ->whereHas('products', function ($query) use ($productHasAvailability) {
            if ($productHasAvailability) {
                $query->where('is_available', true);
            }
        })
        ->with(['products' => function ($query) use ($productHasAvailability) {
            if ($productHasAvailability) {
                $query->where('is_available', true);
            }

            $query->orderBy('name');
        }]);

    if ($categoryHasDisplayOrder) {
        $categories->orderBy('display_order');
    } else {
        $categories->orderBy('name');
    }

    $categories = $categories->get();

    $products = Product::with('category');

    if ($productHasAvailability) {
        $products->where('is_available', true);
    }

    $products = $products
        ->orderBy('name')
        ->get();

    return view('welcome', compact('categories', 'products'));
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Pago por QR (futura actualización): página pública del aviso. El token es
// opaco (sha256, 64 hex) y NO se consulta la DB: se valida solo el formato
// para no revelar ningún dato de la operación (ni montos ni clientes).
Route::get('/pago-qr/{token}', function (string $token) {
    if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
        abort(404);
    }

    return view('pago-qr');
})->name('pago-qr');

Route::middleware('auth')->group(function () {
    Volt::route('/checkout', 'pages.checkout')->name('checkout');
});

require __DIR__.'/auth.php';
