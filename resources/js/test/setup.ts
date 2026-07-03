import '@testing-library/jest-dom/vitest';
import { afterEach } from 'vitest';
import { cleanup } from '@testing-library/react';

// Unmount anything mounted during a test so component trees don't leak between
// tests (happy-dom keeps one document for the whole file).
afterEach(() => {
    cleanup();
});
