import { Link } from '@inertiajs/react';
import { HeartIcon } from 'lucide-react';
import { Pager } from '@/components/pager';
import { ProductCard, ProductGrid } from '@/components/product-card';
import { Button } from '@/components/ui/button';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Page, SiteLayout } from '@/layouts/site-layout';
import type { Paginated, ProductCard as Card } from '@/types';

export default function Wishlist({ products }: { products: Paginated<Card> }) {
    return (
        <SiteLayout title="Wishlist">
            <Page title="Wishlist" description="Produk yang Anda simpan. Tekan ikon hati untuk menghapusnya.">
                {products.data.length ? (
                    <div className="flex flex-col gap-6">
                        <ProductGrid>
                            {products.data.map((p) => (
                                <ProductCard key={p.id} product={p} />
                            ))}
                        </ProductGrid>
                        <Pager paginator={products} label="produk" />
                    </div>
                ) : (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <HeartIcon />
                            </EmptyMedia>
                            <EmptyTitle>Wishlist kosong</EmptyTitle>
                            <EmptyDescription>Tekan ikon hati di produk mana pun untuk menyimpannya di sini.</EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button asChild>
                                <Link href={route('home')}>Lihat katalog</Link>
                            </Button>
                        </EmptyContent>
                    </Empty>
                )}
            </Page>
        </SiteLayout>
    );
}
