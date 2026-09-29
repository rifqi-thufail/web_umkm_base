import { BaseGlyph, CopyHash } from '@/components/chain-proof';
import type { Network } from '@/types';

export function NetworkPanel({ network }: { network: Network }) {
    return (
        <dl className="grid gap-x-8 gap-y-3 rounded-lg bg-chain-muted/60 p-4 text-sm sm:grid-cols-3">
            <div className="flex flex-col gap-0.5">
                <dt className="text-muted-foreground">Jaringan</dt>
                <dd className="flex items-center gap-1.5 font-medium">
                    <BaseGlyph className="size-4" />
                    {network.name}
                </dd>
            </div>
            <div className="flex flex-col gap-0.5">
                <dt className="text-muted-foreground">Chain ID</dt>
                <dd className="font-mono text-xs leading-5">{network.chain_id}</dd>
            </div>
            <div className="flex min-w-0 flex-col gap-0.5">
                <dt className="text-muted-foreground">Kontrak OrderRegistry</dt>
                <dd>{network.contract ? <CopyHash value={network.contract} head={8} tail={6} /> : 'Belum dikonfigurasi'}</dd>
            </div>
        </dl>
    );
}
