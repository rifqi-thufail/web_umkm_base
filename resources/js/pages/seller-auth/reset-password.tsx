import { Link, useForm } from '@inertiajs/react';
import { TextField } from '@/components/form-field';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { AuthLayout } from '@/layouts/auth-layout';

export default function SellerResetPassword({ no_hp, status }: { no_hp?: string | null; status?: string }) {
    const form = useForm({ no_hp: no_hp ?? '', otp: '', password: '', password_confirmation: '' });

    return (
        <AuthLayout title="Reset password penjual" heading="Masukkan kode OTP" description="Kode dikirim lewat SMS dan berlaku 10 menit.">
            {status && (
                <Alert>
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            )}
            <form
                className="flex flex-col gap-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('seller.password.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
                }}
            >
                <FieldGroup>
                    <TextField id="no_hp" label="Nomor HP toko" type="tel" required value={form.data.no_hp} onChange={(v) => form.setData('no_hp', v)} error={form.errors.no_hp} />
                    <Field data-invalid={!!form.errors.otp || undefined}>
                        <FieldLabel htmlFor="otp">Kode OTP</FieldLabel>
                        <InputOTP id="otp" maxLength={6} value={form.data.otp} onChange={(v) => form.setData('otp', v)} aria-invalid={!!form.errors.otp || undefined}>
                            <InputOTPGroup>
                                {Array.from({ length: 6 }, (_, i) => (
                                    <InputOTPSlot key={i} index={i} className="size-10 text-base" />
                                ))}
                            </InputOTPGroup>
                        </InputOTP>
                        {form.errors.otp ? (
                            <FieldError>{form.errors.otp}</FieldError>
                        ) : (
                            <FieldDescription>
                                Tidak menerima SMS?{' '}
                                <Link href={route('seller.password.request')} className="underline underline-offset-4">
                                    Kirim ulang
                                </Link>
                            </FieldDescription>
                        )}
                    </Field>
                    <TextField id="password" label="Password baru" type="password" autoComplete="new-password" required value={form.data.password} onChange={(v) => form.setData('password', v)} error={form.errors.password} />
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
