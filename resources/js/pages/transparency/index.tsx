import { Link, router } from '@inertiajs/react';
import { ArrowUpRightIcon, ReceiptIcon } from 'lucide-react';
import { ChainStatusBadge, CopyHash } from '@/components/chain-proof';
import { NetworkPanel } from '@/components/network-panel';
import { Pager } from '@/components/pager';
import { Button } from '@/components/ui/button';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Page, SiteLayout } from '@/layouts/site-layout';
import { formatDate, formatRupiah, shortOrder } from '@/lib/format';
import type { ChainInfo, Network, Paginated } from '@/types';
import { useState } from 'react';

type Tx = { number: string; total: number; items: number; seller: string | null; chain: ChainInfo };

type Props = {
    transactions: Paginated<Tx>;
    stats: { total_transactions: number; total_amount: number; today_transactions: number };
    network: Network;
    filters: { date_from?: string; date_to?: string };
};

export default function TransparencyIndex({ transactions, stats, network, filters }: Props) {
    const [range, setRange] = useState({ date_from: filters.date_from ?? '', date_to: filters.date_to ?? '' });

    return (
        <SiteLayout title="Buku transaksi publik">
            <Page
                title="Buku transaksi publik"
                description="Setiap pesanan yang lunas punya sidik jari yang disimpan di jaringan Base. Nama pembeli tidak ditampilkan."
            >
                <div className="flex flex-col gap-8">
                    <dl className="grid grid-cols-3 gap-4 border-y py-5">
                        <div className="flex flex-col gap-1">
                            <dt className="text-xs text-muted-foreground sm:text-sm">Transaksi tercatat</dt>
                            <dd className="font-heading text-2xl font-bold tabular sm:text-3xl">{stats.total_transactions.toLocaleString('id-ID')}</dd>
                        </div>
                        <div className="flex flex-col gap-1">
                            <dt className="text-xs text-muted-foreground sm:text-sm">Nilai total</dt>
                            <dd className="font-heading text-2xl font-bold sm:text-3xl">{formatRupiah(stats.total_amount)}</dd>
                        </div>
                        <div className="flex flex-col gap-1">
                            <dt className="text-xs text-muted-foreground sm:text-sm">Hari ini</dt>
                            <dd className="font-heading text-2xl font-bold tabular sm:text-3xl">{stats.today_transactions}</dd>
                        </div>
                    </dl>

                    <NetworkPanel network={network} />

                    <form
                        className="flex flex-col gap-3 sm:flex-row sm:items-end"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get(route('public.transactions.index'), Object.fromEntries(Object.entries(range).filter(([, v]) => v)), {
                                preserveState: true,
                            });
                        }}
                    >
                        <Field className="sm:w-44">
                            <FieldLabel htmlFor="date_from">Dari tanggal</FieldLabel>
                            <Input id="date_from" type="date" value={range.date_from} onChange={(e) => setRange({ ...range, date_from: e.target.value })} />
                        </Field>
                        <Field className="sm:w-44">
                            <FieldLabel htmlFor="date_to">Sampai tanggal</FieldLabel>
                            <Input id="date_to" type="date" value={range.date_to} onChange={(e) => setRange({ ...range, date_to: e.target.value })} />
                        </Field>
                        <Button type="submit" variant="outline">
                            Terapkan
                        </Button>
                    </form>

                    {transactions.data.length ? (
                        <div className="flex flex-col gap-4">
                            <div className="overflow-x-auto rounded-lg bg-card ring-1 ring-border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Pesanan</TableHead>
                                            <TableHead>Penjual</TableHead>
                                            <TableHead className="text-right">Total</TableHead>
                                            <TableHead className="text-right">Blok</TableHead>
                                            <TableHead>Transaksi Base</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="sr-only">Detail</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {transactions.data.map((t) => (
                                            <TableRow key={t.chain.tx_hash}>
                                                <TableCell>
                                                    <div className="flex flex-col">
                                                        <span className="font-mono text-xs font-medium">#{shortOrder(t.number)}</span>
                                                        <span className="text-xs text-muted-foreground">{formatDate(t.chain.anchored_at, true)}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="max-w-48 truncate">{t.seller}</TableCell>
                                                <TableCell className="text-right font-medium tabular">{formatRupiah(t.total)}</TableCell>
                                                <TableCell className="text-right font-mono text-xs tabular">{t.chain.block?.toLocaleString('id-ID')}</TableCell>
                                                <TableCell>{t.chain.tx_hash && <CopyHash value={t.chain.tx_hash} />}</TableCell>
                                                <TableCell>
                                                    <ChainStatusBadge status={t.chain.status} />
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button variant="ghost" size="icon-sm" asChild>
                                                        <Link href={route('public.transactions.show', t.chain.tx_hash!)} aria-label={`Kuitansi #${shortOrder(t.number)}`}>
                                                            <ArrowUpRightIcon />
                                                        </Link>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                            <Pager paginator={transactions} label="transaksi" />
                        </div>
                    ) : (
                        <Empty className="border">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <ReceiptIcon />
                                </EmptyMedia>
                                <EmptyTitle>Belum ada transaksi tercatat</EmptyTitle>
                                <EmptyDescription>Transaksi muncul di sini setelah pembayaran lunas dan sidik jarinya tersimpan di Base.</EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    )}
                </div>
            </Page>
        </SiteLayout>
    );
}
