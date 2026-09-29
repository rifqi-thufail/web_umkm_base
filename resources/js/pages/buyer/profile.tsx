import { useForm } from '@inertiajs/react';
import { TextField } from '@/components/form-field';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { Page, SiteLayout } from '@/layouts/site-layout';
import { initials } from '@/lib/format';

type Profile = { name: string; email: string; phone: string; address: string; avatar: string | null };

function Section({ title, description, children }: { title: string; description: string; children: React.ReactNode }) {
    return (
        <section className="grid grid-cols-1 gap-6 md:grid-cols-[14rem_1fr]">
            <div className="flex flex-col gap-1">
                <h2 className="font-semibold">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            {children}
        </section>
    );
}

export default function Profile({ profile }: { profile: Profile }) {
    const info = useForm<{ name: string; email: string; no_hp: string; alamat: string; avatar: File | null }>({
        name: profile.name,
        email: profile.email,
        no_hp: profile.phone,
        alamat: profile.address,
        avatar: null,
    });
    const pw = useForm({ current_password: '', password: '', password_confirmation: '' });
    const del = useForm({ password: '' });

    return (
        <SiteLayout title="Profil">
            <Page title="Profil" width="narrow">
                <div className="flex flex-col gap-10">
                    <Section title="Data diri" description="Nama, HP, dan alamat dipakai penjual untuk mengirim pesanan.">
                        <form className="flex flex-col gap-6" onSubmit={(e) => { e.preventDefault(); info.post(route('profile.update'), { forceFormData: true, preserveScroll: true }); }}>
                            <FieldGroup>
                                <Field>
                                    <FieldLabel htmlFor="avatar">Foto</FieldLabel>
                                    <div className="flex items-center gap-4">
                                        <Avatar className="size-14">
                                            {profile.avatar && <AvatarImage src={profile.avatar} alt="" />}
                                            <AvatarFallback>{initials(profile.name)}</AvatarFallback>
                                        </Avatar>
                                        <Input id="avatar" type="file" accept="image/*" onChange={(e) => info.setData('avatar', e.target.files?.[0] ?? null)} className="max-w-64" />
                                    </div>
                                    {info.errors.avatar && <FieldError>{info.errors.avatar}</FieldError>}
                                </Field>
                                <TextField id="name" label="Nama" required value={info.data.name} onChange={(v) => info.setData('name', v)} error={info.errors.name} />
                                <TextField id="email" label="Email" type="email" required value={info.data.email} onChange={(v) => info.setData('email', v)} error={info.errors.email} />
                                <TextField id="no_hp" label="Nomor HP" type="tel" value={info.data.no_hp} onChange={(v) => info.setData('no_hp', v)} error={info.errors.no_hp} />
                                <Field data-invalid={!!info.errors.alamat || undefined}>
                                    <FieldLabel htmlFor="alamat">Alamat pengiriman</FieldLabel>
                                    <Textarea id="alamat" rows={3} value={info.data.alamat} onChange={(e) => info.setData('alamat', e.target.value)} />
                                    {info.errors.alamat ? <FieldError>{info.errors.alamat}</FieldError> : <FieldDescription>Nama jalan, nomor, kecamatan, kota.</FieldDescription>}
                                </Field>
                            </FieldGroup>
                            <Button type="submit" className="self-start" disabled={info.processing}>
                                {info.processing && <Spinner data-icon="inline-start" />}
                                Simpan
                            </Button>
                        </form>
                    </Section>

                    <Separator />

                    <Section title="Password" description="Gunakan minimal 8 karakter.">
                        <form className="flex flex-col gap-6" onSubmit={(e) => { e.preventDefault(); pw.put(route('password.update'), { preserveScroll: true, onSuccess: () => pw.reset() }); }}>
                            <FieldGroup>
                                <TextField id="current_password" label="Password saat ini" type="password" autoComplete="current-password" value={pw.data.current_password} onChange={(v) => pw.setData('current_password', v)} error={pw.errors.current_password} />
                                <TextField id="new_password" label="Password baru" type="password" autoComplete="new-password" value={pw.data.password} onChange={(v) => pw.setData('password', v)} error={pw.errors.password} />
                                <TextField id="password_confirmation" label="Ulangi password baru" type="password" autoComplete="new-password" value={pw.data.password_confirmation} onChange={(v) => pw.setData('password_confirmation', v)} error={pw.errors.password_confirmation} />
                            </FieldGroup>
                            <Button type="submit" variant="outline" className="self-start" disabled={pw.processing}>
                                Ganti password
                            </Button>
                        </form>
                    </Section>

                    <Separator />

                    <Section title="Hapus akun" description="Riwayat pesanan dan wishlist ikut terhapus. Kuitansi di Base tetap ada karena tidak bisa dihapus.">
                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button variant="destructive" className="self-start">Hapus akun</Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>Hapus akun secara permanen?</AlertDialogTitle>
                                    <AlertDialogDescription>Masukkan password untuk mengonfirmasi. Tindakan ini tidak bisa dibatalkan.</AlertDialogDescription>
                                </AlertDialogHeader>
                                <TextField id="delete_password" label="Password" type="password" value={del.data.password} onChange={(v) => del.setData('password', v)} error={del.errors.password} />
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Batal</AlertDialogCancel>
                                    <AlertDialogAction
                                        variant="destructive"
                                        onClick={(e) => {
                                            e.preventDefault();
                                            del.delete(route('profile.destroy'), { preserveScroll: true });
                                        }}
                                    >
                                        Hapus akun
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </Section>
                </div>
            </Page>
        </SiteLayout>
    );
}
