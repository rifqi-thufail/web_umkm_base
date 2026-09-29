import { InboxIcon } from 'lucide-react';
import { BaseGlyph, CopyHash } from '@/components/chain-proof';
import { OrderStatusBadge } from '@/components/order-status';
import { Pager } from '@/components/pager';
import { ProductImage } from '@/components/product-card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { SellerLayout } from '@/layouts/seller-layout';
import { formatDate, formatRupiah, shortOrder } from '@/lib/format';
import type { Order, Paginated } from '@/types';

export default function SellerOrders({ orders }: { orders: Paginated<Order> }) {
    return (
        <SellerLayout title="Pesanan">
            <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6 sm:py-10">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold sm:text-3xl">Pesanan</h1>
                    <p className="text-muted-foreground">Hanya pesanan yang sudah dibayar. Kirim sesuai alamat pembeli.</p>
                </div>

                {orders.data.length ? (
                    <div className="flex flex-col gap-4">
                        {orders.data.map((o) => (
                            <article key={o.id} className="flex flex-col gap-4 rounded-lg bg-card p-4 ring-1 ring-border sm:p-5">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-mono text-sm font-medium">#{shortOrder(o.number)}</span>
                                        <OrderStatusBadge order={o} />
                                    </div>
                                    <span className="text-sm text-muted-foreground">{formatDate(o.created_at, true)}</span>
                                </div>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_16rem]">
                                    <ul className="flex flex-col gap-2">
                                        {o.items?.map((item) => (
                                            <li key={item.id} className="flex items-center gap-3">
                                                <ProductImage src={item.image} alt="" className="size-10 shrink-0 rounded-md" />
                                                <span className="min-w-0 flex-1 truncate text-sm">
                                                    {item.name} <span className="text-muted-foreground">× {item.quantity}</span>
                                                </span>
                                                <span className="text-sm tabular">{formatRupiah(item.price * item.quantity)}</span>
                                            </li>
                                        ))}
                                    </ul>
                                    <dl className="flex flex-col gap-1 border-t pt-3 text-sm sm:border-t-0 sm:border-l sm:pt-0 sm:pl-4">
                                        <dt className="text-muted-foreground">Pembeli</dt>
                                        <dd className="font-medium">{o.buyer?.name}</dd>
                                        <dd className="text-muted-foreground">{o.buyer?.phone}</dd>
                                        <dd className="text-muted-foreground">{o.buyer?.address}</dd>
                                    </dl>
                                </div>
                                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-dashed pt-3">
                                    {o.chain.tx_hash ? (
                                        <span className="flex items-center gap-2 text-sm text-muted-foreground">
                                            <BaseGlyph /> <CopyHash value={o.chain.tx_hash} />
                                        </span>
                                    ) : (
                                        <span className="text-sm text-muted-foreground">Belum tercatat di Base</span>
                                    )}
                                    <span className="font-heading text-lg font-bold">{formatRupiah(o.total)}</span>
                                </div>
                            </article>
                        ))}
                        <Pager paginator={orders} label="pesanan" />
                    </div>
                ) : (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <InboxIcon />
                            </EmptyMedia>
                            <EmptyTitle>Belum ada pesanan lunas</EmptyTitle>
                            <EmptyDescription>Pesanan muncul di sini setelah pembeli menyelesaikan pembayaran.</EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                )}
            </div>
        </SellerLayout>
    );
}
