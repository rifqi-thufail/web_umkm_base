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
        const env = props.initialPage.props.appEnv as string | undefined;
        createRoot(el).render(
            <>
                {env === 'staging' && (
                    <div className="bg-amber-400 px-4 py-1 text-center text-xs font-medium text-amber-950">
                        STAGING: data uji, pembayaran sandbox. Jangan pakai data atau kartu asli.
                    </div>
                )}
                <App {...props} />
            </>,
        );
    },
    progress: { color: '#a3171f', delay: 150 },
});
