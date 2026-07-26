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
