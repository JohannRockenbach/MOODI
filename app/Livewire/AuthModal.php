<?php

namespace App\Livewire;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class AuthModal extends Component
{
    public string $login_email = '';
    public string $login_password = '';
    public bool $remember = false;

    public string $name = '';
    public string $email = '';
    public ?string $phone = null;
    public string $birthday = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function login(): void
    {
        $credentials = $this->validate([
            'login_email' => ['required', 'string', 'email'],
            'login_password' => ['required', 'string'],
        ], [], [
            'login_email' => 'correo',
            'login_password' => 'contraseña',
        ]);

        if (!Auth::attempt([
            'email' => $credentials['login_email'],
            'password' => $credentials['login_password'],
        ], $this->remember)) {
            throw ValidationException::withMessages([
                'login_email' => 'Las credenciales ingresadas no son válidas.',
            ]);
        }

        session()->regenerate();

        $this->reset(['login_password']);

        $this->redirect('/', navigate: false);
    }

    public function register(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'phone' => ['nullable', 'string', 'max:20'],
            'birthday' => ['required', 'date', 'before:today'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ], [], [
            'name' => 'nombre',
            'email' => 'correo',
            'phone' => 'teléfono',
            'birthday' => 'fecha de nacimiento',
            'password' => 'contraseña',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'birthday' => $data['birthday'],
            'password' => Hash::make($data['password']),
        ]);

        $clienteRole = Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
        $user->assignRole($clienteRole);

        Cliente::updateOrCreate(
            ['email' => $user->email],
            [
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone ?? null,
                'birthday' => $user->birthday ?? null,
                'fcm_token' => null,
            ]
        );

        event(new Registered($user));

        Auth::login($user);
        session()->regenerate();

        $this->redirect('/', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth-modal');
    }
}
