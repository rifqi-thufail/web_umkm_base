import { Link, router, usePage } from '@inertiajs/react';
import { HeartIcon, ImageOffIcon, MapPinIcon } from 'lucide-react';
import { formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ProductCard as Product, SharedProps } from '@/types';

export function ProductImage({ src, alt, className }: { src: string | null; alt: string; className?: string }) {
    return (
        <div className={cn('relative aspect-square overflow-hidden bg-muted', className)}>
            {src ? (
                <img src={src} alt={alt} loading="lazy" decoding="async" className="size-full object-cover" />
            ) : (
                <div className="flex size-full items-center justify-center text-muted-foreground/60">
                    <ImageOffIcon className="size-6" aria-hidden />
                    <span className="sr-only">Belum ada foto</span>
                </div>
            )}
        </div>
    );
}

export function WishlistButton({ product, className }: { product: Pick<Product, 'id' | 'name' | 'wishlisted'>; className?: string }) {
    const { auth } = usePage<SharedProps>().props;

    const toggle = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (!auth.user) {
            router.visit(route('user.login'));
            return;
        }
        router.post(route('wishlist.toggle'), { product_id: product.id }, { preserveScroll: true, preserveState: true });
    };

    return (
        <button
            type="button"
            onClick={toggle}
            aria-pressed={product.wishlisted}
            aria-label={product.wishlisted ? `Hapus ${product.name} dari wishlist` : `Simpan ${product.name} ke wishlist`}
            className={cn(
                'inline-flex size-8 items-center justify-center rounded-full bg-card/90 text-foreground/70 ring-1 ring-foreground/10 backdrop-blur transition-colors hover:text-primary focus-visible:outline-2 focus-visible:outline-ring',
                product.wishlisted && 'text-primary',
                className,
            )}
        >
            <HeartIcon className={cn('size-4', product.wishlisted && 'fill-current')} />
        </button>
    );
}

export function ProductCard({ product, showSeller = true }: { product: Product; showSeller?: boolean }) {
    const soldOut = product.stock <= 0;

    return (
        <Link
            href={route('products.show', product.id)}
            className="group flex flex-col overflow-hidden rounded-lg bg-card ring-1 ring-border transition-shadow hover:ring-foreground/25 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            <div className="relative">
                <ProductImage src={product.image} alt={product.name} className={cn(soldOut && 'opacity-60')} />
                <WishlistButton product={product} className="absolute top-2 right-2" />
                {soldOut && (
                    <span className="absolute bottom-2 left-2 rounded-sm bg-foreground px-1.5 py-0.5 text-[11px] font-medium text-background">
                        Stok habis
                    </span>
                )}
            </div>
            <div className="flex flex-1 flex-col gap-1 p-3">
                <h3 className="line-clamp-2 font-sans text-sm leading-snug font-medium text-foreground">{product.name}</h3>
                <p className="font-heading text-base font-bold">{formatRupiah(product.price)}</p>
                {showSeller && product.seller && (
                    <p className="mt-auto flex min-w-0 items-center gap-1 pt-1 text-xs text-muted-foreground">
                        <MapPinIcon className="size-3 shrink-0" aria-hidden />
                        <span className="truncate">
                            {product.seller.name}, {product.seller.area}
                        </span>
                    </p>
                )}
            </div>
        </Link>
    );
}

export function ProductGrid({ children }: { children: React.ReactNode }) {
    return <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">{children}</div>;
}
