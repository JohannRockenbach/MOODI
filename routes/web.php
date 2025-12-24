<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
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
    
    // API endpoint for table map - update position
    Route::post('/admin/table-map/update-position', function (Illuminate\Http\Request $request) {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (!$user || !$user->hasAnyRole(['super_admin'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $validated = $request->validate([
            'tableId' => 'required|integer',
            'posX' => 'required|integer',
            'posY' => 'required|integer',
        ]);
        
        $table = \App\Models\Table::find($validated['tableId']);
        if (!$table) {
            return response()->json(['error' => 'Table not found'], 404);
        }
        
        $table->update([
            'pos_x' => $validated['posX'],
            'pos_y' => $validated['posY'],
        ]);
        
        return response()->json(['success' => true]);
    })->name('table-map.update-position');
});

require __DIR__.'/auth.php';
