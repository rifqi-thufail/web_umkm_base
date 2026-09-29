import { Link, usePage } from '@inertiajs/react';
import { BrandMark } from '@/components/brand';
import { BaseGlyph } from '@/components/chain-proof';
import type { SharedProps } from '@/types';

export function SiteFooter() {
    const { chain } = usePage<SharedProps>().props;

    return (
        <footer className="mt-16 border-t bg-card">
            <div className="mx-auto grid max-w-6xl grid-cols-1 gap-8 px-4 py-10 sm:grid-cols-2 sm:px-6 lg:grid-cols-[2fr_1fr_1fr]">
                <div className="flex max-w-sm flex-col gap-3">
                    <div className="flex items-center gap-2">
                        <BrandMark />
                        <span className="font-heading font-extrabold">AMPUH</span>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Aplikasi Merah Putih Universitas Hasanuddin. Marketplace untuk UMKM dan koperasi, dengan setiap pembayaran lunas dicatat di
                        jaringan Base.
                    </p>
                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <BaseGlyph /> Jaringan aktif: {chain.name} (chain {chain.chainId})
                    </p>
                </div>
                <div className="flex flex-col gap-2 text-sm">
                    <p className="font-medium">Belanja</p>
                    <Link href={route('home')} className="text-muted-foreground hover:text-foreground">Katalog</Link>
                    <Link href={route('orders.index')} className="text-muted-foreground hover:text-foreground">Riwayat pesanan</Link>
                    <Link href={route('blockchain.verify')} className="text-muted-foreground hover:text-foreground">Verifikasi kuitansi</Link>
                </div>
                <div className="flex flex-col gap-2 text-sm">
                    <p className="font-medium">Penjual</p>
                    <Link href={route('seller.register')} className="text-muted-foreground hover:text-foreground">Daftarkan koperasi</Link>
                    <Link href={route('seller.login')} className="text-muted-foreground hover:text-foreground">Masuk penjual</Link>
                    <Link href={route('help.index')} className="text-muted-foreground hover:text-foreground">Bantuan</Link>
                </div>
            </div>
            <div className="border-t">
                <p className="mx-auto max-w-6xl px-4 py-4 text-xs text-muted-foreground sm:px-6">© {new Date().getFullYear()} AMPUH, Universitas Hasanuddin</p>
            </div>
        </footer>
    );
}
