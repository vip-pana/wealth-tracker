import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    test: {
        include: ['resources/js/**/*.{test,spec}.{ts,tsx}'],
        // happy-dom gives component tests a DOM; pure-logic tests run fine in it
        // too. jest-dom matchers (toBeInTheDocument, …) load via the setup file.
        environment: 'happy-dom',
        globals: true,
        setupFiles: ['resources/js/test/setup.ts'],
    },
});
