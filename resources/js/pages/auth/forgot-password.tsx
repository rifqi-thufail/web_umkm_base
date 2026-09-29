import { Link, useForm } from '@inertiajs/react';
import { TextField } from '@/components/form-field';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { AuthLayout } from '@/layouts/auth-layout';

export default function ForgotPassword({ status }: { status?: string }) {
    const form = useForm({ email: '' });

    return (
        <AuthLayout title="Lupa password" heading="Atur ulang password" description="Kami kirim tautan untuk membuat password baru ke email Anda.">
            {status && (
                <Alert>
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            )}
            <form className="flex flex-col gap-6" onSubmit={(e) => { e.preventDefault(); form.post(route('password.email')); }}>
                <TextField id="email" label="Email" type="email" autoComplete="email" autoFocus required value={form.data.email} onChange={(v) => form.setData('email', v)} error={form.errors.email} />
                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing && <Spinner data-icon="inline-start" />}
                    Kirim tautan
                </Button>
            </form>
            <Link href={route('user.login')} className="text-sm text-muted-foreground underline-offset-4 hover:underline">
                Kembali ke halaman masuk
            </Link>
        </AuthLayout>
    );
}
