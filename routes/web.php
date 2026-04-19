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

Route::middleware('auth')->group(function () {
    Volt::route('/checkout', 'pages.checkout')->name('checkout');
});

require __DIR__.'/auth.php';
