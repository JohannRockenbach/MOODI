<?php

use App\Models\Cliente;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Spatie\Permission\Models\Role;

use function Livewire\Volt\layout;
use function Livewire\Volt\rules;
use function Livewire\Volt\state;

layout('layouts.guest');

state([
    'name' => '',
    'email' => '',
    'phone' => '',
    'birthday' => '',
    'password' => '',
    'password_confirmation' => ''
]);

rules([
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
    'phone' => ['nullable', 'string', 'max:20'],
    'birthday' => ['required', 'date', 'before:today'],
    'password' => ['required', 'string', 'confirmed', 'min:8'],
]);

$register = function () {
    try {
        $validated = $this->validate();

        $defaultRestaurantId = Restaurant::query()->whereKey(1)->value('id')
            ?: Restaurant::query()->value('id');

        if (! $defaultRestaurantId) {
            $this->addError('email', 'No hay restaurante configurado para registrar clientes.');
            return;
        }

        $validated['password'] = Hash::make($validated['password']);
        $validated['restaurant_id'] = $defaultRestaurantId;

        $user = DB::transaction(function () use ($validated, $defaultRestaurantId) {
            $user = User::create($validated);

            $clienteRole = Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
            $user->assignRole($clienteRole);

            Cliente::create([
                'user_id'  => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'phone'    => $user->phone ?? null,
                'birthday' => $user->birthday ?? null,
                'fcm_token' => null,
                'restaurant_id' => $defaultRestaurantId,
            ]);

            return $user;
        });

        event(new Registered($user));

        // No auto-login: el usuario debe iniciar sesión manualmente
        // Auth::login($user);

        session()->flash('status', '¡Cuenta creada exitosamente! Por favor inicia sesión.');

        return $this->redirect(route('login'), navigate: true);
    } catch (\Throwable $e) {
        Log::error('Web register failed', [
            'email' => $this->email,
            'message' => $e->getMessage(),
        ]);

        $this->addError('email', 'No se pudo completar el registro. Intenta nuevamente.');
    }
};

?>

<div>
    <!-- Título -->
    <div class="mb-6 text-center">
        <h2 class="text-2xl font-black text-foodie-text mb-2">Crear Cuenta</h2>
        <p class="text-sm text-foodie-text-light">Únete a MOODI y disfruta de tus platillos favoritos</p>
    </div>

    <form wire:submit="register" class="space-y-4">
        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Phone -->
        <div>
            <x-input-label for="phone" :value="__('Teléfono')" />
            <x-text-input wire:model="phone" id="phone" class="block mt-1 w-full" type="tel" name="phone" autocomplete="tel" placeholder="+56 9 1234 5678" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        <!-- Birthday -->
        <div>
            <x-input-label for="birthday" :value="__('Fecha de Nacimiento')" />
            <x-text-input wire:model="birthday" id="birthday" class="block mt-1 w-full" type="date" name="birthday" autocomplete="bday" />
            <x-input-error :messages="$errors->get('birthday')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="pt-2">
            <x-primary-button class="w-full justify-center">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</div>
