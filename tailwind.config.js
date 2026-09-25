import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // Toggled manually (theme.ts adds/removes this on <html>) rather than
    // 'media', so a user's explicit choice can override the OS preference
    // it defaults to - see resources/css/app.css for how the app's existing
    // markup (built with plain gray/white utilities, not dark: variants)
    // gets its dark styling from this class rather than a per-file rewrite.
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
