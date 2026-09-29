import { MapPinIcon, PackageOpenIcon } from 'lucide-react';
import { Pager } from '@/components/pager';
import { ProductCard, ProductGrid } from '@/components/product-card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { SiteLayout } from '@/layouts/site-layout';
import { formatDate, initials } from '@/lib/format';
import type { Paginated, ProductCard as Card, Seller } from '@/types';

type Props = { seller: Seller; products: Paginated<Card>; stats: { products: number; orders: number } };

export default function SellerShow({ seller, products, stats }: Props) {
    return (
        <SiteLayout title={seller.name}>
            <section className="border-b bg-card">
                <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-8 sm:flex-row sm:items-center sm:px-6 sm:py-10">
                    <Avatar className="size-20 rounded-lg sm:size-24">
                        {seller.avatar && <AvatarImage src={seller.avatar} alt="" className="rounded-lg" />}
                        <AvatarFallback className="rounded-lg bg-primary text-2xl font-bold text-primary-foreground">{initials(seller.name)}</AvatarFallback>
                    </Avatar>
                    <div className="flex min-w-0 flex-1 flex-col gap-2">
                        <h1 className="text-2xl font-bold sm:text-3xl">{seller.name}</h1>
                        <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                            <MapPinIcon className="size-4 shrink-0" />
                            {seller.address || `${seller.village}, ${seller.area}`}
                        </p>
                        {seller.description && <p className="max-w-prose text-sm text-foreground/80">{seller.description}</p>}
                    </div>
                    <dl className="grid grid-cols-3 gap-6 text-sm sm:w-auto sm:gap-8">
                        <div className="flex flex-col gap-0.5">
                            <dt className="text-muted-foreground">Produk</dt>
                            <dd className="font-heading text-xl font-bold tabular">{stats.products}</dd>
                        </div>
                        <div className="flex flex-col gap-0.5">
                            <dt className="text-muted-foreground">Pesanan lunas</dt>
                            <dd className="font-heading text-xl font-bold tabular">{stats.orders}</dd>
                        </div>
                        <div className="flex flex-col gap-0.5">
                            <dt className="text-muted-foreground">Bergabung</dt>
                            <dd className="font-heading text-xl font-bold">{formatDate(seller.joined).split(' ').slice(1).join(' ')}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6 sm:py-10">
                <h2 className="text-xl font-bold">{seller.type}</h2>
                {products.data.length ? (
                    <>
                        <ProductGrid>
                            {products.data.map((p) => (
                                <ProductCard key={p.id} product={p} showSeller={false} />
                            ))}
                        </ProductGrid>
                        <Pager paginator={products} label="produk" />
                    </>
                ) : (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <PackageOpenIcon />
                            </EmptyMedia>
                            <EmptyTitle>Belum ada produk</EmptyTitle>
                            <EmptyDescription>Toko ini belum menambahkan produk.</EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                )}
            </div>
        </SiteLayout>
    );
}
