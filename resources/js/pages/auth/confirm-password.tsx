import { useForm } from '@inertiajs/react';
import { TextField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { AuthLayout } from '@/layouts/auth-layout';

export default function ConfirmPassword() {
    const form = useForm({ password: '' });

    return (
        <AuthLayout title="Konfirmasi password" heading="Konfirmasi password" description="Halaman ini berisi pengaturan penting. Masukkan password Anda untuk melanjutkan.">
            <form className="flex flex-col gap-6" onSubmit={(e) => { e.preventDefault(); form.post(route('password.confirm'), { onFinish: () => form.reset() }); }}>
                <TextField id="password" label="Password" type="password" autoComplete="current-password" autoFocus required value={form.data.password} onChange={(v) => form.setData('password', v)} error={form.errors.password} />
                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing && <Spinner data-icon="inline-start" />}
                    Lanjutkan
                </Button>
            </form>
        </AuthLayout>
    );
}
