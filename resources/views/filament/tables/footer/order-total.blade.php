<div class="fi-ta-footer-ctn border-t bg-gray-50 dark:bg-gray-900 px-6 py-4">
    <div class="flex justify-end items-center gap-4">
        <span class="text-lg font-semibold text-gray-700 dark:text-gray-300">
            Total del Pedido:
        </span>
        <span class="text-2xl font-black text-green-600 dark:text-green-400">
            $ {{ number_format($total, 2, ',', '.') }}
        </span>
    </div>
</div>
