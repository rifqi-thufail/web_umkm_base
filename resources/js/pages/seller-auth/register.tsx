import { Link, useForm } from '@inertiajs/react';
import { TextField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { FieldGroup, FieldLegend, FieldSet } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { AuthLayout, SELLER_ASIDE } from '@/layouts/auth-layout';

export default function SellerRegister() {
    const form = useForm({
        nama_koperasi: '',
        jenis_usaha: '',
        kecamatan: '',
        desa_kelurahan: '',
        no_hp: '',
        email: '',
        password: '',
        password_confirmation: '',
    });
    const set = (key: keyof typeof form.data) => (v: string) => form.setData(key, v);

    return (
        <AuthLayout title="Daftar penjual" heading="Daftarkan koperasi atau usaha" description="Anda bisa langsung menambahkan produk. Pembayaran diaktifkan setelah akun Midtrans terhubung." aside={SELLER_ASIDE}>
            <form
                className="flex flex-col gap-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('seller.register'), { onFinish: () => form.reset('password', 'password_confirmation') });
                }}
            >
                <FieldSet>
                    <FieldLegend>Usaha</FieldLegend>
                    <FieldGroup>
                        <TextField id="nama_koperasi" label="Nama koperasi atau usaha" autoFocus required value={form.data.nama_koperasi} onChange={set('nama_koperasi')} error={form.errors.nama_koperasi} />
                        <TextField id="jenis_usaha" label="Jenis usaha" placeholder="Contoh: kopi dan hasil kebun" required value={form.data.jenis_usaha} onChange={set('jenis_usaha')} error={form.errors.jenis_usaha} />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField id="kecamatan" label="Kecamatan" required value={form.data.kecamatan} onChange={set('kecamatan')} error={form.errors.kecamatan} />
                            <TextField id="desa_kelurahan" label="Desa / kelurahan" required value={form.data.desa_kelurahan} onChange={set('desa_kelurahan')} error={form.errors.desa_kelurahan} />
                        </div>
                    </FieldGroup>
                </FieldSet>
                <FieldSet>
                    <FieldLegend>Akun</FieldLegend>
                    <FieldGroup>
                        <TextField id="no_hp" label="Nomor HP" type="tel" autoComplete="tel" required value={form.data.no_hp} onChange={set('no_hp')} error={form.errors.no_hp} description="Dipakai untuk masuk dan menerima kode OTP." />
                        <TextField id="email" label="Email" type="email" autoComplete="email" value={form.data.email} onChange={set('email')} error={form.errors.email} description="Opsional." />
                        <TextField id="password" label="Password" type="password" autoComplete="new-password" required value={form.data.password} onChange={set('password')} error={form.errors.password} />
                        <TextField id="password_confirmation" label="Ulangi password" type="password" autoComplete="new-password" required value={form.data.password_confirmation} onChange={set('password_confirmation')} error={form.errors.password_confirmation} />
                    </FieldGroup>
                </FieldSet>
                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing && <Spinner data-icon="inline-start" />}
                    Daftarkan toko
                </Button>
            </form>
            <p className="text-sm text-muted-foreground">
                Sudah terdaftar?{' '}
                <Link href={route('seller.login')} className="font-medium text-foreground underline underline-offset-4">
                    Masuk
                </Link>
            </p>
        </AuthLayout>
    );
}
