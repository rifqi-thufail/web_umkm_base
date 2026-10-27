import { Link, router, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { CheckIcon, ChevronLeftIcon } from 'lucide-react';
import { OrderStatusBadge } from '@/components/order-status';
import { Receipt } from '@/components/receipt';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { SiteLayout } from '@/layouts/site-layout';
import { formatDate, shortOrder } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Order } from '@/types';

const STEPS = [
    { key: 'paid', label: 'Dibayar' },
    { key: 'processing', label: 'Diproses penjual' },
    { key: 'shipped', label: 'Dikirim' },
    { key: 'completed', label: 'Selesai' },
];

function progress(order: Order) {
    if (order.payment_status !== 'paid') return -1;
    return { processing: 1, shipped: 2, completed: 3 }[order.status] ?? 0;
}

type Props = { order: Order; payUrl: string | null; siblings: { id: number; number: string }[] };

export default function OrderShow({ order, payUrl, siblings }: Props) {
    const step = progress(order);
    const complete = useForm({});
    const reorder = useForm({});

    // While the payment or the Base anchor is in flight, refresh quietly: the server asks the
    // gateway / chain on each visit, so no webhook or manual reload is needed.
    const waiting = (order.payment_status === 'unpaid' && !!payUrl) || (order.payment_status === 'paid' && order.chain.status === 'pending');
    useEffect(() => {
        if (!waiting) return;
        let tries = 0;
        const id = window.setInterval(() => {
            if (++tries > 40 || document.hidden) return;
            router.reload({ only: ['order', 'payUrl'] });
        }, 6000);
        return () => window.clearInterval(id);
    }, [waiting]);

    return (
        <SiteLayout title={`Pesanan #${shortOrder(order.number)}`}>
            <div className="mx-auto grid max-w-6xl grid-cols-1 gap-10 px-4 py-8 sm:px-6 sm:py-10 lg:grid-cols-[1fr_26rem]">
                <div className="flex flex-col gap-6">
                    <Button variant="ghost" size="sm" asChild className="self-start">
                        <Link href={route('orders.index')}>
                            <ChevronLeftIcon data-icon="inline-start" />
                            Riwayat pesanan
                        </Link>
                    </Button>
                    <div className="flex flex-col gap-2">
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-bold sm:text-3xl">Pesanan #{shortOrder(order.number)}</h1>
                            <OrderStatusBadge order={order} />
                        </div>
                        <p className="text-muted-foreground">
                            Dari {order.seller?.name}, dibuat {formatDate(order.created_at, true)}
                        </p>
                    </div>

                    {order.payment_status === 'unpaid' && (
                        <Alert>
                            <AlertTitle>Menunggu pembayaran</AlertTitle>
                            <AlertDescription className="flex flex-col items-start gap-3">
                                {payUrl
                                    ? 'Selesaikan pembayaran dalam 24 jam setelah pesanan dibuat. Kalau sudah membayar, status berubah dalam satu menit.'
                                    : 'Kami sedang memeriksa status pembayaran ke gateway. Muat ulang halaman ini sebentar lagi.'}
                                {payUrl && (
                                    <Button size="sm" asChild>
                                        <a href={payUrl}>Bayar sekarang</a>
                                    </Button>
                                )}
                            </AlertDescription>
                        </Alert>
                    )}
                    {(order.payment_status === 'expired' || order.payment_status === 'failed') && (
                        <Alert variant="destructive">
                            <AlertTitle>{order.payment_status === 'expired' ? 'Batas waktu pembayaran habis' : 'Pembayaran gagal'}</AlertTitle>
                            <AlertDescription className="flex flex-col items-start gap-3">
                                Pesanan ini dibatalkan dan tidak ada dana yang diambil.
                                <Button size="sm" variant="outline" disabled={reorder.processing} onClick={() => reorder.post(route('orders.reorder', order.id))}>
                                    {reorder.processing && <Spinner data-icon="inline-start" />}
                                    Pesan ulang
                                </Button>
                            </AlertDescription>
                        </Alert>
                    )}
                    {order.payment_status === 'paid' && order.status === 'shipped' && (
                        <Alert>
                            <AlertTitle>Pesanan sedang dikirim</AlertTitle>
                            <AlertDescription className="flex flex-col items-start gap-3">
                                Sudah sampai? Konfirmasi supaya penjual tahu pesanan selesai.
                                <Button size="sm" disabled={complete.processing} onClick={() => complete.post(route('orders.complete', order.id), { preserveScroll: true })}>
                                    {complete.processing && <Spinner data-icon="inline-start" />}
                                    Pesanan sudah diterima
                                </Button>
                            </AlertDescription>
                        </Alert>
                    )}
                    {siblings.length > 0 && (
                        <p className="text-sm text-muted-foreground">
                            Dibayar sekaligus dengan pesanan{' '}
                            {siblings.map((s, i) => (
                                <span key={s.id}>
                                    {i > 0 && ', '}
                                    <Link href={route('orders.show', s.id)} className="font-mono text-foreground underline underline-offset-4">
                                        #{shortOrder(s.number)}
                                    </Link>
                                </span>
                            ))}
                            .
                        </p>
                    )}

                    <ol className="grid grid-cols-4 gap-2" aria-label="Status pesanan">
                        {STEPS.map((s, i) => (
                            <li key={s.key} className="flex flex-col gap-2">
                                <span className={cn('h-1 rounded-full', i <= step ? 'bg-primary' : 'bg-border')} />
                                <span className={cn('flex items-start gap-1 text-xs sm:text-sm', i <= step ? 'font-medium' : 'text-muted-foreground')}>
                                    {i <= step && <CheckIcon className="mt-px size-3.5 shrink-0 text-primary sm:mt-[3px]" />}
                                    {s.label}
                                </span>
                            </li>
                        ))}
                    </ol>

                    <div className="flex flex-col gap-2 text-sm text-muted-foreground">
                        <p>
                            Kuitansi pesanan ini adalah salinan data yang di-hash ke jaringan Base. Siapa pun dengan hash transaksinya bisa memeriksa bahwa
                            harga dan jumlah barang tidak diubah.
                        </p>
                        {order.chain.tx_hash && (
                            <Button variant="outline" asChild className="self-start">
                                <Link href={route('blockchain.verify', { q: order.chain.tx_hash })}>Verifikasi sekarang</Link>
                            </Button>
                        )}
                    </div>
                </div>
                <Receipt order={order} />
            </div>
        </SiteLayout>
    );
}
