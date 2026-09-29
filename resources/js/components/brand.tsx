import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

/** Merah-putih mark: the flag's two bands, drawn as a small tile. */
export function BrandMark({ className }: { className?: string }) {
    return (
        <span aria-hidden className={cn('inline-flex size-6 flex-col overflow-hidden rounded-[3px] ring-1 ring-foreground/15', className)}>
            <span className="flex-1 bg-primary" />
            <span className="flex-1 bg-card" />
        </span>
    );
}

export function Brand({ href = route('home'), suffix }: { href?: string; suffix?: string }) {
    return (
        <Link href={href} className="flex items-center gap-2 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring">
            <BrandMark />
            <span className="font-heading text-lg font-extrabold tracking-tight">AMPUH</span>
            {suffix && <span className="text-sm font-medium text-muted-foreground">{suffix}</span>}
        </Link>
    );
}
