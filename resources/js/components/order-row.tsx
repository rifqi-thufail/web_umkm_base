import { Link } from '@inertiajs/react';
import { ChevronRightIcon } from 'lucide-react';
import { BaseGlyph } from '@/components/chain-proof';
import { OrderStatusBadge } from '@/components/order-status';
import { ProductImage } from '@/components/product-card';
import { formatDate, formatRupiah, shortOrder } from '@/lib/format';
import type { Order } from '@/types';

/** One order as a list row: thumbnails, seller, date, total, status. */
export function OrderRow({ order, href }: { order: Order; href: string }) {
    const items = order.items ?? [];
    const count = items.reduce((n, i) => n + i.quantity, 0);

    return (
        <Link href={href} className="group flex items-center gap-4 px-4 py-4 transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none sm:px-5">
            <div className="flex shrink-0 -space-x-3">
                {items.slice(0, 2).map((item) => (
                    <ProductImage key={item.id} src={item.image} alt="" className="size-12 rounded-md ring-2 ring-card" />
                ))}
            </div>
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span className="font-mono text-xs font-medium">#{shortOrder(order.number)}</span>
                    <OrderStatusBadge order={order} />
                    {order.chain.status === 'confirmed' && <BaseGlyph aria-label="Tercatat di Base" />}
                </div>
                <p className="truncate text-sm">
                    {items[0]?.name}
                    {items.length > 1 && <span className="text-muted-foreground"> dan {items.length - 1} lainnya</span>}
                </p>
                <p className="truncate text-xs text-muted-foreground">
                    {order.seller?.name ?? order.buyer?.name}, {formatDate(order.created_at)}, {count} barang
                </p>
            </div>
            <div className="flex shrink-0 items-center gap-2">
                <span className="text-sm font-semibold tabular">{formatRupiah(order.total)}</span>
                <ChevronRightIcon className="hidden size-4 text-muted-foreground sm:block" />
            </div>
        </Link>
    );
}
