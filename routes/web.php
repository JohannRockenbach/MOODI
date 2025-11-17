<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Models\Caja;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // API endpoint for updating caja balance
    Route::get('/admin/cajas/{caja}/balance', function (Caja $caja) {
        // Return total sales for this caja
        $totalSales = $caja->sales()->sum('total_amount');
        return response()->json([
            'total_sales' => $totalSales,
            'initial_balance' => $caja->initial_balance,
        ]);
    })->name('cajas.balance');
});

require __DIR__.'/auth.php';
