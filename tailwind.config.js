import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Chỉ khai báo font thật sự được nạp trong app.blade.php. Trước đây
                // ở đây ghi Inter/Manrope nhưng không nơi nào nạp hai font đó, nên
                // toàn bộ giao diện rơi về Segoe UI của hệ điều hành.
                sans: ['Open Sans', 'Segoe UI', ...defaultTheme.fontFamily.sans],
                display: ['Open Sans', 'Segoe UI', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                ink: {
                    950: '#17201f',
                    900: '#20302d',
                    800: '#2c403c',
                },
                brand: {
                    50: '#f0faf7',
                    100: '#d9f3eb',
                    200: '#b6e6d8',
                    300: '#85d2bd',
                    400: '#50b79d',
                    500: '#319b82',
                    600: '#247d69',
                    700: '#206456',
                    800: '#1e5147',
                    900: '#1c443c',
                },
                sand: {
                    50: '#fbfaf7',
                    100: '#f5f1e9',
                    200: '#ebe3d6',
                },
            },
            boxShadow: {
                panel: '0 1px 2px rgba(23, 32, 31, 0.04), 0 12px 32px rgba(23, 32, 31, 0.06)',
                float: '0 18px 48px rgba(23, 32, 31, 0.14)',
            },
        },
    },

    plugins: [forms],
};
