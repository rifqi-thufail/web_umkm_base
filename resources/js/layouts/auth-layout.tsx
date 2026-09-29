import { Head } from '@inertiajs/react';
import { Brand } from '@/components/brand';
import { FlashToaster } from '@/components/flash-toaster';

/**
 * Split screen on desktop: form on the left, a red panel with one plain statement on the right.
 * Mobile shows only the form.
 */
export function AuthLayout({
    title,
    heading,
    description,
    aside,
    children,
}: {
    title: string;
    heading: string;
    description?: React.ReactNode;
    aside?: { title: string; body: string };
    children: React.ReactNode;
}) {
    return (
        <>
            <Head title={title} />
            <div className="grid min-h-svh grid-cols-1 lg:grid-cols-[1fr_minmax(0,0.9fr)]">
                <div className="flex flex-col px-4 py-6 sm:px-10">
                    <Brand />
                    <div className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center gap-6 py-10">
                        <div className="flex flex-col gap-1.5">
                            <h1 className="text-2xl font-bold">{heading}</h1>
                            {description && <p className="text-sm text-muted-foreground">{description}</p>}
                        </div>
                        {children}
                    </div>
                </div>
                <aside className="relative hidden overflow-hidden bg-primary text-primary-foreground lg:flex lg:flex-col lg:justify-end lg:p-12">
                    {/* The white band of the flag, kept as a quiet stripe at the top of the panel. */}
                    <div aria-hidden className="absolute inset-x-0 top-0 h-1/2 bg-[linear-gradient(to_bottom,var(--card)_0,var(--card)_100%)] opacity-[0.06]" />
                    <div className="relative flex max-w-md flex-col gap-3">
                        <p className="font-heading text-3xl leading-tight font-bold">
                            {aside?.title ?? 'Produk koperasi, kuitansi yang bisa dicek siapa saja.'}
                        </p>
                        <p className="text-primary-foreground/80">
                            {aside?.body ??
                                'Setelah pembayaran lunas, sidik jari pesanan dicatat di jaringan Base. Pembeli dan penjual bisa memverifikasinya tanpa harus percaya pada kami.'}
                        </p>
                    </div>
                </aside>
            </div>
            <FlashToaster />
        </>
    );
}

export const SELLER_ASIDE = {
    title: 'Toko koperasi Anda, dengan catatan penjualan yang bisa dibuktikan.',
    body: 'Setiap pesanan lunas tercatat di jaringan Base. Anggota koperasi dan pembeli bisa melihat buktinya tanpa membuka pembukuan Anda.',
};
