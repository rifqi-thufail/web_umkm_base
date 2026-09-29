import { Link, useForm } from '@inertiajs/react';
import { TextField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { AuthLayout } from '@/layouts/auth-layout';

export default function SellerForgotPassword({ no_hp }: { no_hp?: string | null }) {
    const form = useForm({ no_hp: no_hp ?? '' });

    return (
        <AuthLayout title="Lupa password penjual" heading="Atur ulang password toko" description="Kami kirim kode OTP 6 digit lewat SMS ke nomor HP toko.">
            <form className="flex flex-col gap-6" onSubmit={(e) => { e.preventDefault(); form.post(route('seller.password.email')); }}>
                <TextField id="no_hp" label="Nomor HP toko" type="tel" autoComplete="tel" autoFocus required value={form.data.no_hp} onChange={(v) => form.setData('no_hp', v)} error={form.errors.no_hp} />
                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing && <Spinner data-icon="inline-start" />}
                    Kirim kode OTP
                </Button>
            </form>
            <Link href={route('seller.login')} className="text-sm text-muted-foreground underline-offset-4 hover:underline">
                Kembali ke halaman masuk
            </Link>
        </AuthLayout>
    );
}
