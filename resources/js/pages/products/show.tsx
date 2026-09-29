import { Link, useForm, usePage } from '@inertiajs/react';
import { MinusIcon, PlusIcon, ShieldCheckIcon, StoreIcon } from 'lucide-react';
import { useState } from 'react';
import { BaseGlyph } from '@/components/chain-proof';
import { ProductCard, ProductGrid, ProductImage, WishlistButton } from '@/components/product-card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from '@/components/ui/input-group';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { SiteLayout } from '@/layouts/site-layout';
import { formatRupiah, initials } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ProductCard as Card, ProductDetail, SharedProps } from '@/types';

function Gallery({ product }: { product: ProductDetail }) {
    const [active, setActive] = useState(0);
    const images = product.images;

    if (images.length === 0) return <ProductImage src={null} alt={product.name} className="rounded-lg" />;

    return (
        <div className="flex flex-col gap-3">
            <div className="overflow-hidden rounded-lg ring-1 ring-border">
                <ProductImage src={images[active]!.url} alt={product.name} />
            </div>
            {images.length > 1 && (
                <div className="grid grid-cols-5 gap-2">
                    {images.map((img, i) => (
                        <button
                            key={img.id}
                            type="button"
                            onClick={() => setActive(i)}
                            aria-label={`Foto ${i + 1}`}
                            aria-current={i === active}
                            className={cn('overflow-hidden rounded-md ring-1 ring-border', i === active && 'ring-2 ring-primary')}
                        >
                            <ProductImage src={img.url} alt="" />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

function BuyBox({ product }: { product: ProductDetail }) {
    const { auth } = usePage<SharedProps>().props;
    const form = useForm({ product_id: product.id, quantity: 1 });
    const max = Math.max(product.stock, 1);
    const soldOut = product.stock <= 0;

    const setQty = (q: number) => form.setData('quantity', Math.min(Math.max(1, q), max));

    if (auth.seller && !auth.user) {
        return <p className="text-sm text-muted-foreground">Anda masuk sebagai penjual. Masuk sebagai pembeli untuk membeli produk ini.</p>;
    }

    return (
        <form
            className="flex flex-col gap-3 sm:flex-row"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route('cart.store'), { preserveScroll: true });
            }}
        >
            <InputGroup className="h-10 w-full sm:w-36">
                <InputGroupAddon>
                    <InputGroupButton size="icon-xs" aria-label="Kurangi" onClick={() => setQty(form.data.quantity - 1)} disabled={soldOut}>
                        <MinusIcon />
                    </InputGroupButton>
                </InputGroupAddon>
                <InputGroupInput
                    aria-label="Jumlah"
                    inputMode="numeric"
                    className="text-center tabular"
                    value={form.data.quantity}
                    onChange={(e) => setQty(Number(e.target.value) || 1)}
                    disabled={soldOut}
                />
                <InputGroupAddon align="inline-end">
                    <InputGroupButton size="icon-xs" aria-label="Tambah" onClick={() => setQty(form.data.quantity + 1)} disabled={soldOut}>
                        <PlusIcon />
                    </InputGroupButton>
                </InputGroupAddon>
            </InputGroup>
            {auth.user ? (
                <Button type="submit" size="lg" className="h-10 flex-1" disabled={soldOut || form.processing}>
                    {form.processing && <Spinner data-icon="inline-start" />}
                    {soldOut ? 'Stok habis' : 'Tambah ke keranjang'}
                </Button>
            ) : (
                <Button size="lg" className="h-10 flex-1" asChild>
                    <Link href={route('user.login')}>Masuk untuk membeli</Link>
                </Button>
            )}
        </form>
    );
}

export default function ProductShow({ product, related }: { product: ProductDetail; related: Card[] }) {
    const seller = product.seller;

    return (
        <SiteLayout title={product.name}>
            <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-10">
                <Breadcrumb className="mb-6">
                    <BreadcrumbList>
                        <BreadcrumbItem>
                            <BreadcrumbLink asChild>
                                <Link href={route('home')}>Katalog</Link>
                            </BreadcrumbLink>
                        </BreadcrumbItem>
                        {product.category && (
                            <>
                                <BreadcrumbSeparator />
                                <BreadcrumbItem>{product.category}</BreadcrumbItem>
                            </>
                        )}
                        <BreadcrumbSeparator className="hidden sm:block" />
                        <BreadcrumbItem className="hidden min-w-0 sm:inline-flex">
                            <BreadcrumbPage className="truncate">{product.name}</BreadcrumbPage>
                        </BreadcrumbItem>
                    </BreadcrumbList>
                </Breadcrumb>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1.1fr_1fr] lg:gap-12">
                    <Gallery product={product} />

                    <div className="flex flex-col gap-6">
                        <div className="flex flex-col gap-3">
                            <div className="flex items-start justify-between gap-4">
                                <h1 className="text-2xl leading-tight font-bold sm:text-3xl">{product.name}</h1>
                                <WishlistButton product={product} className="mt-1 shrink-0" />
                            </div>
                            <p className="font-heading text-3xl font-extrabold">{formatRupiah(product.price)}</p>
                            <div className="flex flex-wrap items-center gap-2">
                                {product.category && <Badge variant="secondary">{product.category}</Badge>}
                                {product.stock > 0 ? (
                                    <Badge variant={product.stock < 10 ? 'warning' : 'outline'}>
                                        {product.stock < 10 ? `Sisa ${product.stock}` : `Stok ${product.stock}`}
                                    </Badge>
                                ) : (
                                    <Badge variant="destructive">Stok habis</Badge>
                                )}
                            </div>
                        </div>

                        <BuyBox product={product} />

                        <p className="flex items-start gap-2 rounded-md bg-chain-muted px-3 py-2.5 text-sm text-foreground/80">
                            <BaseGlyph className="mt-0.5 size-4" />
                            Setelah lunas, pesanan ini dicatat di jaringan Base. Anda akan mendapat kuitansi yang bisa diverifikasi.
                        </p>

                        {seller && (
                            <div className="flex items-center gap-3 rounded-lg bg-card p-4 ring-1 ring-border">
                                <Avatar className="size-11">
                                    {seller.avatar && <AvatarImage src={seller.avatar} alt="" />}
                                    <AvatarFallback>{initials(seller.name)}</AvatarFallback>
                                </Avatar>
                                <div className="flex min-w-0 flex-1 flex-col">
                                    <p className="truncate font-medium">{seller.name}</p>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {seller.type}, {seller.village}, {seller.area}
                                    </p>
                                </div>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={route('seller.profile.show', seller.id)}>
                                        <StoreIcon data-icon="inline-start" />
                                        Toko
                                    </Link>
                                </Button>
                            </div>
                        )}

                        <Separator />

                        <div className="flex flex-col gap-2">
                            <h2 className="text-base font-semibold">Tentang produk</h2>
                            <p className="max-w-prose text-sm leading-relaxed whitespace-pre-line text-muted-foreground">
                                {product.description || 'Penjual belum menambahkan deskripsi.'}
                            </p>
                        </div>

                        <p className="flex items-center gap-2 text-xs text-muted-foreground">
                            <ShieldCheckIcon className="size-4" /> Pembayaran diproses oleh Midtrans atau CoinPayments. AMPUH tidak menyimpan data kartu Anda.
                        </p>
                    </div>
                </div>

                {related.length > 0 && (
                    <section className="mt-14 flex flex-col gap-4">
                        <h2 className="text-xl font-bold">Lainnya dari {seller?.name}</h2>
                        <ProductGrid>
                            {related.map((p) => (
                                <ProductCard key={p.id} product={p} showSeller={false} />
                            ))}
                        </ProductGrid>
                    </section>
                )}
            </div>
        </SiteLayout>
    );
}
