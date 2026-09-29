import { CheckIcon, CopyIcon, ExternalLinkIcon } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { shortHash } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ChainInfo } from '@/types';

/** The Base logo: a blue disc with a flat bite. Only used next to on-chain facts. */
export function BaseGlyph({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 24 24" aria-hidden className={cn('size-3.5 shrink-0', className)}>
            <circle cx="12" cy="12" r="12" fill="var(--chain)" />
            <path d="M11.9 19.2a7.2 7.2 0 1 0-7.1-8.1h9.4v1.9H4.8a7.2 7.2 0 0 0 7.1 6.2Z" fill="var(--chain-foreground)" />
        </svg>
    );
}

export function CopyHash({ value, head = 6, tail = 4, className }: { value: string; head?: number; tail?: number; className?: string }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            /* clipboard blocked: the full value is still selectable in the tooltip */
        }
    };

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    onClick={copy}
                    className={cn(
                        'inline-flex items-center gap-1.5 rounded-sm font-mono text-xs text-foreground/80 hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring',
                        className,
                    )}
                >
                    {shortHash(value, head, tail)}
                    {copied ? <CheckIcon className="size-3 text-success" /> : <CopyIcon className="size-3 opacity-50" />}
                </button>
            </TooltipTrigger>
            <TooltipContent className="max-w-xs font-mono text-[11px] break-all select-all">{value}</TooltipContent>
        </Tooltip>
    );
}

export function ChainStatusBadge({ status }: { status: ChainInfo['status'] }) {
    if (status === 'confirmed')
        return (
            <Badge variant="chain">
                <BaseGlyph />
                Tercatat di Base
            </Badge>
        );
    if (status === 'failed') return <Badge variant="destructive">Gagal dicatat</Badge>;
    return <Badge variant="outline">Belum dicatat</Badge>;
}

export function ExplorerLink({ url }: { url: string | null }) {
    if (!url) return null;
    return (
        <Button variant="link" size="sm" asChild className="h-auto p-0 text-chain">
            <a href={url} target="_blank" rel="noreferrer">
                Lihat di BaseScan
                <ExternalLinkIcon data-icon="inline-end" />
            </a>
        </Button>
    );
}
