import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,jsx}',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Nunito Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: 'rgb(var(--color-brand-50) / <alpha-value>)',
                    100: 'rgb(var(--color-brand-100) / <alpha-value>)',
                    200: 'rgb(var(--color-brand-200) / <alpha-value>)',
                    300: 'rgb(var(--color-brand-300) / <alpha-value>)',
                    400: 'rgb(var(--color-brand-400) / <alpha-value>)',
                    500: 'rgb(var(--color-brand-500) / <alpha-value>)',
                    600: 'rgb(var(--color-brand-600) / <alpha-value>)',
                    700: 'rgb(var(--color-brand-700) / <alpha-value>)',
                    800: 'rgb(var(--color-brand-800) / <alpha-value>)',
                    900: 'rgb(var(--color-brand-900) / <alpha-value>)',
                    950: 'rgb(var(--color-brand-950) / <alpha-value>)',
                },
                ink: {
                    DEFAULT: 'rgb(var(--color-ink) / <alpha-value>)',
                    muted: 'rgb(var(--color-ink-muted) / <alpha-value>)',
                    subtle: 'rgb(var(--color-ink-subtle) / <alpha-value>)',
                },
                canvas: 'rgb(var(--color-canvas) / <alpha-value>)',
                surface: {
                    DEFAULT: 'rgb(var(--color-surface) / <alpha-value>)',
                    muted: 'rgb(var(--color-surface-muted) / <alpha-value>)',
                },
                line: {
                    DEFAULT: 'rgb(var(--color-line) / <alpha-value>)',
                    strong: 'rgb(var(--color-line-strong) / <alpha-value>)',
                },
                role: {
                    DEFAULT: 'rgb(var(--color-role) / <alpha-value>)',
                    text: 'rgb(var(--color-role-text) / <alpha-value>)',
                },
                action: {
                    edit: 'rgb(var(--color-action-edit) / <alpha-value>)',
                    delete: 'rgb(var(--color-negative) / <alpha-value>)',
                },
                positive: 'rgb(var(--color-positive) / <alpha-value>)',
                negative: 'rgb(var(--color-negative) / <alpha-value>)',
                caution: 'rgb(var(--color-caution) / <alpha-value>)',
            },
            boxShadow: {
                ambient: '0 1px 3px 0 rgba(15, 23, 42, 0.06), 0 1px 2px -1px rgba(15, 23, 42, 0.06)',
                floating: '0 10px 28px -12px rgba(15, 23, 42, 0.22)',
            },
            transitionTimingFunction: {
                productive: 'cubic-bezier(0.22, 1, 0.36, 1)',
            },
        },
    },

    plugins: [],
};
