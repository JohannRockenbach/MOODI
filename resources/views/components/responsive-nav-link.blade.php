@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-foodie-orange-500 text-start text-base font-semibold text-foodie-orange-600 bg-foodie-orange-50 focus:outline-none focus:text-foodie-orange-700 focus:bg-foodie-orange-100 focus:border-foodie-orange-700 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-foodie-text hover:text-foodie-orange-600 hover:bg-foodie-orange-50 hover:border-foodie-orange-300 focus:outline-none focus:text-foodie-orange-600 focus:bg-foodie-orange-50 focus:border-foodie-orange-300 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
