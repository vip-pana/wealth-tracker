import '@testing-library/jest-dom/vitest';
import { afterEach, vi } from 'vitest';
import { cleanup } from '@testing-library/react';

// happy-dom has no layout engine, so its matchMedia answer for a width query
// depends on the version rather than on anything meaningful. Pin it to a
// non-match — components read it through useMediaQuery and so render their
// wide-screen branch, which is what the existing assertions expect. A test that
// wants the mobile branch overrides this per-file.
window.matchMedia = vi.fn((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    addListener: vi.fn(),
    removeListener: vi.fn(),
    dispatchEvent: vi.fn(),
})) as unknown as typeof window.matchMedia;

// Unmount anything mounted during a test so component trees don't leak between
// tests (happy-dom keeps one document for the whole file).
afterEach(() => {
    cleanup();
});
