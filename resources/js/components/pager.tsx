import { Link } from '@inertiajs/react';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

export function Pager({ paginator, label = 'item' }: { paginator: Paginated<unknown>; label?: string }) {
    const { meta, links } = paginator;
    if (meta.last_page <= 1) return null;

    // Show first, last, and a window around the current page.
    const pages = links.pages.filter(
        (p) => p.page === 1 || p.page === meta.last_page || Math.abs(p.page - meta.current_page) <= 1,
    );

    return (
        <nav aria-label="Halaman" className="flex flex-col items-center justify-between gap-3 sm:flex-row">
            <p className="text-sm text-muted-foreground tabular">
                {meta.from}–{meta.to} dari {meta.total} {label}
            </p>
            <div className="flex items-center gap-1">
                <Button variant="ghost" size="icon" asChild={!!links.prev} disabled={!links.prev} aria-label="Halaman sebelumnya">
                    {links.prev ? (
                        <Link href={links.prev} preserveScroll={false}>
                            <ChevronLeftIcon />
                        </Link>
                    ) : (
                        <ChevronLeftIcon />
                    )}
                </Button>
                {pages.map((p, i) => (
                    <span key={p.page} className="flex items-center gap-1">
                        {i > 0 && p.page - pages[i - 1]!.page > 1 && <span className="px-1 text-muted-foreground">…</span>}
                        <Button variant={p.page === meta.current_page ? 'outline' : 'ghost'} size="icon" asChild>
                            <Link href={p.url} aria-current={p.page === meta.current_page ? 'page' : undefined} className="tabular">
                                {p.page}
                            </Link>
                        </Button>
                    </span>
                ))}
                <Button variant="ghost" size="icon" asChild={!!links.next} disabled={!links.next} aria-label="Halaman berikutnya">
                    {links.next ? (
                        <Link href={links.next}>
                            <ChevronRightIcon />
                        </Link>
                    ) : (
                        <ChevronRightIcon />
                    )}
                </Button>
            </div>
        </nav>
    );
}
