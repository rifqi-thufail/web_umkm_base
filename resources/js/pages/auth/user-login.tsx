import { Link, useForm } from '@inertiajs/react';
import { TextField } from '@/components/form-field';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { AuthLayout } from '@/layouts/auth-layout';

export default function UserLogin({ status }: { status?: string }) {
    const form = useForm({ email: '', password: '', remember: false });

    return (
        <AuthLayout title="Masuk pembeli" heading="Masuk sebagai pembeli" description="Gunakan email yang Anda pakai saat mendaftar.">
            {status && (
                <Alert>
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            )}
            <form
                className="flex flex-col gap-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('login'), { onFinish: () => form.reset('password') });
                }}
            >
                <FieldGroup>
                    <TextField id="email" label="Email" type="email" autoComplete="email" autoFocus required value={form.data.email} onChange={(v) => form.setData('email', v)} error={form.errors.email} />
                    <TextField
                        id="password"
                        label="Password"
                        type="password"
                        autoComplete="current-password"
                        required
                        value={form.data.password}
                        onChange={(v) => form.setData('password', v)}
                        error={form.errors.password}
                        labelAside={
                            <Link href={route('password.request')} className="text-sm text-muted-foreground underline-offset-4 hover:underline">
                                Lupa password?
                            </Link>
                        }
                    />
                    <Field orientation="horizontal">
                        <Checkbox id="remember" checked={form.data.remember} onCheckedChange={(v) => form.setData('remember', v === true)} />
                        <FieldLabel htmlFor="remember" className="font-normal">
                            Ingat saya di perangkat ini
                        </FieldLabel>
                    </Field>
                </FieldGroup>
                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing && <Spinner data-icon="inline-start" />}
                    Masuk
                </Button>
            </form>
            <p className="text-sm text-muted-foreground">
                Belum punya akun?{' '}
                <Link href={route('user.register')} className="font-medium text-foreground underline underline-offset-4">
                    Daftar
                </Link>
                . Penjual?{' '}
                <Link href={route('seller.login')} className="font-medium text-foreground underline underline-offset-4">
                    Masuk di sini
                </Link>
                .
            </p>
        </AuthLayout>
    );
}
