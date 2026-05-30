import { defineConfig } from 'vitest/config';
import path from 'path';

export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    test: {
        include: ['resources/js/**/*.{test,spec}.ts'],
        environment: 'node',
    },
});
