import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Receipt } from '@/components/receipt';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Page, SiteLayout } from '@/layouts/site-layout';
import { formatRupiah } from '@/lib/format';
import type { Order } from '@/types';

declare global {
    interface Window {
        snap?: { pay: (token: string, options?: Record<string, (r: unknown) => void>) => void };
    }
}

type Props = { snapToken: string; clientKey: string; isProduction: boolean; orders: Order[]; total: number };

export default function Payment({ snapToken, clientKey, isProduction, orders, total }: Props) {
    const [ready, setReady] = useState(!!window.snap);
    const first = orders[0];

    useEffect(() => {
        if (window.snap) return;
        const s = document.createElement('script');
        s.src = isProduction ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';
        s.dataset.clientKey = clientKey;
        s.onload = () => setReady(true);
        document.body.appendChild(s);
    }, [clientKey, isProduction]);

    const done = () => (window.location.href = route('orders.show', first.id));
    // Closing the popup re-checks the payment: the server redirects to the order once it is paid.
    const pay = () => window.snap?.pay(snapToken, { onSuccess: done, onPending: done, onError: done, onClose: () => router.reload() });

    useEffect(() => {
        if (ready) pay();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ready]);

    const description =
        orders.length > 1
            ? `Satu pembayaran ${formatRupiah(total)} untuk ${orders.length} pesanan dari ${orders.length} penjual. Jendela Midtrans terbuka otomatis; kalau tertutup, tekan tombol di bawah.`
            : 'Jendela Midtrans terbuka otomatis. Kalau tertutup, tekan tombol di bawah.';

    return (
        <SiteLayout title="Bayar pesanan">
            <Page width="narrow" title="Selesaikan pembayaran" description={description}>
                <div className="flex flex-col gap-6">
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Button size="lg" onClick={pay} disabled={!ready}>
                            {!ready && <Spinner data-icon="inline-start" />}
                            Bayar {formatRupiah(total)}
                        </Button>
                        <Button size="lg" variant="outline" asChild>
                            <Link href={route('orders.show', first.id)}>Bayar nanti</Link>
                        </Button>
                    </div>
                    {orders.map((order) => (
                        <Receipt key={order.id} order={order} className="max-w-md" />
                    ))}
                </div>
            </Page>
        </SiteLayout>
    );
}
