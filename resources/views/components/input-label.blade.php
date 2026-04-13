@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-semibold text-sm text-foodie-text']) }}>
    {{ $value ?? $slot }}
</label>
