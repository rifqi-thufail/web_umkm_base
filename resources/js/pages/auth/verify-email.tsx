import { router, useForm } from '@inertiajs/react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { AuthLayout } from '@/layouts/auth-layout';

export default function VerifyEmail({ status }: { status?: string }) {
    const form = useForm({});

    return (
        <AuthLayout title="Verifikasi email" heading="Cek email Anda" description="Kami mengirim tautan verifikasi saat Anda mendaftar. Klik tautan itu untuk mengaktifkan akun.">
            {status === 'verification-link-sent' && (
                <Alert>
                    <AlertDescription>Tautan baru sudah dikirim ke email Anda.</AlertDescription>
                </Alert>
            )}
            <div className="flex flex-col gap-3 sm:flex-row">
                <Button size="lg" disabled={form.processing} onClick={() => form.post(route('verification.send'))}>
                    {form.processing && <Spinner data-icon="inline-start" />}
                    Kirim ulang tautan
                </Button>
                <Button size="lg" variant="ghost" onClick={() => router.post(route('logout'))}>
                    Keluar
                </Button>
            </div>
        </AuthLayout>
    );
}
