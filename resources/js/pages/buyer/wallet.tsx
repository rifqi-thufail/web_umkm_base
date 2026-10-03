import { useForm, usePage } from '@inertiajs/react';
import { ArrowDownLeftIcon, ArrowUpRightIcon, CheckIcon, CopyIcon, WalletIcon } from 'lucide-react';
import { useState } from 'react';
import { TextField } from '@/components/form-field';
import { Pager } from '@/components/pager';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Spinner } from '@/components/ui/spinner';
import { Page, SiteLayout } from '@/layouts/site-layout';
import { formatDate, shortHash } from '@/lib/format';
import type { Paginated, SharedProps, Wallet, WalletTransfer } from '@/types';

const STATUS_VARIANT = { confirmed: 'success', pending: 'warning', failed: 'destructive' } as const;

function CopyAddress({ address }: { address: string }) {
    const [copied, setCopied] = useState(false);
    return (
        <Button
            variant="outline"
            size="sm"
            onClick={() => {
                navigator.clipboard.writeText(address).then(() => {
                    setCopied(true);
                    setTimeout(() => setCopied(false), 1500);
                });
            }}
        >
            {copied ? <CheckIcon /> : <CopyIcon />} {copied ? 'Tersalin' : 'Salin alamat'}
        </Button>
    );
}

function TransferRow({ t, explorer }: { t: WalletTransfer; explorer: string | null }) {
    const sent = t.direction === 'sent';
    return (
        <li className="flex items-center gap-4 px-4 py-3">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted">
                {sent ? <ArrowUpRightIcon className="size-4" /> : <ArrowDownLeftIcon className="size-4" />}
            </span>
            <div className="flex min-w-0 flex-1 flex-col">
                <span className="truncate font-medium">
                    {sent ? 'Ke' : 'Dari'} {t.counterparty.email ?? shortHash(t.counterparty.address)}
                </span>
                <span className="truncate text-sm text-muted-foreground">
                    {formatDate(t.created_at, true)}
                    {t.tx_hash && (
                        <>
                            {' · '}
                            {explorer ? (
                                <a href={`${explorer.replace(/\/$/, '')}/tx/${t.tx_hash}`} target="_blank" rel="noreferrer" className="underline underline-offset-4">
                                    {shortHash(t.tx_hash)}
                                </a>
                            ) : (
                                shortHash(t.tx_hash)
                            )}
                        </>
                    )}
                </span>
            </div>
            <div className="flex flex-col items-end gap-1">
                <span className="font-medium tabular">
                    {sent ? '−' : '+'}
                    {t.amount}
                </span>
                <Badge variant={STATUS_VARIANT[t.status]}>{t.status_label}</Badge>
            </div>
        </li>
    );
}

export default function WalletPage({ wallet, transfers }: { wallet: Wallet; transfers: Paginated<WalletTransfer> }) {
    const { chain } = usePage<SharedProps>().props;
    const form = useForm({ recipient: '', amount: '' });
    const ready = wallet.enabled && !!wallet.address;

    return (
        <SiteLayout title="Wallet">
            <Page title="Wallet" description={`Wallet MPC Anda di jaringan ${chain.name}. Kunci dibagi 2-dari-3 di node MPC, sehingga kunci privat utuh tidak pernah ada di satu tempat.`}>
                {!ready ? (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <WalletIcon />
                            </EmptyMedia>
                            <EmptyTitle>{wallet.enabled ? 'Wallet sedang disiapkan' : 'Wallet belum tersedia'}</EmptyTitle>
                            <EmptyDescription>
                                {wallet.enabled
                                    ? 'Wallet dibuat otomatis setelah registrasi. Muat ulang halaman ini dalam beberapa menit.'
                                    : 'Fitur wallet sedang tidak aktif.'}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <div className="flex flex-col gap-10">
                        <div className="grid gap-6 lg:grid-cols-2">
                            <section className="flex flex-col gap-4 rounded-lg bg-card p-6 ring-1 ring-border">
                                <div className="flex flex-col gap-1">
                                    <span className="text-sm text-muted-foreground">Saldo</span>
                                    <span className="font-heading text-3xl font-bold tabular">{wallet.balance?.display ?? '—'}</span>
                                </div>
                                <div className="flex flex-col gap-2">
                                    <span className="text-sm text-muted-foreground">Alamat</span>
                                    <code className="break-all rounded-md bg-muted px-3 py-2 text-sm">{wallet.address}</code>
                                    <div className="flex flex-wrap gap-2">
                                        <CopyAddress address={wallet.address!} />
                                        {chain.explorer && (
                                            <Button variant="link" size="sm" asChild>
                                                <a href={`${chain.explorer.replace(/\/$/, '')}/address/${wallet.address}`} target="_blank" rel="noreferrer">
                                                    Lihat di explorer
                                                </a>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </section>

                            <section className="flex flex-col gap-4 rounded-lg bg-card p-6 ring-1 ring-border">
                                <h2 className="font-semibold">Kirim ETH</h2>
                                <form
                                    className="flex flex-col gap-4"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        form.post(route('wallet.send'), { preserveScroll: true, onSuccess: () => form.reset() });
                                    }}
                                >
                                    <TextField
                                        id="recipient"
                                        label="Penerima"
                                        required
                                        placeholder="email@contoh.com atau 0x…"
                                        value={form.data.recipient}
                                        onChange={(v) => form.setData('recipient', v)}
                                        error={form.errors.recipient}
                                    />
                                    <TextField
                                        id="amount"
                                        label="Jumlah (ETH)"
                                        type="number"
                                        required
                                        placeholder="0.001"
                                        value={form.data.amount}
                                        onChange={(v) => form.setData('amount', v)}
                                        error={form.errors.amount}
                                    />
                                    <Button type="submit" disabled={form.processing}>
                                        {form.processing && <Spinner />} Kirim
                                    </Button>
                                </form>
                            </section>
                        </div>

                        <section className="flex flex-col gap-4">
                            <h2 className="text-lg font-bold">Riwayat transfer</h2>
                            {transfers.data.length ? (
                                <>
                                    <ul className="divide-y overflow-hidden rounded-lg bg-card ring-1 ring-border">
                                        {transfers.data.map((t) => (
                                            <TransferRow key={t.id} t={t} explorer={chain.explorer} />
                                        ))}
                                    </ul>
                                    <Pager paginator={transfers} label="transfer" />
                                </>
                            ) : (
                                <p className="text-sm text-muted-foreground">Belum ada transfer.</p>
                            )}
                        </section>
                    </div>
                )}
            </Page>
        </SiteLayout>
    );
}
