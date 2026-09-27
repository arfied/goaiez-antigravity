import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/widget.js',
                'resources/js/chat.js',
                'resources/js/pixel.js',
                'resources/js/actuate.js',
            ],
            refresh: true,
            fonts: [
                bunny('Archivo', { weights: [600] }),
                bunny('Public Sans', { weights: [400, 500, 600] }),
                bunny('IBM Plex Mono', { weights: [400, 500] }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
