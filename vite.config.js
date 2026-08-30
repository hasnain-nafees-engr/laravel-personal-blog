import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Self-hosted via Bunny Fonts: no Google CDN request from the
            // visitor's browser, so no third-party tracking and no CSP hole.
            fonts: [
                bunny('Instrument Sans', { weights: [400, 500, 600, 700] }),
                bunny('Newsreader', { weights: [400, 500, 600], styles: ['normal', 'italic'] }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        // why: the dev server runs inside a container, so it must bind to
        // 0.0.0.0; the browser reaches HMR through the published port on
        // localhost.
        host: '0.0.0.0',
        port: Number(process.env.VITE_PORT ?? 5173),
        strictPort: true,
        hmr: {
            host: 'localhost',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
