export const NAV = [
    { label: 'Katalog', route: 'home' },
    { label: 'Transparansi', route: 'public.transactions.index' },
    { label: 'Verifikasi', route: 'blockchain.verify' },
    { label: 'Bantuan', route: 'help.index' },
] as const;

export function isActive(name: string) {
    return route().current(name) || route().current(`${name}.*`);
}
