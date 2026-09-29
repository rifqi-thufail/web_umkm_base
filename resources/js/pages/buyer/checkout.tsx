import { Link, useForm } from '@inertiajs/react';
import { BitcoinIcon, CreditCardIcon } from 'lucide-react';
import { BaseGlyph } from '@/components/chain-proof';
import { ProductImage } from '@/components/product-card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Field, FieldContent, FieldDescription, FieldLabel, FieldLegend, FieldSet, FieldTitle } from '@/components/ui/field';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Page, SiteLayout } from '@/layouts/site-layout';
import { formatRupiah } from '@/lib/format';
import { type CartItem, groupBySeller } from '@/lib/cart';

type Props = {
    items: CartItem[];
    buyer: { name: string; email: string; phone: string | null; address: string | null };
    gateways: { midtrans: boolean; coinpayments: boolean };
};

const GATEWAYS = [
    { value: 'midtrans', icon: CreditCardIcon, title: 'Midtrans', body: 'Transfer bank, virtual account, GoPay, OVO, DANA, kartu, atau minimarket.' },
    { value: 'coinpayments', icon: BitcoinIcon, title: 'Kripto (CoinPayments)', body: 'Bayar dengan USDC, ETH, atau BTC. Anda akan diarahkan ke halaman CoinPayments.' },
] as const;

export default function Checkout({ items, buyer, gateways }: Props) {
    const form = useForm({ payment_gateway: 'midtrans' });
    const total = items.reduce((n, i) => n + i.quantity * i.product.price, 0);
    const groups = groupBySeller(items);

    return (
        <SiteLayout title="Pembayaran">
            <Page title="Pembayaran" description="Periksa pesanan, lalu pilih cara bayar.">
                <form
                    className="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_22rem] lg:items-start"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('orders.process'));
                    }}
                >
                    <div className="flex flex-col gap-8">
                        <section className="flex flex-col gap-3 rounded-lg bg-card p-5 ring-1 ring-border">
                            <div className="flex items-baseline justify-between gap-4">
                                <h2 className="font-semibold">Kirim ke</h2>
                                <Button variant="link" size="sm" asChild className="h-auto p-0">
                                    <Link href={route('profile.edit')}>Ubah</Link>
                                </Button>
                            </div>
                            <div className="text-sm">
                                <p className="font-medium">{buyer.name}</p>
                                <p className="text-muted-foreground">{buyer.phone ?? 'Nomor HP belum diisi'}</p>
                                <p className="text-muted-foreground">{buyer.address ?? 'Alamat belum diisi'}</p>
                            </div>
                            {!buyer.address && (
                                <Alert>
                                    <AlertDescription>Tambahkan alamat di profil supaya penjual bisa mengirim pesanan.</AlertDescription>
                                </Alert>
                            )}
                        </section>

                        <FieldSet>
                            <FieldLegend>Metode pembayaran</FieldLegend>
                            <RadioGroup value={form.data.payment_gateway} onValueChange={(v) => form.setData('payment_gateway', v)}>
                                {GATEWAYS.map((g) => (
                                    <FieldLabel key={g.value} htmlFor={g.value}>
                                        <Field orientation="horizontal" data-disabled={!gateways[g.value] || undefined}>
                                            <g.icon className="size-5 shrink-0 text-muted-foreground" />
                                            <FieldContent>
                                                <FieldTitle>{g.title}</FieldTitle>
                                                <FieldDescription>{gateways[g.value] ? g.body : 'Belum dikonfigurasi di server ini.'}</FieldDescription>
                                            </FieldContent>
                                            <RadioGroupItem value={g.value} id={g.value} disabled={!gateways[g.value]} />
                                        </Field>
                                    </FieldLabel>
                                ))}
                            </RadioGroup>
                        </FieldSet>

                        {groups.map(([seller, group]) => (
                            <section key={seller} className="flex flex-col gap-3">
                                <h2 className="text-sm font-semibold">{seller}</h2>
                                <ul className="flex flex-col gap-3">
                                    {group.map((item) => (
                                        <li key={item.id} className="flex items-center gap-3">
                                            <ProductImage src={item.product.image} alt="" className="size-12 shrink-0 rounded-md" />
                                            <span className="min-w-0 flex-1 truncate text-sm">
                                                {item.product.name} <span className="text-muted-foreground">× {item.quantity}</span>
                                            </span>
                                            <span className="text-sm tabular">{formatRupiah(item.quantity * item.product.price)}</span>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>

                    <aside className="flex flex-col gap-4 rounded-lg bg-card p-5 ring-1 ring-border lg:sticky lg:top-20">
                        <div className="flex items-baseline justify-between">
                            <span className="text-sm">Total</span>
                            <span className="font-heading text-2xl font-extrabold">{formatRupiah(total)}</span>
                        </div>
                        <Separator />
                        <p className="flex gap-2 text-xs text-muted-foreground">
                            <BaseGlyph className="mt-0.5" />
                            Setelah lunas, {groups.length > 1 ? `${groups.length} kuitansi` : 'kuitansi'} dicatat di jaringan Base.
                        </p>
                        {form.errors.payment_gateway && <p className="text-sm text-destructive">{form.errors.payment_gateway}</p>}
                        <Button type="submit" size="lg" disabled={form.processing || !gateways[form.data.payment_gateway as 'midtrans']}>
                            {form.processing && <Spinner data-icon="inline-start" />}
                            Bayar {formatRupiah(total)}
                        </Button>
                    </aside>
                </form>
            </Page>
        </SiteLayout>
    );
}
