<?php

namespace App\Livewire;

use App\Models\Cliente;
use App\Models\User;
use App\Support\DisplayText;
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

        $email = mb_strtolower(trim(DisplayText::plain($credentials['login_email'])));
        $password = (string) $credentials['login_password'];

        if (! Auth::attempt([
            'email' => $email,
            'password' => $password,
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
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

        $name = DisplayText::plain($data['name'], 'Cliente web');
        $email = mb_strtolower(trim(DisplayText::plain($data['email'])));
        $phone = DisplayText::plain($data['phone'] ?? null);

        $password = Hash::make($data['password']);

        // Si existe un usuario soft-deleted con el mismo email, restaurarlo y actualizarlo
        // (el UNIQUE(email) cuenta las filas borradas). En caso contrario, crearlo.
        $user = User::withTrashed()->where('email', $email)->first();

        if ($user) {
            $user->restore();
            $user->forceFill([
                'name' => $name,
                'phone' => $phone !== '' ? $phone : null,
                'birthday' => $data['birthday'],
                'password' => $password,
            ])->save();
        } else {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'birthday' => $data['birthday'],
                'password' => $password,
            ]);
        }

        // Garantizar el rol de cliente web (tanto en usuario nuevo como restaurado).
        $clienteRole = Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
        $user->assignRole($clienteRole);

        $cliente = Cliente::findByEmailWithTrashed($user->email);

        if ($cliente) {
            $cliente->restore();
        } else {
            $cliente = new Cliente;
        }

        $cliente->fill([
            'user_id' => $user->id,
            'email' => $user->email, // clientes.email es NOT NULL; sin esto el registro falla.
            'name' => $user->name,
            'phone' => $user->phone ?? null,
            'birthday' => $user->birthday ?? null,
            'fcm_token' => null,
        ])->save();

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
