import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    server: {
        // Bind to all interfaces so the Vite dev server is reachable from
        // outside the Docker container (browser connects via localhost:5173).
        host: '0.0.0.0',
        origin: 'http://localhost:5173',
        cors: true,
    },
    plugins: [
        tailwindcss(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
