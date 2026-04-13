<div
    x-data="{ open: false, tab: 'login' }"
    @open-auth-modal.window="open = true; tab = $event.detail.tab || 'login'"
    @keydown.escape.window="open = false"
    x-show="open"
    x-cloak
>
    <div class="fixed inset-0 z-[70] flex items-end justify-center p-3 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label="Autenticación de usuario">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="open = false"></div>

        <div class="relative w-full max-w-2xl max-h-[92vh] overflow-y-auto rounded-2xl bg-white p-4 pr-12 sm:p-6 sm:pr-14 md:p-8 md:pr-16 shadow-2xl" @click.stop>
            <button
                type="button"
                @click="open = false"
                class="absolute top-2 right-2 z-10 rounded-full bg-gray-50 p-2 text-gray-400 hover:text-gray-600 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500"
                aria-label="Cerrar"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6l-12 12" />
                </svg>
            </button>

            <div class="mb-5 flex rounded-xl bg-gray-100 p-1">
        <button
            type="button"
            @click="tab = 'login'"
            :class="tab === 'login' ? 'bg-white text-orange-600 shadow-sm' : 'text-gray-600'"
            class="flex-1 rounded-lg px-2.5 py-2 text-xs font-bold transition sm:px-4 sm:py-2.5 sm:text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500"
        >
            Iniciar Sesión
        </button>
        <button
            type="button"
            @click="tab = 'register'"
            :class="tab === 'register' ? 'bg-white text-orange-600 shadow-sm' : 'text-gray-600'"
            class="flex-1 rounded-lg px-2.5 py-2 text-xs font-bold transition sm:px-4 sm:py-2.5 sm:text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500"
        >
            Registrarse
        </button>
            </div>

    <div x-show="tab === 'login'" x-cloak>
        <div class="mb-6 text-center">
            <h2 class="text-xl sm:text-2xl font-black text-gray-900 mb-2">Iniciar Sesión</h2>
            <p class="text-sm text-gray-500">Ingresa tus credenciales para continuar</p>
        </div>

        <form wire:submit.prevent="login" class="space-y-4">
            <div>
                <label for="login_email" class="block text-sm font-semibold text-gray-700">Correo electrónico</label>
                <input wire:model="login_email" id="login_email" type="email" autocomplete="username" required class="mt-1 block w-full rounded-xl border-gray-300 focus:border-orange-500 focus:ring-orange-500" />
                @error('login_email') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="login_password" class="block text-sm font-semibold text-gray-700">Contraseña</label>
                <input wire:model="login_password" id="login_password" type="password" autocomplete="current-password" required class="mt-1 block w-full rounded-xl border-gray-300 focus:border-orange-500 focus:ring-orange-500" />
                @error('login_password') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label for="remember" class="inline-flex items-center gap-2 text-sm text-gray-600">
                <input wire:model="remember" id="remember" type="checkbox" class="rounded border-gray-300 text-orange-600 focus:ring-orange-500" />
                Recordarme
            </label>

            <button type="submit" class="w-full rounded-xl bg-orange-500 py-3 text-sm font-bold text-white hover:bg-orange-600 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2">
                Ingresar
            </button>
        </form>
    </div>

    <div x-show="tab === 'register'" x-cloak>
        <div class="mb-6 text-center">
            <h2 class="text-xl sm:text-2xl font-black text-gray-900 mb-2">Crear Cuenta</h2>
            <p class="text-sm text-gray-500">Únete a MOODI y disfruta de tus platillos favoritos</p>
        </div>

        <form wire:submit.prevent="register" class="space-y-4">
            <div>
                <label for="name" class="block text-sm font-semibold text-gray-700">Nombre</label>
                <input wire:model="name" id="name" type="text" autocomplete="name" required class="mt-1 block w-full rounded-xl border-gray-300 focus:border-orange-500 focus:ring-orange-500" />
                @error('name') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-semibold text-gray-700">Correo electrónico</label>
                <input wire:model="email" id="email" type="email" autocomplete="username" required class="mt-1 block w-full rounded-xl border-gray-300 focus:border-orange-500 focus:ring-orange-500" />
                @error('email') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="phone" class="block text-sm font-semibold text-gray-700">Teléfono</label>
                <input wire:model="phone" id="phone" type="tel" autocomplete="tel" placeholder="+56 9 1234 5678" class="mt-1 block w-full rounded-xl border-gray-300 focus:border-orange-500 focus:ring-orange-500" />
                @error('phone') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="birthday" class="block text-sm font-semibold text-gray-700">Fecha de Nacimiento</label>
                <input wire:model="birthday" id="birthday" type="date" autocomplete="bday" required class="mt-1 block w-full rounded-xl border-gray-300 focus:border-orange-500 focus:ring-orange-500" />
                @error('birthday') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-gray-700">Contraseña</label>
                <input wire:model="password" id="password" type="password" autocomplete="new-password" required class="mt-1 block w-full rounded-xl border-gray-300 focus:border-orange-500 focus:ring-orange-500" />
                @error('password') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-gray-700">Confirmar Contraseña</label>
                <input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" required class="mt-1 block w-full rounded-xl border-gray-300 focus:border-orange-500 focus:ring-orange-500" />
            </div>

            <button type="submit" class="w-full rounded-xl bg-orange-500 py-3 text-sm font-bold text-white hover:bg-orange-600 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2">
                Registrarme
            </button>
        </form>
    </div>
        </div>
    </div>
</div>
