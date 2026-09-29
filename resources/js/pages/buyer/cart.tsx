import { Link, router } from '@inertiajs/react';
import { ShoppingBagIcon, Trash2Icon } from 'lucide-react';
import { ProductImage } from '@/components/product-card';
import { QtyStepper } from '@/components/qty-stepper';
import { Button } from '@/components/ui/button';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Separator } from '@/components/ui/separator';
import { Page, SiteLayout } from '@/layouts/site-layout';
import { formatRupiah } from '@/lib/format';
import { type CartItem, groupBySeller } from '@/lib/cart';

export default function Cart({ items }: { items: CartItem[] }) {
    const total = items.reduce((n, i) => n + i.quantity * i.product.price, 0);
    const count = items.reduce((n, i) => n + i.quantity, 0);
    const groups = groupBySeller(items);

    if (!items.length) {
        return (
            <SiteLayout title="Keranjang">
                <Page title="Keranjang" width="narrow">
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <ShoppingBagIcon />
                            </EmptyMedia>
                            <EmptyTitle>Keranjang masih kosong</EmptyTitle>
                            <EmptyDescription>Produk yang Anda tambahkan akan muncul di sini.</EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button asChild>
                                <Link href={route('home')}>Lihat katalog</Link>
                            </Button>
                        </EmptyContent>
                    </Empty>
                </Page>
            </SiteLayout>
        );
    }

    return (
        <SiteLayout title="Keranjang">
            <Page title="Keranjang" description={`${count} barang dari ${groups.length} penjual. Setiap penjual menjadi pesanan dan kuitansi terpisah.`}>
                <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_20rem] lg:items-start">
                    <div className="flex flex-col gap-6">
                        {groups.map(([seller, group]) => (
                            <section key={seller} className="overflow-hidden rounded-lg bg-card ring-1 ring-border">
                                <h2 className="border-b px-4 py-3 text-sm font-semibold sm:px-5">{seller}</h2>
                                <ul className="divide-y">
                                    {group.map((item) => (
                                        <li key={item.id} className="flex gap-4 px-4 py-4 sm:px-5">
                                            <Link href={route('products.show', item.product.id)} className="shrink-0">
                                                <ProductImage src={item.product.image} alt={item.product.name} className="size-20 rounded-md" />
                                            </Link>
                                            <div className="flex min-w-0 flex-1 flex-col gap-2">
                                                <div className="flex items-start justify-between gap-3">
                                                    <Link href={route('products.show', item.product.id)} className="line-clamp-2 text-sm font-medium hover:underline">
                                                        {item.product.name}
                                                    </Link>
                                                    <span className="shrink-0 text-sm font-semibold tabular">{formatRupiah(item.quantity * item.product.price)}</span>
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatRupiah(item.product.price)} per barang, stok {item.product.stock}
                                                </p>
                                                <div className="mt-auto flex items-center justify-between gap-3">
                                                    <QtyStepper
                                                        value={item.quantity}
                                                        max={item.product.stock}
                                                        onChange={(q) => router.patch(route('cart.update', item.id), { quantity: q }, { preserveScroll: true })}
                                                    />
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-muted-foreground"
                                                        onClick={() => router.delete(route('cart.destroy', item.id), { preserveScroll: true })}
                                                    >
                                                        <Trash2Icon data-icon="inline-start" />
                                                        Hapus
                                                    </Button>
                                                </div>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>

                    <aside className="flex flex-col gap-4 rounded-lg bg-card p-5 ring-1 ring-border lg:sticky lg:top-20">
                        <h2 className="font-semibold">Ringkasan</h2>
                        <dl className="flex flex-col gap-2 text-sm">
                            {groups.map(([seller, group]) => (
                                <div key={seller} className="flex justify-between gap-3">
                                    <dt className="truncate text-muted-foreground">{seller}</dt>
                                    <dd className="tabular">{formatRupiah(group.reduce((n, i) => n + i.quantity * i.product.price, 0))}</dd>
                                </div>
                            ))}
                        </dl>
                        <Separator />
                        <div className="flex items-baseline justify-between">
                            <span className="text-sm">Total</span>
                            <span className="font-heading text-2xl font-extrabold">{formatRupiah(total)}</span>
                        </div>
                        <p className="text-xs text-muted-foreground">Ongkos kirim dikonfirmasi penjual setelah pesanan dibuat.</p>
                        <Button size="lg" asChild>
                            <Link href={route('orders.checkout')}>Lanjut ke pembayaran</Link>
                        </Button>
                    </aside>
                </div>
            </Page>
        </SiteLayout>
    );
}
