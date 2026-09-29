import { Link, router, useForm } from '@inertiajs/react';
import { ChevronLeftIcon, ImagePlusIcon, StarIcon, Trash2Icon, XIcon } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import { TextField } from '@/components/form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel, FieldLegend, FieldSet } from '@/components/ui/field';
import { InputGroup, InputGroupAddon, InputGroupInput, InputGroupText } from '@/components/ui/input-group';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { SellerLayout } from '@/layouts/seller-layout';
import type { Category, ProductDetail } from '@/types';

const MAX_IMAGES = 5;

type Props = { product: ProductDetail | null; categories: Category[] };

export default function ProductForm({ product, categories }: Props) {
    const editing = !!product;
    const form = useForm<{ nama_produk: string; category_id: string; deskripsi: string; harga: string; stok: string; images: File[]; _method?: string }>({
        nama_produk: product?.name ?? '',
        category_id: product?.category_id?.toString() ?? '',
        deskripsi: product?.description ?? '',
        harga: product ? String(product.price) : '',
        stok: product ? String(product.stock) : '',
        images: [],
        ...(editing ? { _method: 'put' } : {}),
    });

    const existing = product?.images ?? [];
    const slotsLeft = MAX_IMAGES - existing.length - form.data.images.length;
    const previews = useMemo(() => form.data.images.map((f) => URL.createObjectURL(f)), [form.data.images]);
    useEffect(() => () => previews.forEach(URL.revokeObjectURL), [previews]);

    const imageError = form.errors.images ?? Object.entries(form.errors).find(([k]) => k.startsWith('images.'))?.[1];

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const url = editing ? route('seller.products.update', product!.id) : route('seller.products.store');
        form.post(url, { forceFormData: true, preserveScroll: true, onSuccess: () => form.setData('images', []) });
    };

    return (
        <SellerLayout title={editing ? `Ubah ${product!.name}` : 'Tambah produk'}>
            <form onSubmit={submit} className="mx-auto flex max-w-3xl flex-col gap-8 px-4 py-8 sm:px-6 sm:py-10">
                <div className="flex flex-col gap-3">
                    <Button variant="ghost" size="sm" asChild className="self-start">
                        <Link href={route('seller.products.index')}>
                            <ChevronLeftIcon data-icon="inline-start" />
                            Produk
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold sm:text-3xl">{editing ? 'Ubah produk' : 'Tambah produk'}</h1>
                </div>

                <FieldSet>
                    <FieldLegend>Foto</FieldLegend>
                    <FieldDescription>Foto pertama menjadi foto utama di katalog. Maksimal {MAX_IMAGES} foto, masing-masing 2 MB.</FieldDescription>
                    <div className="grid grid-cols-3 gap-3 sm:grid-cols-5">
                        {existing.map((img) => (
                            <div key={img.id} className="group relative aspect-square overflow-hidden rounded-md ring-1 ring-border">
                                <img src={img.url} alt="" className="size-full object-cover" />
                                {img.is_primary ? (
                                    <Badge className="absolute top-1.5 left-1.5">Utama</Badge>
                                ) : (
                                    <Button
                                        type="button"
                                        size="icon-xs"
                                        variant="secondary"
                                        className="absolute top-1.5 left-1.5"
                                        aria-label="Jadikan foto utama"
                                        onClick={() => router.post(route('seller.products.images.set-primary', [product!.id, img.id]), {}, { preserveScroll: true })}
                                    >
                                        <StarIcon />
                                    </Button>
                                )}
                                <Button
                                    type="button"
                                    size="icon-xs"
                                    variant="secondary"
                                    className="absolute top-1.5 right-1.5"
                                    aria-label="Hapus foto"
                                    onClick={() => router.delete(route('seller.products.images.delete', [product!.id, img.id]), { preserveScroll: true })}
                                >
                                    <Trash2Icon />
                                </Button>
                            </div>
                        ))}
                        {previews.map((src, i) => (
                            <div key={src} className="relative aspect-square overflow-hidden rounded-md ring-1 ring-primary/40">
                                <img src={src} alt="" className="size-full object-cover" />
                                <Badge variant="secondary" className="absolute bottom-1.5 left-1.5">Baru</Badge>
                                <Button
                                    type="button"
                                    size="icon-xs"
                                    variant="secondary"
                                    className="absolute top-1.5 right-1.5"
                                    aria-label="Batalkan foto"
                                    onClick={() => form.setData('images', form.data.images.filter((_, j) => j !== i))}
                                >
                                    <XIcon />
                                </Button>
                            </div>
                        ))}
                        {slotsLeft > 0 && (
                            <label className="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed text-muted-foreground transition-colors hover:border-primary hover:text-foreground focus-within:outline-2 focus-within:outline-ring">
                                <ImagePlusIcon className="size-5" />
                                <span className="text-xs">Tambah foto</span>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,image/gif"
                                    multiple
                                    className="sr-only"
                                    onChange={(e) => {
                                        const files = Array.from(e.target.files ?? []).slice(0, slotsLeft);
                                        form.setData('images', [...form.data.images, ...files]);
                                        e.target.value = '';
                                    }}
                                />
                            </label>
                        )}
                    </div>
                    {imageError && <FieldError>{imageError}</FieldError>}
                </FieldSet>

                <FieldSet>
                    <FieldLegend>Detail</FieldLegend>
                    <FieldGroup>
                        <TextField id="nama_produk" label="Nama produk" placeholder="Contoh: Kopi Arabika Toraja 250 g" required value={form.data.nama_produk} onChange={(v) => form.setData('nama_produk', v)} error={form.errors.nama_produk} description="Sebutkan berat atau ukuran di nama supaya mudah dibandingkan." />
                        <Field data-invalid={!!form.errors.category_id || undefined}>
                            <FieldLabel htmlFor="category_id">Kategori</FieldLabel>
                            <Select value={form.data.category_id} onValueChange={(v) => form.setData('category_id', v)}>
                                <SelectTrigger id="category_id" className="w-full sm:w-64" aria-invalid={!!form.errors.category_id || undefined}>
                                    <SelectValue placeholder="Pilih kategori" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {categories.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FieldError>{form.errors.category_id}</FieldError>
                        </Field>
                        <Field data-invalid={!!form.errors.deskripsi || undefined}>
                            <FieldLabel htmlFor="deskripsi">Deskripsi</FieldLabel>
                            <Textarea id="deskripsi" rows={5} value={form.data.deskripsi} onChange={(e) => form.setData('deskripsi', e.target.value)} placeholder="Asal bahan, cara pembuatan, rasa, cara penyimpanan." />
                            <FieldError>{form.errors.deskripsi}</FieldError>
                        </Field>
                    </FieldGroup>
                </FieldSet>

                <FieldSet>
                    <FieldLegend>Harga dan stok</FieldLegend>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field data-invalid={!!form.errors.harga || undefined}>
                            <FieldLabel htmlFor="harga">Harga</FieldLabel>
                            <InputGroup>
                                <InputGroupAddon>
                                    <InputGroupText>Rp</InputGroupText>
                                </InputGroupAddon>
                                <InputGroupInput id="harga" inputMode="numeric" required value={form.data.harga} onChange={(e) => form.setData('harga', e.target.value.replace(/\D/g, ''))} aria-invalid={!!form.errors.harga || undefined} className="tabular" />
                            </InputGroup>
                            <FieldError>{form.errors.harga}</FieldError>
                        </Field>
                        <TextField id="stok" label="Stok" inputMode="numeric" required value={form.data.stok} onChange={(v) => form.setData('stok', v.replace(/\D/g, ''))} error={form.errors.stok} />
                    </div>
                </FieldSet>

                <div className="flex flex-col-reverse gap-3 border-t pt-6 sm:flex-row sm:justify-end">
                    <Button variant="ghost" asChild>
                        <Link href={route('seller.products.index')}>Batal</Link>
                    </Button>
                    <Button type="submit" size="lg" disabled={form.processing}>
                        {form.processing && <Spinner data-icon="inline-start" />}
                        {editing ? 'Simpan perubahan' : 'Tambahkan ke katalog'}
                    </Button>
                </div>
            </form>
        </SellerLayout>
    );
}
