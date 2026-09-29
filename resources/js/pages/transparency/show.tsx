import { Link } from '@inertiajs/react';
import { ChevronLeftIcon } from 'lucide-react';
import { NetworkPanel } from '@/components/network-panel';
import { type ChainCheck, Receipt } from '@/components/receipt';
import { Button } from '@/components/ui/button';
import { SiteLayout } from '@/layouts/site-layout';
import { shortOrder } from '@/lib/format';
import type { Network, Order } from '@/types';

type Props = { transaction: Order; fingerprint: Record<string, unknown>; check: ChainCheck; network: Network };

export default function TransparencyShow({ transaction, fingerprint, check, network }: Props) {
    return (
        <SiteLayout title={`Kuitansi #${shortOrder(transaction.number)}`}>
            <div className="mx-auto grid max-w-6xl grid-cols-1 gap-10 px-4 py-8 sm:px-6 sm:py-10 lg:grid-cols-[minmax(0,26rem)_1fr]">
                <div className="flex flex-col gap-4">
                    <Button variant="ghost" size="sm" asChild className="self-start">
                        <Link href={route('public.transactions.index')}>
                            <ChevronLeftIcon data-icon="inline-start" />
                            Buku transaksi
                        </Link>
                    </Button>
                    <Receipt order={transaction} check={check} />
                </div>

                <div className="flex min-w-0 flex-col gap-6 lg:pt-12">
                    <div className="flex flex-col gap-2">
                        <h1 className="text-2xl font-bold">Cara memeriksa kuitansi ini sendiri</h1>
                        <p className="max-w-prose text-muted-foreground">
                            AMPUH menyimpan hash keccak256 dari data di bawah ke kontrak OrderRegistry. Kalau ada yang mengubah harga, jumlah, atau
                            barang setelah pembayaran, hash-nya tidak akan cocok lagi.
                        </p>
                    </div>

                    <NetworkPanel network={network} />

                    <div className="flex flex-col gap-2">
                        <h2 className="text-base font-semibold">Data yang di-hash</h2>
                        <pre className="overflow-x-auto rounded-lg bg-foreground p-4 font-mono text-xs leading-relaxed text-background">
                            {JSON.stringify(fingerprint, null, 2)}
                        </pre>
                        <p className="text-sm text-muted-foreground">
                            Hash dihitung dari JSON ringkas (tanpa spasi) dengan urutan kunci seperti di atas.
                        </p>
                    </div>

                    <div className="flex flex-col gap-2">
                        <h2 className="text-base font-semibold">Periksa lewat terminal</h2>
                        <pre className="overflow-x-auto rounded-lg bg-muted p-4 font-mono text-xs leading-relaxed">
                            {`cast call ${network.contract ?? '<kontrak>'} \\\n  "recordOf(bytes32)(uint64,uint64)" \\\n  ${check.data_hash} \\\n  --rpc-url <RPC Base>`}
                        </pre>
                        <p className="text-sm text-muted-foreground">Hasilnya adalah nomor pesanan internal dan waktu pencatatan (unix).</p>
                    </div>
                </div>
            </div>
        </SiteLayout>
    );
}
