import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.tsx',
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    ssr: {
        // Bundle every dependency into bootstrap/ssr/ssr.js so the SSR
        // service runs from that one file, with no node_modules
        // (Dockerfile.ssr).
        noExternal: true,
    },
});
