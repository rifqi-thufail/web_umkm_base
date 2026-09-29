import { Link, useForm } from '@inertiajs/react';
import { SearchIcon } from 'lucide-react';
import { BaseGlyph } from '@/components/chain-proof';
import { NetworkPanel } from '@/components/network-panel';
import { type ChainCheck, ChainSection } from '@/components/receipt';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Page, SiteLayout } from '@/layouts/site-layout';
import { formatDate, formatRupiah, shortOrder } from '@/lib/format';
import type { ChainInfo, Network } from '@/types';

type Found = {
    found: true;
    order: { number: string; total: number; created_at: string; seller: string | null; items: number; chain: ChainInfo };
    check: ChainCheck | null;
};

type Props = { query: string | null; network: Network; result: Found | { found: false } | null };

export default function Verify({ query, network, result }: Props) {
    const form = useForm({ q: query ?? '' });

    return (
        <SiteLayout title="Verifikasi kuitansi">
            <Page
                width="narrow"
                title="Verifikasi kuitansi"
                description="Tempel hash transaksi Base, sidik jari pesanan, atau nomor pesanan lengkap. Kami menghitung ulang sidik jarinya dan membandingkan dengan kontrak di Base."
            >
                <div className="flex flex-col gap-8">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.get(route('blockchain.verify'), { preserveScroll: true });
                        }}
                        className="flex flex-col gap-3"
                    >
                        <Field data-invalid={!!form.errors.q || undefined}>
                            <FieldLabel htmlFor="q">Hash atau nomor pesanan</FieldLabel>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <Input
                                    id="q"
                                    className="h-10 font-mono text-sm"
                                    placeholder="0x… atau ORD-…"
                                    value={form.data.q}
                                    onChange={(e) => form.setData('q', e.target.value)}
                                    aria-invalid={!!form.errors.q || undefined}
                                    autoComplete="off"
                                    spellCheck={false}
                                />
                                <Button type="submit" size="lg" className="h-10" disabled={form.processing}>
                                    {form.processing ? <Spinner data-icon="inline-start" /> : <SearchIcon data-icon="inline-start" />}
                                    Periksa
                                </Button>
                            </div>
                            {form.errors.q ? <FieldError>{form.errors.q}</FieldError> : <FieldDescription>Hash ada di halaman kuitansi dan di email pesanan.</FieldDescription>}
                        </Field>
                    </form>

                    {result && !result.found && (
                        <Alert variant="destructive">
                            <AlertTitle>Tidak ditemukan</AlertTitle>
                            <AlertDescription>
                                Tidak ada pesanan AMPUH dengan hash atau nomor itu. Periksa apakah hash disalin lengkap, termasuk awalan 0x.
                            </AlertDescription>
                        </Alert>
                    )}

                    {result?.found && (
                        <section className="flex flex-col gap-4 rounded-lg bg-card p-5 ring-1 ring-border sm:p-6">
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <h2 className="font-mono text-lg">#{shortOrder(result.order.number)}</h2>
                                <span className="font-heading text-xl font-bold">{formatRupiah(result.order.total)}</span>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {result.order.seller}, {result.order.items} barang, dipesan {formatDate(result.order.created_at, true)}
                            </p>
                            <div className="border-t border-dashed pt-4">
                                <ChainSection chain={result.order.chain} check={result.check} />
                            </div>
                            {result.order.chain.tx_hash && (
                                <Button variant="outline" asChild className="self-start">
                                    <Link href={route('public.transactions.show', result.order.chain.tx_hash)}>Buka kuitansi lengkap</Link>
                                </Button>
                            )}
                        </section>
                    )}

                    <div className="flex flex-col gap-3">
                        <h2 className="flex items-center gap-2 text-base font-semibold">
                            <BaseGlyph className="size-4" /> Kontrak yang diperiksa
                        </h2>
                        <NetworkPanel network={network} />
                    </div>
                </div>
            </Page>
        </SiteLayout>
    );
}
