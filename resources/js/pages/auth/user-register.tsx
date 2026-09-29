import { Link, useForm } from '@inertiajs/react';
import { TextField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { AuthLayout } from '@/layouts/auth-layout';

export default function UserRegister() {
    const form = useForm({ nama: '', email: '', no_hp: '', password: '', password_confirmation: '' });

    return (
        <AuthLayout title="Daftar" heading="Buat akun pembeli" description="Gratis. Alamat pengiriman bisa diisi nanti.">
            <form
                className="flex flex-col gap-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('register'), { onFinish: () => form.reset('password', 'password_confirmation') });
                }}
            >
                <FieldGroup>
                    <TextField id="nama" label="Nama lengkap" autoComplete="name" autoFocus required value={form.data.nama} onChange={(v) => form.setData('nama', v)} error={form.errors.nama} />
                    <TextField id="email" label="Email" type="email" autoComplete="email" required value={form.data.email} onChange={(v) => form.setData('email', v)} error={form.errors.email} />
                    <TextField
                        id="no_hp"
                        label="Nomor HP"
                        type="tel"
                        autoComplete="tel"
                        placeholder="08…"
                        value={form.data.no_hp}
                        onChange={(v) => form.setData('no_hp', v)}
                        error={form.errors.no_hp}
                        description="Opsional. Dipakai kurir untuk menghubungi Anda."
                    />
                    <TextField
                        id="password"
                        label="Password"
                        type="password"
                        autoComplete="new-password"
                        required
                        value={form.data.password}
                        onChange={(v) => form.setData('password', v)}
                        error={form.errors.password}
                        description="Minimal 8 karakter."
                    />
                    <TextField
                        id="password_confirmation"
                        label="Ulangi password"
                        type="password"
                        autoComplete="new-password"
                        required
                        value={form.data.password_confirmation}
                        onChange={(v) => form.setData('password_confirmation', v)}
                        error={form.errors.password_confirmation}
                    />
                </FieldGroup>
                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing && <Spinner data-icon="inline-start" />}
                    Buat akun
                </Button>
            </form>
            <p className="text-sm text-muted-foreground">
                Sudah punya akun?{' '}
                <Link href={route('user.login')} className="font-medium text-foreground underline underline-offset-4">
                    Masuk
                </Link>
            </p>
        </AuthLayout>
    );
}
