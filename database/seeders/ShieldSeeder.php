<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class ShieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Generating permissions and roles via shield:generate...');

        Artisan::call('shield:generate', [
            '--all' => true,
        ]);

        $this->command->info('Shield generation complete.');
    }
}
