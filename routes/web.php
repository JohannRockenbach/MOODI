<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\Caja;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

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

// --- RUTA DE EMERGENCIA PARA CREAR USUARIO ---
Route::get('/crear-admin-emergencia', function () {
    try {
        $email = 'admin@moodi.com'; // El correo que usaremos
        $password = 'password123';  // La contraseña provisional

        // Busca si existe, si no, lo crea
        $user = User::firstOrNew(['email' => $email]);
        
        $user->name = 'Super Admin';
        $user->password = Hash::make($password);
        $user->email_verified_at = now(); // Lo marcamos como verificado
        
        // Importante: aseguramos que restaurant_id sea null (Super Admin)
        // Solo si tu tabla tiene esta columna
        if (\Schema::hasColumn('users', 'restaurant_id')) {
            $user->restaurant_id = null;
        }

        $user->save();

        return "EXITO: Usuario creado/restablecido.<br>Email: $email<br>Password: $password<br><br><a href='/admin'>Ir al Login</a>";
    } catch (\Exception $e) {
        return "ERROR: " . $e->getMessage();
    }
});
