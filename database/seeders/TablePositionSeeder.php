<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Table;

class TablePositionSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        Table::truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
        
        $tables = [
            // Fila Superior (Terraza)
            ['number' => 10, 'capacity' => 4, 'location' => 'Terraza', 'pos_x' => 50, 'pos_y' => 50],
            ['number' => 11, 'capacity' => 2, 'location' => 'Terraza', 'pos_x' => 190, 'pos_y' => 50],
            ['number' => 12, 'capacity' => 2, 'location' => 'Terraza', 'pos_x' => 330, 'pos_y' => 50],
            ['number' => 13, 'capacity' => 6, 'location' => 'Terraza', 'pos_x' => 470, 'pos_y' => 50],
            
            // Fila Central (Salón)
            ['number' => 20, 'capacity' => 4, 'location' => 'Salón', 'pos_x' => 50, 'pos_y' => 220],
            ['number' => 21, 'capacity' => 4, 'location' => 'Salón', 'pos_x' => 190, 'pos_y' => 220],
            ['number' => 22, 'capacity' => 2, 'location' => 'Salón', 'pos_x' => 330, 'pos_y' => 220],
            ['number' => 23, 'capacity' => 4, 'location' => 'Salón', 'pos_x' => 470, 'pos_y' => 220],
            
            // Fila Inferior (Barra)
            ['number' => 30, 'capacity' => 1, 'location' => 'Barra', 'pos_x' => 50, 'pos_y' => 390],
            ['number' => 31, 'capacity' => 1, 'location' => 'Barra', 'pos_x' => 190, 'pos_y' => 390],
            ['number' => 32, 'capacity' => 1, 'location' => 'Barra', 'pos_x' => 330, 'pos_y' => 390],
            ['number' => 33, 'capacity' => 1, 'location' => 'Barra', 'pos_x' => 470, 'pos_y' => 390],
        ];

        foreach ($tables as $table) {
            Table::create(array_merge($table, [
                'restaurant_id' => 1,
                'status' => 'available',
                'waiter_id' => null,
            ]));
        }
        
        $this->command->info('✅ 12 mesas creadas con sus posiciones');
    }
}
