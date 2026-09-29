import { useForm, usePage } from '@inertiajs/react';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { Page, SiteLayout } from '@/layouts/site-layout';
import type { SharedProps } from '@/types';

const FAQ = [
    {
        q: 'Bagaimana cara berbelanja?',
        a: (
            <ol className="list-decimal pl-5">
                <li>Daftar atau masuk sebagai pembeli.</li>
                <li>Tambahkan produk ke keranjang. Satu keranjang bisa berisi produk dari beberapa koperasi.</li>
                <li>Checkout, lalu bayar lewat Midtrans (transfer bank, e-wallet, kartu, minimarket) atau kripto.</li>
                <li>Setelah lunas, kuitansi pesanan muncul di Riwayat pesanan beserta bukti di jaringan Base.</li>
            </ol>
        ),
    },
    {
        q: 'Apa yang dicatat di Base, dan apakah data saya ikut terlihat?',
        a: (
            <p>
                Yang disimpan hanya sidik jari (hash) dari nomor pesanan, ID penjual, barang, jumlah, dan harga. Nama, alamat, email, dan nomor HP
                Anda tidak pernah dikirim ke jaringan. Dari hash tidak bisa dibaca isi pesanan, tetapi bisa dipakai untuk membuktikan isinya tidak
                berubah.
            </p>
        ),
    },
    {
        q: 'Bagaimana cara memeriksa kuitansi?',
        a: (
            <p>
                Buka halaman Verifikasi dan tempel hash transaksi atau nomor pesanan. AMPUH menghitung ulang sidik jari dari data pesanan dan
                membacanya langsung dari kontrak OrderRegistry di Base. Anda juga bisa memeriksanya sendiri dengan perintah yang ada di halaman
                kuitansi.
            </p>
        ),
    },
    {
        q: 'Bagaimana cara berjualan?',
        a: (
            <ol className="list-decimal pl-5">
                <li>Daftarkan koperasi atau usaha Anda dengan nomor HP.</li>
                <li>Tambahkan produk dengan foto, harga, dan stok.</li>
                <li>Hubungkan akun Midtrans di menu Pembayaran agar bisa menerima pesanan.</li>
                <li>Proses pesanan yang sudah dibayar dari menu Pesanan.</li>
            </ol>
        ),
    },
    {
        q: 'Lupa password penjual?',
        a: <p>Gunakan “Lupa password” di halaman masuk penjual. Kode OTP 6 digit dikirim lewat SMS ke nomor HP toko dan berlaku 10 menit.</p>,
    },
];

export default function Help() {
    const { auth } = usePage<SharedProps>().props;
    const form = useForm<{ gmail: string; pesan: string; upload_file: File | null }>({
        gmail: auth.user?.email ?? '',
        pesan: '',
        upload_file: null,
    });

    return (
        <SiteLayout title="Bantuan">
            <Page title="Bantuan" description="Jawaban singkat untuk pertanyaan yang paling sering masuk. Kalau belum terjawab, kirim pesan ke tim AMPUH.">
                <div className="grid grid-cols-1 gap-10 lg:grid-cols-[1.3fr_1fr]">
                    <Accordion type="single" collapsible defaultValue="item-0" className="rounded-lg bg-card px-5 ring-1 ring-border">
                        {FAQ.map((item, i) => (
                            <AccordionItem key={item.q} value={`item-${i}`}>
                                <AccordionTrigger className="text-base">{item.q}</AccordionTrigger>
                                <AccordionContent className="max-w-prose text-muted-foreground">{item.a}</AccordionContent>
                            </AccordionItem>
                        ))}
                    </Accordion>

                    <form
                        className="flex flex-col gap-5 self-start rounded-lg bg-card p-5 ring-1 ring-border sm:p-6"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('help.store'), { forceFormData: true, onSuccess: () => form.reset('pesan', 'upload_file') });
                        }}
                    >
                        <div className="flex flex-col gap-1">
                            <h2 className="text-lg font-bold">Kirim pesan</h2>
                            <p className="text-sm text-muted-foreground">Balasan dikirim ke email Anda, biasanya dalam satu hari kerja.</p>
                        </div>
                        <FieldGroup>
                            <Field data-invalid={!!form.errors.gmail || undefined}>
                                <FieldLabel htmlFor="gmail">Email</FieldLabel>
                                <Input id="gmail" type="email" value={form.data.gmail} onChange={(e) => form.setData('gmail', e.target.value)} aria-invalid={!!form.errors.gmail || undefined} required />
                                <FieldError>{form.errors.gmail}</FieldError>
                            </Field>
                            <Field data-invalid={!!form.errors.pesan || undefined}>
                                <FieldLabel htmlFor="pesan">Pesan</FieldLabel>
                                <Textarea
                                    id="pesan"
                                    rows={5}
                                    placeholder="Contoh: pesanan #8E3297A5 sudah dibayar tapi statusnya masih menunggu."
                                    value={form.data.pesan}
                                    onChange={(e) => form.setData('pesan', e.target.value)}
                                    aria-invalid={!!form.errors.pesan || undefined}
                                    required
                                />
                                <FieldError>{form.errors.pesan}</FieldError>
                            </Field>
                            <Field data-invalid={!!form.errors.upload_file || undefined}>
                                <FieldLabel htmlFor="upload_file">Lampiran (opsional)</FieldLabel>
                                <Input id="upload_file" type="file" accept="image/*,video/*" onChange={(e) => form.setData('upload_file', e.target.files?.[0] ?? null)} />
                                {form.errors.upload_file ? (
                                    <FieldError>{form.errors.upload_file}</FieldError>
                                ) : (
                                    <FieldDescription>Foto atau video tangkapan layar, maksimal 10 MB.</FieldDescription>
                                )}
                            </Field>
                        </FieldGroup>
                        <Button type="submit" size="lg" disabled={form.processing}>
                            {form.processing && <Spinner data-icon="inline-start" />}
                            Kirim pesan
                        </Button>
                    </form>
                </div>
            </Page>
        </SiteLayout>
    );
}
