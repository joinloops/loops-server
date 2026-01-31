import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from "@tailwindcss/vite";
import { resolve } from 'path'
import { ensureDir, copy } from 'fs-extra'

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/sass/next.css',
            ],
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
    ],
    resolve: {
        alias: {
            '~': '/resources/js',
            '@': '/resources/js',
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
    optimizeDeps: {
        exclude: ['@ffmpeg/ffmpeg', '@ffmpeg/util']
    },
    build: {
        minify: 'esbuild',
        chunkSizeWarningLimit: 1024,
        rollupOptions: {
            output: {
                manualChunks: {  // Chunk splitting for better caching
                    'vendor': ['vue', 'vue-router', 'pinia'],
                    'ui': ['@vueuse/core', '@headlessui/vue', '@heroicons/vue'],
                },
            },
        },
    },
    define: {
        __VUE_PROD_DEVTOOLS__: false,  // Disable Vue devtools in production
    },
});
