import { Link, router } from '@inertiajs/react';
import { SearchIcon, SearchXIcon } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { BaseGlyph, CopyHash } from '@/components/chain-proof';
import { Pager } from '@/components/pager';
import { ProductCard, ProductGrid } from '@/components/product-card';
import { Button } from '@/components/ui/button';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from '@/components/ui/input-group';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { SiteLayout } from '@/layouts/site-layout';
import { formatRupiah, shortOrder, timeAgo } from '@/lib/format';
import type { Category, Paginated, ProductCard as Product } from '@/types';

type Proof = { number: string; total: number; tx_hash: string; block: number | null; anchored_at: string | null };

type Props = {
    products: Paginated<Product>;
    categories: Category[];
    filters: { search?: string; category?: string };
    recentProofs: Proof[];
    stats: { products: number; sellers: number; anchored: number };
};

function ReceiptRail({ proofs }: { proofs: Proof[] }) {
    return (
        <div className="receipt-edge relative rounded-b-lg bg-card pt-6 pb-4 shadow-[0_1px_0_var(--border),0_12px_32px_-18px_oklch(0.2_0.004_270/0.35)]">
            <div className="flex items-center justify-between gap-2 px-5 pb-3">
                <p className="text-sm font-medium">Kuitansi terbaru di Base</p>
                <BaseGlyph className="size-4" />
            </div>
            {proofs.length > 0 ? (
                <ul className="flex flex-col divide-y divide-dashed">
                    {proofs.map((p) => (
                        <li key={p.tx_hash} className="flex items-center justify-between gap-3 px-5 py-2.5">
                            <div className="flex min-w-0 flex-col gap-0.5">
                                <Link
                                    href={route('public.transactions.show', p.tx_hash)}
                                    className="font-mono text-xs font-medium hover:underline"
                                >
                                    #{shortOrder(p.number)}
                                </Link>
                                <span className="text-xs text-muted-foreground">
                                    blok {p.block?.toLocaleString('id-ID')}, {timeAgo(p.anchored_at)}
                                </span>
                            </div>
                            <span className="text-sm font-semibold tabular">{formatRupiah(p.total)}</span>
                        </li>
                    ))}
                </ul>
            ) : (
                <ol className="flex flex-col gap-3 px-5 text-sm text-muted-foreground">
                    <li><span className="font-medium text-foreground">1. Bayar</span> lewat Midtrans atau kripto.</li>
                    <li><span className="font-medium text-foreground">2. Sidik jari</span> pesanan dihitung dari isi pesanan.</li>
                    <li><span className="font-medium text-foreground">3. Dicatat di Base</span>, lalu bisa dicek siapa saja.</li>
                </ol>
            )}
            <div className="mt-2 border-t border-dashed px-5 pt-3">
                <Link href={route('public.transactions.index')} className="text-sm font-medium text-chain hover:underline">
                    Buka buku transaksi publik
                </Link>
            </div>
        </div>
    );
}

export default function Home({ products, categories, filters, recentProofs, stats }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const category = filters.category ?? 'all';

    const apply = (next: { search?: string; category?: string }) => {
        const params = { search: next.search ?? search, category: (next.category ?? category) === 'all' ? undefined : next.category ?? category };
        router.get(route('home'), Object.fromEntries(Object.entries(params).filter(([, v]) => v)), {
            preserveState: true,
            preserveScroll: true,
            only: ['products', 'filters'],
        });
    };

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        apply({ search });
        document.getElementById('katalog')?.scrollIntoView({ behavior: 'smooth' });
    };

    return (
        <SiteLayout>
            <section className="border-b">
                <div className="mx-auto grid max-w-6xl grid-cols-1 gap-10 px-4 py-10 sm:px-6 sm:py-14 lg:grid-cols-[1.35fr_1fr] lg:items-center lg:py-16">
                    <div className="flex flex-col gap-5">
                        <h1 className="text-[2.1rem] leading-[1.05] font-extrabold sm:text-5xl lg:text-[3.4rem]">
                            Produk koperasi, dengan kuitansi yang tidak bisa diubah.
                        </h1>
                        <p className="max-w-[52ch] text-base text-muted-foreground sm:text-lg">
                            Beli langsung dari UMKM dan koperasi di sekitar Makassar. Setelah Anda membayar, sidik jari pesanan dicatat di jaringan
                            Base supaya siapa pun bisa memeriksanya.
                        </p>
                        <form onSubmit={onSubmit} role="search" className="max-w-lg">
                            <InputGroup className="h-11 bg-card">
                                <InputGroupAddon>
                                    <SearchIcon />
                                </InputGroupAddon>
                                <InputGroupInput
                                    aria-label="Cari produk"
                                    placeholder="Cari kopi, madu, tenun…"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                                <InputGroupAddon align="inline-end">
                                    <InputGroupButton type="submit" variant="default" size="sm">
                                        Cari
                                    </InputGroupButton>
                                </InputGroupAddon>
                            </InputGroup>
                        </form>
                        <p className="text-sm text-muted-foreground tabular">
                            {stats.products.toLocaleString('id-ID')} produk dari {stats.sellers.toLocaleString('id-ID')} penjual,{' '}
                            {stats.anchored.toLocaleString('id-ID')} transaksi tercatat
                        </p>
                    </div>
                    <ReceiptRail proofs={recentProofs} />
                </div>
            </section>

            <section id="katalog" className="mx-auto flex max-w-6xl scroll-mt-16 flex-col gap-6 px-4 py-10 sm:px-6">
                <div className="flex flex-col gap-4">
                    <div className="flex items-baseline justify-between gap-4">
                        <h2 className="text-xl font-bold sm:text-2xl">
                            {filters.search ? `Hasil untuk “${filters.search}”` : 'Katalog'}
                        </h2>
                        {filters.search && (
                            <Button variant="link" size="sm" onClick={() => { setSearch(''); apply({ search: '' }); }}>
                                Hapus pencarian
                            </Button>
                        )}
                    </div>
                    <div className="-mx-4 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0">
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            size="sm"
                            value={category}
                            onValueChange={(v) => v && apply({ category: v })}
                            aria-label="Kategori"
                            className="w-max"
                        >
                            <ToggleGroupItem value="all">Semua</ToggleGroupItem>
                            {categories.map((c) => (
                                <ToggleGroupItem key={c.id} value={c.slug}>
                                    {c.name}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                    </div>
                </div>

                {products.data.length > 0 ? (
                    <>
                        <ProductGrid>
                            {products.data.map((p) => (
                                <ProductCard key={p.id} product={p} />
                            ))}
                        </ProductGrid>
                        <Pager paginator={products} label="produk" />
                    </>
                ) : (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <SearchXIcon />
                            </EmptyMedia>
                            <EmptyTitle>Tidak ada produk yang cocok</EmptyTitle>
                            <EmptyDescription>Coba kata lain atau pilih kategori “Semua”.</EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button variant="outline" onClick={() => { setSearch(''); apply({ search: '', category: 'all' }); }}>
                                Tampilkan semua produk
                            </Button>
                        </EmptyContent>
                    </Empty>
                )}
            </section>
        </SiteLayout>
    );
}
