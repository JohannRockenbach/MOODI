<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure a restaurant exists to associate the user with
        $restaurant = Restaurant::firstOrCreate(
            ['cuit' => '30-12345678-9'], // CUIT is required and must be unique
            [
                'name' => 'Default Restaurant',
                'address' => '123 Main St',
                'contact_phone' => '555-1234',
            ]
        );

        // Create the super_admin role if it doesn't exist
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        // Create the admin user
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@moodi.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'), // Change 'password' to a secure password
                'restaurant_id' => $restaurant->id, // Assign the restaurant ID
            ]
        );

        // Assign the super_admin role to the user
        if (!$adminUser->hasRole('super_admin')) {
            $adminUser->assignRole($superAdminRole);
            $this->command->info('Super admin role assigned to admin@moodi.com.');
        } else {
            $this->command->info('User admin@moodi.com already has the super admin role.');
        }

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: admin@moodi.com');
        $this->command->info('Password: password');
    }
}
    