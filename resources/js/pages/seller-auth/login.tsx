import { Link, useForm } from '@inertiajs/react';
import { TextField } from '@/components/form-field';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { AuthLayout, SELLER_ASIDE } from '@/layouts/auth-layout';

export default function SellerLogin({ status }: { status?: string }) {
    const form = useForm({ no_hp: '', password: '', remember: false });

    return (
        <AuthLayout title="Masuk penjual" heading="Masuk sebagai penjual" description="Gunakan nomor HP yang didaftarkan untuk toko." aside={SELLER_ASIDE}>
            {status && (
                <Alert>
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            )}
            <form
                className="flex flex-col gap-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('seller.login'), { onFinish: () => form.reset('password') });
                }}
            >
                <FieldGroup>
                    <TextField id="no_hp" label="Nomor HP" type="tel" inputMode="tel" autoComplete="tel" placeholder="08…" autoFocus required value={form.data.no_hp} onChange={(v) => form.setData('no_hp', v)} error={form.errors.no_hp} />
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
                            <Link href={route('seller.password.request')} className="text-sm text-muted-foreground underline-offset-4 hover:underline">
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
                Belum punya toko?{' '}
                <Link href={route('seller.register')} className="font-medium text-foreground underline underline-offset-4">
                    Daftarkan koperasi
                </Link>
            </p>
        </AuthLayout>
    );
}
