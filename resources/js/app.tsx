import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import type { ResolvedComponent } from '@inertiajs/react';
import '../css/app.css';

type PageModule = { default: ResolvedComponent };
const pages = import.meta.glob<PageModule>('./Pages/**/*.tsx');

createInertiaApp({
    title: (title) => `${title} — Wealth Tracker`,

    resolve: async (name) => {
        const page = pages[`./Pages/${name}.tsx`];
        if (!page) {
            throw new Error(`Page not found: ./Pages/${name}.tsx`);
        }
        return (await page()).default;
    },

    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },

    progress: {
        color: '#6366f1',
    },
});

// Registered only for the built app: under `vite dev` the service worker would
// sit in front of the HMR client and serve a stale shell.
if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        void navigator.serviceWorker.register('/sw.js');
    });
}
