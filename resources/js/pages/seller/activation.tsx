import { router, useForm } from '@inertiajs/react';
import { CheckCircle2Icon, CircleIcon, ExternalLinkIcon } from 'lucide-react';
import { TextField } from '@/components/form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import { SellerLayout } from '@/layouts/seller-layout';

type Props = {
    activation: { has_merchant_id: boolean; has_client_key: boolean; has_server_key: boolean; progress_percentage: number; can_receive_payments: boolean };
    config: { merchant_id: string | null; client_key: string | null; has_server_key: boolean };
    isActive: boolean;
};

export default function Activation({ activation, config, isActive }: Props) {
    const form = useForm({ merchant_id: config.merchant_id ?? '', client_key: config.client_key ?? '', server_key: '' });

    const checks = [
        ['Merchant ID', activation.has_merchant_id],
        ['Client key', activation.has_client_key],
        ['Server key', activation.has_server_key],
    ] as const;

    return (
        <SellerLayout title="Pembayaran">
            <div className="mx-auto grid max-w-5xl grid-cols-1 gap-10 px-4 py-8 sm:px-6 sm:py-10 lg:grid-cols-[1fr_18rem]">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col gap-2">
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-bold sm:text-3xl">Pembayaran</h1>
                            {activation.can_receive_payments ? <Badge variant="success">Aktif</Badge> : <Badge variant="warning">Belum aktif</Badge>}
                        </div>
                        <p className="max-w-prose text-muted-foreground">
                            Hubungkan akun Midtrans toko agar uang dari pembeli masuk langsung ke rekening koperasi. Kuncinya ada di Dashboard Midtrans,
                            menu Settings → Access Keys.
                        </p>
                    </div>

                    <form
                        className="flex flex-col gap-6"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.put(route('seller.activation.update'), { preserveScroll: true, onSuccess: () => form.reset('server_key') });
                        }}
                    >
                        <FieldGroup>
                            <TextField id="merchant_id" label="Merchant ID" placeholder="G123456789" value={form.data.merchant_id} onChange={(v) => form.setData('merchant_id', v)} error={form.errors.merchant_id} />
                            <TextField id="client_key" label="Client key" placeholder="SB-Mid-client-…" value={form.data.client_key} onChange={(v) => form.setData('client_key', v)} error={form.errors.client_key} />
                            <TextField
                                id="server_key"
                                label="Server key"
                                type="password"
                                autoComplete="off"
                                placeholder={config.has_server_key ? 'Tersimpan. Isi hanya untuk mengganti.' : 'SB-Mid-server-…'}
                                value={form.data.server_key}
                                onChange={(v) => form.setData('server_key', v)}
                                error={form.errors.server_key}
                                description="Disimpan di server dan tidak pernah ditampilkan lagi."
                            />
                        </FieldGroup>
                        <div className="flex flex-wrap gap-3">
                            <Button type="submit" size="lg" disabled={form.processing}>
                                {form.processing && <Spinner data-icon="inline-start" />}
                                Simpan dan aktifkan
                            </Button>
                            <Button variant="outline" size="lg" asChild>
                                <a href="https://dashboard.midtrans.com/register" target="_blank" rel="noreferrer">
                                    Belum punya akun Midtrans
                                    <ExternalLinkIcon data-icon="inline-end" />
                                </a>
                            </Button>
                        </div>
                    </form>
                </div>

                <aside className="flex flex-col gap-4 self-start rounded-lg bg-card p-5 ring-1 ring-border">
                    <p className="text-sm font-medium">Kelengkapan</p>
                    <Progress value={activation.progress_percentage} aria-label="Kelengkapan data pembayaran" />
                    <ul className="flex flex-col gap-2 text-sm">
                        {checks.map(([label, done]) => (
                            <li key={label} className="flex items-center gap-2">
                                {done ? <CheckCircle2Icon className="size-4 text-success" /> : <CircleIcon className="size-4 text-muted-foreground" />}
                                <span className={done ? '' : 'text-muted-foreground'}>{label}</span>
                            </li>
                        ))}
                    </ul>
                    {isActive && (
                        <Button variant="ghost" size="sm" className="self-start text-muted-foreground" onClick={() => router.patch(route('seller.activation.deactivate'))}>
                            Nonaktifkan pembayaran
                        </Button>
                    )}
                </aside>
            </div>
        </SellerLayout>
    );
}
