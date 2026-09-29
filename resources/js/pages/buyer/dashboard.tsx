import { Link } from '@inertiajs/react';
import { PackageIcon } from 'lucide-react';
import { OrderRow } from '@/components/order-row';
import { ProductCard, ProductGrid } from '@/components/product-card';
import { Button } from '@/components/ui/button';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { SiteLayout } from '@/layouts/site-layout';
import { formatDate, formatRupiah } from '@/lib/format';
import type { Order, ProductCard as Card } from '@/types';

type Props = {
    profile: { name: string; email: string; phone: string | null; address: string | null; joined: string; verified: boolean };
    stats: { orders: number; active: number; spent: number; anchored: number };
    recentOrders: Order[];
    wishlist: Card[];
    recommended: Card[];
};

function Section({ title, action, children }: { title: string; action?: React.ReactNode; children: React.ReactNode }) {
    return (
        <section className="flex flex-col gap-4">
            <div className="flex items-baseline justify-between gap-4">
                <h2 className="text-lg font-bold">{title}</h2>
                {action}
            </div>
            {children}
        </section>
    );
}

export default function BuyerDashboard({ profile, stats, recentOrders, wishlist, recommended }: Props) {
    const firstName = profile.name.split(' ')[0];

    return (
        <SiteLayout title="Ringkasan">
            <div className="mx-auto flex max-w-6xl flex-col gap-10 px-4 py-8 sm:px-6 sm:py-10">
                <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-bold sm:text-3xl">Halo, {firstName}</h1>
                        <p className="text-muted-foreground">
                            Anggota sejak {formatDate(profile.joined)}
                            {!profile.address && (
                                <>
                                    .{' '}
                                    <Link href={route('profile.edit')} className="text-foreground underline underline-offset-4">
                                        Tambahkan alamat pengiriman
                                    </Link>
                                </>
                            )}
                        </p>
                    </div>
                    <dl className="grid grid-cols-2 gap-x-8 gap-y-4 sm:grid-cols-4">
                        {[
                            ['Pesanan', stats.orders.toString()],
                            ['Sedang berjalan', stats.active.toString()],
                            ['Total belanja', formatRupiah(stats.spent)],
                            ['Kuitansi di Base', stats.anchored.toString()],
                        ].map(([label, value]) => (
                            <div key={label} className="flex flex-col gap-0.5">
                                <dt className="text-sm text-muted-foreground">{label}</dt>
                                <dd className="font-heading text-xl font-bold">{value}</dd>
                            </div>
                        ))}
                    </dl>
                </div>

                <Section
                    title="Pesanan terakhir"
                    action={
                        recentOrders.length > 0 && (
                            <Button variant="link" size="sm" asChild>
                                <Link href={route('orders.index')}>Semua pesanan</Link>
                            </Button>
                        )
                    }
                >
                    {recentOrders.length ? (
                        <div className="divide-y overflow-hidden rounded-lg bg-card ring-1 ring-border">
                            {recentOrders.map((o) => (
                                <OrderRow key={o.id} order={o} href={route('orders.show', o.id)} />
                            ))}
                        </div>
                    ) : (
                        <Empty className="border">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <PackageIcon />
                                </EmptyMedia>
                                <EmptyTitle>Belum ada pesanan</EmptyTitle>
                                <EmptyDescription>Pesanan dan kuitansinya akan muncul di sini.</EmptyDescription>
                            </EmptyHeader>
                            <EmptyContent>
                                <Button asChild>
                                    <Link href={route('home')}>Lihat katalog</Link>
                                </Button>
                            </EmptyContent>
                        </Empty>
                    )}
                </Section>

                {wishlist.length > 0 && (
                    <Section
                        title="Wishlist"
                        action={
                            <Button variant="link" size="sm" asChild>
                                <Link href={route('wishlist.index')}>Lihat semua</Link>
                            </Button>
                        }
                    >
                        <ProductGrid>
                            {wishlist.map((p) => (
                                <ProductCard key={p.id} product={p} />
                            ))}
                        </ProductGrid>
                    </Section>
                )}

                {recommended.length > 0 && (
                    <Section title="Mungkin Anda suka">
                        <ProductGrid>
                            {recommended.map((p) => (
                                <ProductCard key={p.id} product={p} />
                            ))}
                        </ProductGrid>
                    </Section>
                )}
            </div>
        </SiteLayout>
    );
}
