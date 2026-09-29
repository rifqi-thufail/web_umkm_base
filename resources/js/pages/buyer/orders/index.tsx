import { Link } from '@inertiajs/react';
import { PackageIcon } from 'lucide-react';
import { OrderRow } from '@/components/order-row';
import { Pager } from '@/components/pager';
import { Button } from '@/components/ui/button';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Page, SiteLayout } from '@/layouts/site-layout';
import type { Order, Paginated } from '@/types';

export default function OrdersIndex({ orders }: { orders: Paginated<Order> }) {
    return (
        <SiteLayout title="Riwayat pesanan">
            <Page title="Riwayat pesanan" description="Buka pesanan untuk melihat kuitansi dan bukti pencatatannya di Base." width="narrow">
                {orders.data.length ? (
                    <div className="flex flex-col gap-4">
                        <div className="divide-y overflow-hidden rounded-lg bg-card ring-1 ring-border">
                            {orders.data.map((o) => (
                                <OrderRow key={o.id} order={o} href={route('orders.show', o.id)} />
                            ))}
                        </div>
                        <Pager paginator={orders} label="pesanan" />
                    </div>
                ) : (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <PackageIcon />
                            </EmptyMedia>
                            <EmptyTitle>Belum ada pesanan</EmptyTitle>
                            <EmptyDescription>Setelah checkout, pesanan Anda muncul di sini.</EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button asChild>
                                <Link href={route('home')}>Mulai belanja</Link>
                            </Button>
                        </EmptyContent>
                    </Empty>
                )}
            </Page>
        </SiteLayout>
    );
}
