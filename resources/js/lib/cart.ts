import type { ProductCard } from '@/types';

export type CartItem = { id: number; quantity: number; product: ProductCard };

export function groupBySeller(items: CartItem[]) {
    const groups = new Map<string, CartItem[]>();
    for (const item of items) {
        const key = item.product.seller?.name ?? 'Lainnya';
        groups.set(key, [...(groups.get(key) ?? []), item]);
    }
    return [...groups.entries()];
}
