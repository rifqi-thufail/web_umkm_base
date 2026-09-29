import { Badge } from '@/components/ui/badge';
import type { Order } from '@/types';

const VARIANT: Record<string, React.ComponentProps<typeof Badge>['variant']> = {
    pending: 'warning',
    processing: 'secondary',
    shipped: 'secondary',
    completed: 'success',
    cancelled: 'destructive',
};

export function OrderStatusBadge({ order }: { order: Pick<Order, 'status' | 'status_label' | 'payment_status'> }) {
    if (order.payment_status === 'failed') return <Badge variant="destructive">Pembayaran gagal</Badge>;
    return <Badge variant={VARIANT[order.status] ?? 'outline'}>{order.status_label}</Badge>;
}
