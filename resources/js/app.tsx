import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

const pages = import.meta.glob('./pages/**/*.tsx');

createInertiaApp({
    title: (title) => (title ? `${title} · AMPUH` : 'AMPUH — Marketplace UMKM & Koperasi'),
    resolve: async (name) => {
        const page = pages[`./pages/${name}.tsx`];
        if (!page) throw new Error(`Page not found: ${name}`);
        return ((await page()) as { default: never }).default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#a3171f', delay: 150 },
});
