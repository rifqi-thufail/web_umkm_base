import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Receipt } from '@/components/receipt';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Page, SiteLayout } from '@/layouts/site-layout';
import type { Order } from '@/types';

declare global {
    interface Window {
        snap?: { pay: (token: string, options?: Record<string, (r: unknown) => void>) => void };
    }
}

type Props = { snapToken: string; clientKey: string; isProduction: boolean; order: Order };

export default function Payment({ snapToken, clientKey, isProduction, order }: Props) {
    const [ready, setReady] = useState(!!window.snap);

    useEffect(() => {
        if (window.snap) return;
        const s = document.createElement('script');
        s.src = isProduction ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';
        s.dataset.clientKey = clientKey;
        s.onload = () => setReady(true);
        document.body.appendChild(s);
    }, [clientKey, isProduction]);

    const done = (status: string) => () => (window.location.href = `${route('orders.show', order.id)}?status=${status}`);
    const pay = () => window.snap?.pay(snapToken, { onSuccess: done('success'), onPending: done('pending'), onError: done('error') });

    useEffect(() => {
        if (ready) pay();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ready]);

    return (
        <SiteLayout title="Bayar pesanan">
            <Page width="narrow" title="Selesaikan pembayaran" description="Jendela Midtrans terbuka otomatis. Kalau tertutup, tekan tombol di bawah.">
                <div className="flex flex-col gap-6">
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Button size="lg" onClick={pay} disabled={!ready}>
                            {!ready && <Spinner data-icon="inline-start" />}
                            Buka pembayaran
                        </Button>
                        <Button size="lg" variant="outline" asChild>
                            <Link href={route('orders.show', order.id)}>Bayar nanti</Link>
                        </Button>
                    </div>
                    <Receipt order={order} className="max-w-md" />
                </div>
            </Page>
        </SiteLayout>
    );
}
