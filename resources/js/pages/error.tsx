import { Head, Link } from '@inertiajs/react';
import { Brand } from '@/components/brand';
import { Button } from '@/components/ui/button';

const COPY: Record<number, { title: string; body: string }> = {
    403: { title: 'Akses ditolak', body: 'Anda tidak punya izin untuk membuka halaman ini.' },
    404: { title: 'Halaman tidak ditemukan', body: 'Tautannya mungkin salah, atau produk dan pesanannya sudah tidak ada.' },
    429: { title: 'Terlalu banyak percobaan', body: 'Tunggu sebentar, lalu coba lagi.' },
    500: { title: 'Terjadi kesalahan', body: 'Ada masalah di server kami. Coba lagi beberapa saat lagi.' },
    503: { title: 'Sedang pemeliharaan', body: 'AMPUH sedang diperbarui dan akan kembali dalam beberapa menit.' },
};

/**
 * Rendered from the exception handler, sometimes before the shared props exist,
 * so it depends on nothing but its own `status`.
 */
export default function ErrorPage({ status }: { status: number }) {
    const copy = COPY[status] ?? COPY[500];

    return (
        <>
            <Head title={copy.title} />
            <div className="flex min-h-svh flex-col">
                <header className="border-b">
                    <div className="mx-auto flex h-14 max-w-6xl items-center px-4 sm:px-6">
                        <Brand />
                    </div>
                </header>
                <main className="mx-auto flex w-full max-w-xl flex-1 flex-col items-start justify-center gap-4 px-4 py-16 sm:px-6">
                    <p className="font-mono text-sm text-muted-foreground tabular">{status}</p>
                    <h1 className="text-2xl font-bold sm:text-3xl">{copy.title}</h1>
                    <p className="text-muted-foreground">{copy.body}</p>
                    <div className="mt-2 flex flex-wrap gap-3">
                        <Button asChild>
                            <Link href={route('home')}>Ke katalog</Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={route('help.index')}>Bantuan</Link>
                        </Button>
                    </div>
                </main>
            </div>
        </>
    );
}
