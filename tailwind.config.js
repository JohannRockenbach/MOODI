import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Paleta "Foodie" para App de Delivery
                'foodie-orange': {
                    50: '#FFF7ED',
                    100: '#FFEDD5',
                    200: '#FED7AA',
                    300: '#FDBA74',
                    400: '#FB923C',
                    500: '#F97316', // Principal - Naranja vibrante
                    600: '#EA580C',
                    700: '#C2410C',
                    800: '#9A3412',
                    900: '#7C2D12',
                },
                'foodie-mustard': {
                    50: '#FEFCE8',
                    100: '#FEF9C3',
                    200: '#FEF08A',
                    300: '#FDE047',
                    400: '#FACC15',
                    500: '#EAB308', // Secundario - Amarillo mostaza
                    600: '#CA8A04',
                    700: '#A16207',
                    800: '#854D0E',
                    900: '#713F12',
                },
                'foodie-red': {
                    50: '#FEF2F2',
                    100: '#FEE2E2',
                    200: '#FECACA',
                    300: '#FCA5A5',
                    400: '#F87171',
                    500: '#EF4444', // Secundario alternativo - Rojo suave
                    600: '#DC2626',
                    700: '#B91C1C',
                    800: '#991B1B',
                    900: '#7F1D1D',
                },
                'foodie-bg': '#F3F4F6',      // Fondo suave
                'foodie-text': '#1F2937',     // Texto principal
                'foodie-text-light': '#6B7280', // Texto secundario
            },
        },
    },

    plugins: [forms],
};
