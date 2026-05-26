import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

// Frontend lives in ./frontend, Laravel lives in ../backend.
// Vite writes its manifest / hot file / SSR bundle into the backend so
// Laravel's @vite() and Inertia SSR runtime keep working unchanged.
export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            ssr: 'resources/js/ssr.js',
            publicDirectory: '../backend/public',
            buildDirectory: 'build',
            hotFile: '../backend/public/hot',
            ssrOutputDirectory: '../backend/bootstrap/ssr',
            refresh: [
                '../backend/app/**',
                '../backend/routes/**',
                '../backend/resources/views/**',
                '../backend/lang/**',
            ],
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
