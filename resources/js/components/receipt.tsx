import { CheckCircle2Icon, CircleAlertIcon, CircleDashedIcon } from 'lucide-react';
import { BaseGlyph, CopyHash, ExplorerLink } from '@/components/chain-proof';
import { ProductImage } from '@/components/product-card';
import { formatDate, formatRupiah, shortOrder } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ChainInfo, Order } from '@/types';

export type ChainCheck = {
    reachable: boolean;
    verified: boolean;
    data_hash: string;
    matches_stored_hash?: boolean;
    anchored_at?: number | null;
    error?: string;
};

function Row({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex items-baseline justify-between gap-4 py-1.5">
            <dt className="shrink-0 text-muted-foreground">{label}</dt>
            <dd className="min-w-0 text-right">{children}</dd>
        </div>
    );
}

export function CheckResult({ check }: { check: ChainCheck | null }) {
    if (!check) return null;
    if (!check.reachable)
        return (
            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                <CircleDashedIcon className="size-4" /> Node Base tidak bisa dihubungi, pemeriksaan ulang dilewati.
            </p>
        );
    return check.verified ? (
        <p className="flex items-center gap-2 text-sm font-medium text-success">
            <CheckCircle2Icon className="size-4" /> Cocok. Data pesanan sama dengan yang tersimpan di Base.
        </p>
    ) : (
        <p className="flex items-center gap-2 text-sm font-medium text-destructive">
            <CircleAlertIcon className="size-4" /> Tidak cocok. Data pesanan berbeda dari catatan di Base.
        </p>
    );
}

export function ChainSection({ chain, check }: { chain: ChainInfo; check?: ChainCheck | null }) {
    if (chain.status !== 'confirmed' || !chain.tx_hash) {
        return (
            <div className="flex items-start gap-2 text-sm text-muted-foreground">
                <BaseGlyph className="mt-0.5 size-4 opacity-50" />
                {chain.status === 'failed'
                    ? 'Pencatatan ke Base gagal. Tim kami akan mencobanya lagi.'
                    : 'Sidik jari akan dicatat di Base setelah pembayaran lunas.'}
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-3">
            <p className="flex items-center gap-2 text-sm font-medium">
                <BaseGlyph className="size-4" /> Bukti di jaringan Base
            </p>
            <dl className="text-sm">
                <Row label="Transaksi">
                    <CopyHash value={chain.tx_hash} head={10} tail={8} />
                </Row>
                <Row label="Blok">
                    <span className="font-mono text-xs tabular">{chain.block?.toLocaleString('id-ID')}</span>
                </Row>
                {chain.data_hash && (
                    <Row label="Sidik jari">
                        <CopyHash value={chain.data_hash} head={10} tail={8} />
                    </Row>
                )}
                <Row label="Dicatat">{formatDate(chain.anchored_at, true)}</Row>
            </dl>
            {check !== undefined && <CheckResult check={check} />}
            <ExplorerLink url={chain.explorer_url} />
        </div>
    );
}

/** A paper receipt: notched top edge, dashed rules, the Base proof at the foot. */
export function Receipt({ order, check, className }: { order: Order; check?: ChainCheck | null; className?: string }) {
    const items = order.items ?? [];

    return (
        <article
            className={cn(
                'receipt-edge flex flex-col rounded-b-lg bg-card pt-7 shadow-[0_1px_0_var(--border),0_16px_40px_-24px_oklch(0.2_0.004_270/0.4)]',
                className,
            )}
        >
            <header className="flex items-start justify-between gap-4 px-5 sm:px-7">
                <div className="flex flex-col gap-1">
                    <p className="text-sm text-muted-foreground">Kuitansi</p>
                    <h2 className="font-mono text-lg font-medium tracking-tight">#{shortOrder(order.number)}</h2>
                </div>
                <div className="text-right text-sm">
                    <p className="font-medium">{order.seller?.name}</p>
                    <p className="text-muted-foreground">{formatDate(order.created_at, true)}</p>
                </div>
            </header>

            <div className="mx-5 my-5 border-t border-dashed sm:mx-7" />

            <ul className="flex flex-col gap-3 px-5 sm:px-7">
                {items.map((item) => (
                    <li key={item.id} className="flex items-center gap-3">
                        <ProductImage src={item.image} alt="" className="size-11 shrink-0 rounded-md" />
                        <div className="flex min-w-0 flex-1 flex-col">
                            <span className="truncate text-sm font-medium">{item.name}</span>
                            <span className="text-xs text-muted-foreground tabular">
                                {item.quantity} × {formatRupiah(item.price)}
                            </span>
                        </div>
                        <span className="text-sm font-medium tabular">{formatRupiah(item.quantity * item.price)}</span>
                    </li>
                ))}
            </ul>

            <div className="mx-5 my-5 border-t border-dashed sm:mx-7" />

            <div className="flex items-baseline justify-between px-5 sm:px-7">
                <span className="text-sm text-muted-foreground">Total dibayar</span>
                <span className="font-heading text-2xl font-extrabold">{formatRupiah(order.total)}</span>
            </div>

            <div className="mt-6 rounded-b-lg border-t border-dashed bg-chain-muted/50 px-5 py-5 sm:px-7">
                <ChainSection chain={order.chain} check={check} />
            </div>
        </article>
    );
}
