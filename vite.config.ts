import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'path';

const devHost = process.env.VITE_DEV_HOST ?? 'localhost';
// Host-side port published by docker-compose. Differs from the in-container
// port (5173) when 5173 is already taken on the host by another project.
const devClientPort = Number(process.env.VITE_DEV_CLIENT_PORT ?? 5173);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        cors: true,
        origin: `http://${devHost}:${devClientPort}`,
        hmr: {
            host: devHost,
            clientPort: devClientPort,
        },
    },
});
