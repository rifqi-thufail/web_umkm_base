import { Head } from '@inertiajs/react';
import { FlashToaster } from '@/components/flash-toaster';
import { SiteFooter } from '@/components/site-footer';
import { SiteHeader } from '@/components/site-header';
import { TooltipProvider } from '@/components/ui/tooltip';

export function SiteLayout({ title, children }: { title?: string; children: React.ReactNode }) {
    return (
        <TooltipProvider delayDuration={200}>
            <Head title={title} />
            <div className="flex min-h-svh flex-col">
                <SiteHeader />
                <main className="flex-1">{children}</main>
                <SiteFooter />
            </div>
            <FlashToaster />
        </TooltipProvider>
    );
}

/** Standard page container + heading. */
export function Page({
    title,
    description,
    actions,
    children,
    width = 'default',
}: {
    title: string;
    description?: React.ReactNode;
    actions?: React.ReactNode;
    children: React.ReactNode;
    width?: 'default' | 'narrow';
}) {
    return (
        <div className={width === 'narrow' ? 'mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-10' : 'mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10'}>
            <div className="mb-6 flex flex-col gap-3 sm:mb-8 sm:flex-row sm:items-end sm:justify-between">
                <div className="flex min-w-0 flex-col gap-1">
                    <h1 className="text-2xl font-bold sm:text-3xl">{title}</h1>
                    {description && <p className="max-w-prose text-sm text-muted-foreground sm:text-base">{description}</p>}
                </div>
                {actions && <div className="flex shrink-0 gap-2">{actions}</div>}
            </div>
            {children}
        </div>
    );
}
