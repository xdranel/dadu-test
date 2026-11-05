import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    alpine: ['alpinejs'],
                },
            },
        },
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: false,
            },
        },
    },
    optimizeDeps: {
        include: ['alpinejs'],
    },
});
