<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'MOODI') }} - Autenticación</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 relative overflow-hidden">
            
            <!-- Background Image con Overlay -->
            <div class="absolute inset-0 z-0">
                <img src="https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=1920&h=1080&fit=crop&q=80" 
                     alt="Delicious food background" 
                     class="w-full h-full object-cover">
                <!-- Overlay oscuro con gradiente -->
                <div class="absolute inset-0 bg-gradient-to-br from-black/70 via-foodie-orange-900/60 to-black/80"></div>
                <!-- Patrón de textura sutil -->
                <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23ffffff&quot; fill-opacity=&quot;0.4&quot;%3E%3Cpath d=&quot;M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
            </div>

            <!-- Contenido -->
            <div class="relative z-10 w-full flex flex-col items-center px-4">
                
                <!-- Logo y Título -->
                <div class="text-center mb-8">
                    <a href="/" wire:navigate class="inline-block">
                        <div class="w-20 h-20 bg-gradient-to-br from-foodie-orange-500 to-foodie-orange-600 rounded-2xl flex items-center justify-center shadow-2xl hover:scale-110 transition-transform duration-300 mx-auto mb-4">
                            <span class="text-white font-black text-4xl">M</span>
                        </div>
                    </a>
                    <h1 class="text-3xl font-black text-white mb-2">
                        Bienvenido a <span class="text-foodie-orange-400">MOODI</span>
                    </h1>
                    <p class="text-foodie-orange-200 font-medium">
                        Tu comida favorita, a un click de distancia 🍕
                    </p>
                </div>

                <!-- Tarjeta del Formulario -->
                <div class="w-full sm:max-w-md">
                    <div class="bg-white/95 backdrop-blur-lg shadow-2xl overflow-hidden rounded-2xl border-4 border-foodie-orange-400">
                        <!-- Decoración superior -->
                        <div class="h-2 bg-gradient-to-r from-foodie-orange-500 via-foodie-red-500 to-foodie-mustard-500"></div>
                        
                        <!-- Contenido del formulario -->
                        <div class="px-8 py-10">
                            {{ $slot }}
                        </div>

                        <!-- Footer de la tarjeta -->
                        <div class="px-8 py-4 bg-foodie-orange-50 border-t border-foodie-orange-200">
                            @if (request()->routeIs('login'))
                                <p class="text-center text-sm text-foodie-text">
                                    ¿No tienes cuenta? 
                                    <a href="{{ route('register') }}" wire:navigate class="font-bold text-foodie-orange-600 hover:text-foodie-orange-700 hover:underline transition">
                                        Regístrate aquí
                                    </a>
                                </p>
                            @elseif (request()->routeIs('register'))
                                <p class="text-center text-sm text-foodie-text">
                                    ¿Ya tienes cuenta? 
                                    <a href="{{ route('login') }}" wire:navigate class="font-bold text-foodie-orange-600 hover:text-foodie-orange-700 hover:underline transition">
                                        Inicia sesión
                                    </a>
                                </p>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Link para volver -->
                    <div class="mt-6 text-center">
                        <a href="/" wire:navigate class="inline-flex items-center text-white hover:text-foodie-orange-300 font-semibold transition-colors duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Volver al inicio
                        </a>
                    </div>
                </div>
            </div>

            <!-- Elementos decorativos flotantes -->
            <div class="absolute top-20 left-10 w-20 h-20 bg-foodie-orange-400 rounded-full opacity-20 blur-2xl animate-pulse"></div>
            <div class="absolute bottom-20 right-10 w-32 h-32 bg-foodie-mustard-400 rounded-full opacity-20 blur-2xl animate-pulse" style="animation-delay: 1s;"></div>
        </div>
    </body>
</html>
