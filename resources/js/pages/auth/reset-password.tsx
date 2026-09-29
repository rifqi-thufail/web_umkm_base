import { useForm } from '@inertiajs/react';
import { TextField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { AuthLayout } from '@/layouts/auth-layout';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const form = useForm({ token, email, password: '', password_confirmation: '' });

    return (
        <AuthLayout title="Password baru" heading="Buat password baru">
            <form
                className="flex flex-col gap-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('password.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
                }}
            >
                <FieldGroup>
                    <TextField id="email" label="Email" type="email" autoComplete="email" required value={form.data.email} onChange={(v) => form.setData('email', v)} error={form.errors.email} />
                    <TextField id="password" label="Password baru" type="password" autoComplete="new-password" autoFocus required value={form.data.password} onChange={(v) => form.setData('password', v)} error={form.errors.password} />
                    <TextField id="password_confirmation" label="Ulangi password" type="password" autoComplete="new-password" required value={form.data.password_confirmation} onChange={(v) => form.setData('password_confirmation', v)} error={form.errors.password_confirmation} />
                </FieldGroup>
                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing && <Spinner data-icon="inline-start" />}
                    Simpan password
                </Button>
            </form>
        </AuthLayout>
    );
}
