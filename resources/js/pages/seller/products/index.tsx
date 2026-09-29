import { Link, router } from '@inertiajs/react';
import { MoreHorizontalIcon, PackagePlusIcon, PencilIcon, Trash2Icon } from 'lucide-react';
import { Pager } from '@/components/pager';
import { ProductImage } from '@/components/product-card';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { SellerLayout } from '@/layouts/seller-layout';
import { formatRupiah } from '@/lib/format';
import type { Paginated, ProductCard } from '@/types';
import { useState } from 'react';

export default function SellerProducts({ products }: { products: Paginated<ProductCard> }) {
    const [deleting, setDeleting] = useState<ProductCard | null>(null);

    return (
        <SellerLayout title="Produk">
            <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6 sm:py-10">
                <div className="flex items-end justify-between gap-4">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-bold sm:text-3xl">Produk</h1>
                        <p className="text-muted-foreground">{products.meta.total} produk tampil di katalog.</p>
                    </div>
                    <Button asChild>
                        <Link href={route('seller.products.create')}>
                            <PackagePlusIcon data-icon="inline-start" />
                            <span className="hidden sm:inline">Tambah produk</span>
                            <span className="sm:hidden">Tambah</span>
                        </Link>
                    </Button>
                </div>

                {products.data.length ? (
                    <>
                        <div className="overflow-x-auto rounded-lg bg-card ring-1 ring-border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Produk</TableHead>
                                        <TableHead className="hidden sm:table-cell">Kategori</TableHead>
                                        <TableHead className="text-right">Harga</TableHead>
                                        <TableHead className="text-right">Stok</TableHead>
                                        <TableHead className="sr-only">Aksi</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {products.data.map((p) => (
                                        <TableRow key={p.id}>
                                            <TableCell>
                                                <Link href={route('seller.products.edit', p.id)} className="flex items-center gap-3">
                                                    <ProductImage src={p.image} alt="" className="size-10 shrink-0 rounded-md" />
                                                    <span className="max-w-64 truncate font-medium">{p.name}</span>
                                                </Link>
                                            </TableCell>
                                            <TableCell className="hidden text-muted-foreground sm:table-cell">{p.category}</TableCell>
                                            <TableCell className="text-right tabular">{formatRupiah(p.price)}</TableCell>
                                            <TableCell className="text-right">
                                                {p.stock === 0 ? <Badge variant="destructive">Habis</Badge> : p.stock < 10 ? <Badge variant="warning">{p.stock}</Badge> : <span className="tabular">{p.stock}</span>}
                                            </TableCell>
                                            <TableCell className="w-10 text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="icon-sm" aria-label={`Aksi untuk ${p.name}`}>
                                                            <MoreHorizontalIcon />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuGroup>
                                                            <DropdownMenuItem asChild>
                                                                <Link href={route('seller.products.edit', p.id)}>
                                                                    <PencilIcon /> Ubah
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem variant="destructive" onSelect={() => setDeleting(p)}>
                                                                <Trash2Icon /> Hapus
                                                            </DropdownMenuItem>
                                                        </DropdownMenuGroup>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        <Pager paginator={products} label="produk" />
                    </>
                ) : (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <PackagePlusIcon />
                            </EmptyMedia>
                            <EmptyTitle>Belum ada produk</EmptyTitle>
                            <EmptyDescription>Tambahkan produk pertama dengan foto, harga, dan stok.</EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button asChild>
                                <Link href={route('seller.products.create')}>Tambah produk</Link>
                            </Button>
                        </EmptyContent>
                    </Empty>
                )}
            </div>

            <AlertDialog open={!!deleting} onOpenChange={(o) => !o && setDeleting(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus {deleting?.name}?</AlertDialogTitle>
                        <AlertDialogDescription>Produk dan semua fotonya dihapus. Pesanan lama yang berisi produk ini tetap tersimpan.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={() => deleting && router.delete(route('seller.products.destroy', deleting.id), { onFinish: () => setDeleting(null) })}>
                            Hapus produk
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </SellerLayout>
    );
}
