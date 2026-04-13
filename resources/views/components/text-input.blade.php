@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-foodie-orange-200 bg-white text-foodie-text focus:border-foodie-orange-500 focus:ring-foodie-orange-500 rounded-lg shadow-sm placeholder-foodie-text-light']) }}>
