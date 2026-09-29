import { Link } from '@inertiajs/react';
import { CheckIcon, ChevronLeftIcon } from 'lucide-react';
import { OrderStatusBadge } from '@/components/order-status';
import { Receipt } from '@/components/receipt';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
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

export default function OrderShow({ order, returnStatus }: { order: Order; returnStatus?: string | null }) {
    const step = progress(order);

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

                    {returnStatus === 'success' && order.payment_status !== 'paid' && (
                        <Alert>
                            <AlertTitle>Pembayaran diterima Midtrans</AlertTitle>
                            <AlertDescription>Status akan berubah setelah konfirmasi masuk, biasanya kurang dari satu menit.</AlertDescription>
                        </Alert>
                    )}
                    {order.payment_status === 'unpaid' && returnStatus !== 'success' && (
                        <Alert>
                            <AlertTitle>Menunggu pembayaran</AlertTitle>
                            <AlertDescription>Selesaikan pembayaran lewat instruksi Midtrans yang Anda terima.</AlertDescription>
                        </Alert>
                    )}

                    <ol className="grid grid-cols-4 gap-2" aria-label="Status pesanan">
                        {STEPS.map((s, i) => (
                            <li key={s.key} className="flex flex-col gap-2">
                                <span className={cn('h-1 rounded-full', i <= step ? 'bg-primary' : 'bg-border')} />
                                <span className={cn('flex items-center gap-1 text-xs sm:text-sm', i <= step ? 'font-medium' : 'text-muted-foreground')}>
                                    {i <= step && <CheckIcon className="size-3.5 shrink-0 text-primary" />}
                                    {s.label}
                                </span>
                            </li>
                        ))}
                    </ol>

                    <div className="flex flex-col gap-2 text-sm text-muted-foreground">
                        <p>
                            Kuitansi di samping adalah salinan data yang di-hash ke jaringan Base. Siapa pun dengan hash transaksinya bisa memeriksa bahwa
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
