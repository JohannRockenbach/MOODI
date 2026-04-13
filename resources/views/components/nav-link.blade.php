@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-foodie-orange-500 text-sm font-semibold leading-5 text-foodie-orange-600 focus:outline-none focus:border-foodie-orange-700 transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-foodie-text-light hover:text-foodie-orange-600 hover:border-foodie-orange-300 focus:outline-none focus:text-foodie-orange-600 focus:border-foodie-orange-300 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
