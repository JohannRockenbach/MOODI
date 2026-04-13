<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-6 py-3 bg-foodie-orange-500 border border-transparent rounded-xl font-bold text-sm text-white uppercase tracking-wide hover:bg-foodie-orange-600 hover:scale-105 focus:bg-foodie-orange-600 active:bg-foodie-orange-700 focus:outline-none focus:ring-4 focus:ring-foodie-orange-300 shadow-lg hover:shadow-xl transition-all ease-in-out duration-200']) }}>
    {{ $slot }}
</button>
