import { Link } from '@inertiajs/react';
import { ChevronRightIcon, ShoppingBagIcon, StoreIcon } from 'lucide-react';
import { AuthLayout } from '@/layouts/auth-layout';

const OPTIONS = [
    { href: 'user.login', icon: ShoppingBagIcon, title: 'Saya pembeli', body: 'Masuk dengan email untuk belanja dan melihat kuitansi.' },
    { href: 'seller.login', icon: StoreIcon, title: 'Saya penjual', body: 'Masuk dengan nomor HP koperasi untuk mengelola toko.' },
] as const;

export default function Choose() {
    return (
        <AuthLayout title="Masuk" heading="Masuk ke AMPUH" description="Pembeli dan penjual punya akun terpisah.">
            <div className="flex flex-col gap-3">
                {OPTIONS.map(({ href, icon: Icon, title, body }) => (
                    <Link
                        key={href}
                        href={route(href)}
                        className="group flex items-center gap-4 rounded-lg bg-card p-4 ring-1 ring-border transition-colors hover:ring-primary/50 focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-accent text-accent-foreground">
                            <Icon className="size-5" />
                        </span>
                        <span className="flex min-w-0 flex-1 flex-col">
                            <span className="font-medium">{title}</span>
                            <span className="text-sm text-muted-foreground">{body}</span>
                        </span>
                        <ChevronRightIcon className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                    </Link>
                ))}
            </div>
            <p className="text-sm text-muted-foreground">
                Belum punya akun?{' '}
                <Link href={route('user.register')} className="font-medium text-foreground underline underline-offset-4">
                    Daftar sebagai pembeli
                </Link>{' '}
                atau{' '}
                <Link href={route('seller.register')} className="font-medium text-foreground underline underline-offset-4">
                    daftarkan koperasi
                </Link>
                .
            </p>
        </AuthLayout>
    );
}
