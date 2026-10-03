type RouteParams = string | number | Record<string, string | number | null | undefined> | (string | number)[];

interface ZiggyRouter {
    current(name?: string, params?: RouteParams): boolean;
    current(): string | undefined;
}

declare global {
    /** Ziggy's global `route()` helper, injected by the @routes Blade directive. */
    function route(): ZiggyRouter;
    function route(name: string, params?: RouteParams, absolute?: boolean): string;
}

export type AuthUser = { id: number; name: string; email: string; avatar: string | null };
export type AuthSeller = { id: number; name: string; phone: string; avatar: string | null; can_receive_payments: boolean };

export type SharedProps = {
    appName: string;
    auth: { user: AuthUser | null; seller: AuthSeller | null };
    cartCount: number;
    flash: { success?: string | null; error?: string | null; info?: string | null; warning?: string | null; status?: string | null };
    chain: { name: string; chainId: number; explorer: string | null };
    errors: Record<string, string>;
};

export type ProductCard = {
    id: number;
    name: string;
    price: number;
    stock: number;
    image: string | null;
    category: string | null;
    seller: { id: number; name: string; area: string } | null;
    wishlisted: boolean;
};

export type Seller = {
    id: number;
    name: string;
    type: string;
    area: string;
    village: string;
    address: string | null;
    description: string | null;
    avatar: string | null;
    joined: string | null;
};

export type ProductDetail = Omit<ProductCard, 'seller'> & {
    description: string | null;
    category_id: number | null;
    images: { id: number; url: string; is_primary: boolean }[];
    seller: Seller | null;
};

export type Category = { id: number; name: string; slug: string };

export type ChainInfo = {
    status: 'pending' | 'confirmed' | 'failed' | null;
    tx_hash: string | null;
    data_hash: string | null;
    block: number | null;
    chain_id: number | null;
    anchored_at: string | null;
    explorer_url: string | null;
};

export type OrderItem = { id: number; product_id: number; name: string; image: string | null; quantity: number; price: number };

export type Order = {
    id: number;
    number: string;
    total: number;
    status: string;
    status_label: string;
    payment_status: string;
    payment_status_label: string;
    gateway: string | null;
    created_at: string;
    seller: { id: number; name: string } | null;
    buyer: { name: string; phone: string; address?: string } | null;
    chain: ChainInfo;
    items?: OrderItem[];
};

export type Paginated<T> = {
    data: T[];
    meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    links: { prev: string | null; next: string | null; pages: { page: number; url: string }[] };
};

export type Network = { name: string; chain_id: number; contract: string | null; explorer: string | null };

export type WalletBalance = { wei: string; eth: string; display: string };

export type Wallet = {
    enabled: boolean;
    address: string | null;
    provider: string | null;
    connected_at: string | null;
    balance: WalletBalance | null;
    limits: { per_transfer: string; daily: string };
};

export type WalletTransfer = {
    id: number;
    direction: 'sent' | 'received';
    amount: string;
    tx_hash: string | null;
    status: 'pending' | 'confirmed' | 'failed';
    status_label: string;
    counterparty: { name: string; email: string | null; address: string };
    created_at: string | null;
};
