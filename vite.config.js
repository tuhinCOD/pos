import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    base: '/',
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        vue({
        template: {
            transformAssetUrls: {
            base: null,
            includeAbsolute: false,
            },
        },
        }),
        tailwindcss(),
    ],
    css: {
        preprocessorOptions: {
        scss: {
            api: "modern-compiler", // or "modern",
        },
        },
    },
    server: {
        host: true,
        port: 4000,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
        proxy: {
            "/api": {
                target: "http://localhost:8000",
                changeOrigin: true,
                secure: false,
                credentials: true,
            },
        },
    },
});
