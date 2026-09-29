import { useEffect } from 'react';
import { toast } from 'sonner';
import { Toaster } from '@/components/ui/sonner';
import type { SharedProps } from '@/types';

function Fire({ flash }: { flash: SharedProps['flash'] }) {
    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
        if (flash.warning) toast.warning(flash.warning);
        if (flash.info) toast.info(flash.info);
    }, [flash]);

    return null;
}

/** Loaded on demand by FlashToaster. Toaster must mount before Fire so it is subscribed when toasts are created. */
export default function FlashToasts({ flash }: { flash: SharedProps['flash'] }) {
    return (
        <>
            <Toaster position="top-center" richColors closeButton />
            <Fire flash={flash} />
        </>
    );
}
