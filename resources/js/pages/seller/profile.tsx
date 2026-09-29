import { useForm } from '@inertiajs/react';
import { TextField } from '@/components/form-field';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel, FieldLegend, FieldSet } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { SellerLayout } from '@/layouts/seller-layout';
import { initials } from '@/lib/format';
import type { Seller } from '@/types';

export default function SellerProfile({ seller }: { seller: Seller & { email: string | null; phone: string } }) {
    const form = useForm<Record<string, string | File | null>>({
        nama_koperasi: seller.name,
        jenis_usaha: seller.type,
        kecamatan: seller.area,
        desa_kelurahan: seller.village,
        alamat_toko: seller.address ?? '',
        deskripsi_toko: seller.description ?? '',
        no_hp: seller.phone,
        email: seller.email ?? '',
        foto_profil: null,
    });
    const text = (k: string) => (form.data[k] as string) ?? '';
    const set = (k: string) => (v: string) => form.setData(k, v);

    return (
        <SellerLayout title="Profil toko">
            <form
                className="mx-auto flex max-w-3xl flex-col gap-8 px-4 py-8 sm:px-6 sm:py-10"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('seller.profile.update'), { forceFormData: true, preserveScroll: true });
                }}
            >
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold sm:text-3xl">Profil toko</h1>
                    <p className="text-muted-foreground">Ditampilkan di halaman toko publik dan di setiap produk.</p>
                </div>

                <FieldSet>
                    <FieldLegend>Tampilan</FieldLegend>
                    <FieldGroup>
                        <Field data-invalid={!!form.errors.foto_profil || undefined}>
                            <FieldLabel htmlFor="foto_profil">Logo atau foto toko</FieldLabel>
                            <div className="flex items-center gap-4">
                                <Avatar className="size-16 rounded-lg">
                                    {seller.avatar && <AvatarImage src={seller.avatar} alt="" className="rounded-lg" />}
                                    <AvatarFallback className="rounded-lg bg-primary text-lg font-bold text-primary-foreground">{initials(seller.name)}</AvatarFallback>
                                </Avatar>
                                <Input id="foto_profil" type="file" accept="image/*" className="max-w-64" onChange={(e) => form.setData('foto_profil', e.target.files?.[0] ?? null)} />
                            </div>
                            {form.errors.foto_profil ? <FieldError>{form.errors.foto_profil}</FieldError> : <FieldDescription>Persegi, maksimal 2 MB.</FieldDescription>}
                        </Field>
                        <TextField id="nama_koperasi" label="Nama toko" required value={text('nama_koperasi')} onChange={set('nama_koperasi')} error={form.errors.nama_koperasi} />
                        <TextField id="jenis_usaha" label="Jenis usaha" required value={text('jenis_usaha')} onChange={set('jenis_usaha')} error={form.errors.jenis_usaha} />
                        <Field>
                            <FieldLabel htmlFor="deskripsi_toko">Tentang toko</FieldLabel>
                            <Textarea id="deskripsi_toko" rows={4} value={text('deskripsi_toko')} onChange={(e) => form.setData('deskripsi_toko', e.target.value)} placeholder="Siapa anggota koperasi, dari mana bahan baku, apa yang membuat produk Anda berbeda." />
                        </Field>
                    </FieldGroup>
                </FieldSet>

                <FieldSet>
                    <FieldLegend>Lokasi dan kontak</FieldLegend>
                    <FieldGroup>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField id="kecamatan" label="Kecamatan" required value={text('kecamatan')} onChange={set('kecamatan')} error={form.errors.kecamatan} />
                            <TextField id="desa_kelurahan" label="Desa / kelurahan" required value={text('desa_kelurahan')} onChange={set('desa_kelurahan')} error={form.errors.desa_kelurahan} />
                        </div>
                        <TextField id="alamat_toko" label="Alamat lengkap" value={text('alamat_toko')} onChange={set('alamat_toko')} error={form.errors.alamat_toko} />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField id="no_hp" label="Nomor HP" type="tel" required value={text('no_hp')} onChange={set('no_hp')} error={form.errors.no_hp} description="Juga dipakai untuk masuk." />
                            <TextField id="email" label="Email" type="email" value={text('email')} onChange={set('email')} error={form.errors.email} />
                        </div>
                    </FieldGroup>
                </FieldSet>

                <div className="flex justify-end border-t pt-6">
                    <Button type="submit" size="lg" disabled={form.processing}>
                        {form.processing && <Spinner data-icon="inline-start" />}
                        Simpan profil
                    </Button>
                </div>
            </form>
        </SellerLayout>
    );
}
