<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center px-6 py-3 bg-white border border-foodie-orange-300 rounded-xl font-semibold text-sm text-foodie-text uppercase tracking-wide shadow-sm hover:bg-foodie-orange-50 hover:border-foodie-orange-400 focus:outline-none focus:ring-4 focus:ring-foodie-orange-200 disabled:opacity-25 transition-all ease-in-out duration-200']) }}>
    {{ $slot }}
</button>
