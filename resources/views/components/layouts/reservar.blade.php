<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Reservar mesa - MOODI</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet"/>

    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased text-gray-800 overflow-x-hidden min-h-screen">

    {{-- Header del cliente (tema naranja MOODI) --}}
    <header class="sticky top-0 z-50 bg-white border-b border-orange-100 shadow-sm">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between gap-3">
            <a href="{{ url('/') }}" class="text-3xl font-black tracking-tight text-orange-600">
                MOODI
            </a>

            <a href="{{ url('/') }}" class="inline-flex h-11 items-center rounded-full border border-orange-200 bg-white px-5 text-sm font-bold text-orange-600 hover:bg-orange-50 transition">
                Volver al menú
            </a>
        </div>
    </header>

    {{-- Contenido de la página --}}
    <main>
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
