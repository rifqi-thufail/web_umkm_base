import { Link } from '@inertiajs/react';
import { PackagePlusIcon } from 'lucide-react';
import { OrderRow } from '@/components/order-row';
import { ProductImage } from '@/components/product-card';
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { SellerLayout } from '@/layouts/seller-layout';
import { formatRupiah } from '@/lib/format';
import type { Order, ProductCard, Seller } from '@/types';

type Props = {
    seller: Seller;
    activation: { can_receive_payments: boolean; completed_fields: number; total_fields: number };
    stats: { products: number; orders: number; to_process: number; revenue_month: number; anchored: number };
    recentOrders: Order[];
    lowStock: ProductCard[];
};

export default function SellerDashboard({ seller, activation, stats, recentOrders, lowStock }: Props) {
    const month = new Intl.DateTimeFormat('id-ID', { month: 'long' }).format(new Date());

    return (
        <SellerLayout title="Ringkasan toko">
            <div className="mx-auto flex max-w-6xl flex-col gap-8 px-4 py-8 sm:px-6 sm:py-10">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-bold sm:text-3xl">{seller.name}</h1>
                        <p className="text-muted-foreground">
                            {seller.type}, {seller.area}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('seller.products.create')}>
                            <PackagePlusIcon data-icon="inline-start" />
                            Tambah produk
                        </Link>
                    </Button>
                </div>

                {!activation.can_receive_payments && (
                    <Alert>
                        <AlertTitle>Pembayaran belum aktif</AlertTitle>
                        <AlertDescription>
                            Produk sudah tampil di katalog, tetapi pembeli belum bisa membayar. Hubungkan akun Midtrans toko ({activation.completed_fields}/
                            {activation.total_fields} data terisi).
                        </AlertDescription>
                        <AlertAction>
                            <Button size="sm" variant="outline" asChild>
                                <Link href={route('seller.activation.index')}>Hubungkan</Link>
                            </Button>
                        </AlertAction>
                    </Alert>
                )}

                <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-border ring-1 ring-border lg:grid-cols-4">
                    {[
                        ['Perlu diproses', stats.to_process.toString(), stats.to_process > 0],
                        [`Pendapatan ${month}`, formatRupiah(stats.revenue_month), false],
                        ['Pesanan lunas', stats.orders.toString(), false],
                        ['Tercatat di Base', `${stats.anchored} dari ${stats.orders}`, false],
                    ].map(([label, value, hot]) => (
                        <div key={label as string} className="flex flex-col gap-1 bg-card p-4 sm:p-5">
                            <dt className="text-sm text-muted-foreground">{label}</dt>
                            <dd className={`font-heading text-2xl font-bold ${hot ? 'text-primary' : ''}`}>{value}</dd>
                        </div>
                    ))}
                </dl>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_20rem]">
                    <section className="flex flex-col gap-4">
                        <div className="flex items-baseline justify-between">
                            <h2 className="text-lg font-bold">Pesanan terbaru</h2>
                            <Button variant="link" size="sm" asChild>
                                <Link href={route('seller.orders.index')}>Semua pesanan</Link>
                            </Button>
                        </div>
                        {recentOrders.length ? (
                            <div className="divide-y overflow-hidden rounded-lg bg-card ring-1 ring-border">
                                {recentOrders.map((o) => (
                                    <OrderRow key={o.id} order={o} href={route('seller.orders.index')} />
                                ))}
                            </div>
                        ) : (
                            <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">Belum ada pesanan.</p>
                        )}
                    </section>

                    <section className="flex flex-col gap-4">
                        <h2 className="text-lg font-bold">Stok menipis</h2>
                        {lowStock.length ? (
                            <ul className="divide-y overflow-hidden rounded-lg bg-card ring-1 ring-border">
                                {lowStock.map((p) => (
                                    <li key={p.id}>
                                        <Link href={route('seller.products.edit', p.id)} className="flex items-center gap-3 px-4 py-3 hover:bg-muted/50">
                                            <ProductImage src={p.image} alt="" className="size-10 shrink-0 rounded-md" />
                                            <span className="min-w-0 flex-1 truncate text-sm">{p.name}</span>
                                            <Badge variant={p.stock === 0 ? 'destructive' : 'warning'}>{p.stock === 0 ? 'Habis' : `Sisa ${p.stock}`}</Badge>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">Semua stok aman.</p>
                        )}
                        <p className="text-sm text-muted-foreground">{stats.products} produk di katalog.</p>
                    </section>
                </div>
            </div>
        </SellerLayout>
    );
}
