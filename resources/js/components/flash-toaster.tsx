import { usePage } from '@inertiajs/react';
import { lazy, Suspense, useEffect, useState } from 'react';
import type { SharedProps } from '@/types';

// sonner is ~22 kB gzipped and only needed once a flash message exists, so keep it off the initial bundle.
const FlashToasts = lazy(() => import('@/components/flash-toasts'));

export function FlashToaster() {
    const { flash } = usePage<SharedProps>().props;
    const hasFlash = Boolean(flash.success || flash.error || flash.warning || flash.info);
    const [loaded, setLoaded] = useState(hasFlash);

    useEffect(() => {
        if (hasFlash) setLoaded(true);
    }, [hasFlash]);

    if (!loaded) return null;

    return (
        <Suspense fallback={null}>
            <FlashToasts flash={flash} />
        </Suspense>
    );
}
