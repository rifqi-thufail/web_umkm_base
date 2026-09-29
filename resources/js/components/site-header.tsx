import { Link, usePage } from '@inertiajs/react';
import { MenuIcon, ShoppingBagIcon, StoreIcon } from 'lucide-react';
import { lazy, Suspense, useState } from 'react';
import { Brand } from '@/components/brand';
import { Button } from '@/components/ui/button';
import { AccountButton } from '@/components/site-header-account-button';
import { isActive, NAV } from '@/lib/nav';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

// Radix dropdown/dialog code is only needed after login or when the mobile menu opens.
const AccountMenu = lazy(() => import('@/components/site-header-menus').then((m) => ({ default: m.AccountMenu })));
const MobileNav = lazy(() => import('@/components/site-header-menus').then((m) => ({ default: m.MobileNav })));

export function SiteHeader() {
    const { auth, cartCount } = usePage<SharedProps>().props;
    const user = auth.user;
    const [menuOpen, setMenuOpen] = useState(false);
    const [menuLoaded, setMenuLoaded] = useState(false);
    // A click on the placeholder before the dropdown chunk arrives opens the menu once it loads.
    const [accountClicked, setAccountClicked] = useState(false);

    return (
        <header className="sticky top-0 z-40 border-b bg-background/90 backdrop-blur supports-[backdrop-filter]:bg-background/75">
            <div className="mx-auto flex h-14 max-w-6xl items-center gap-6 px-4 sm:px-6">
                <Brand />

                <nav aria-label="Utama" className="hidden items-center gap-1 md:flex">
                    {NAV.map((item) => (
                        <Link
                            key={item.route}
                            href={route(item.route)}
                            className={cn(
                                'rounded-md px-2.5 py-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground',
                                isActive(item.route) && 'font-medium text-foreground',
                            )}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>

                <div className="ml-auto flex items-center gap-1">
                    {user && (
                        <Button variant="ghost" size="icon" asChild className="relative">
                            <Link href={route('cart.index')} aria-label={`Keranjang, ${cartCount} barang`}>
                                <ShoppingBagIcon />
                                {cartCount > 0 && (
                                    <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground tabular">
                                        {cartCount}
                                    </span>
                                )}
                            </Link>
                        </Button>
                    )}

                    {user ? (
                        <Suspense fallback={<AccountButton user={user} onClick={() => setAccountClicked(true)} />}>
                            <AccountMenu user={user} defaultOpen={accountClicked} />
                        </Suspense>
                    ) : auth.seller ? (
                        <Button size="sm" variant="outline" asChild>
                            <Link href={route('seller.dashboard')}>
                                <StoreIcon data-icon="inline-start" />
                                Toko saya
                            </Link>
                        </Button>
                    ) : (
                        <>
                            <Button size="sm" variant="ghost" asChild className="hidden sm:inline-flex">
                                <Link href={route('seller.register')}>Buka toko</Link>
                            </Button>
                            <Button size="sm" asChild>
                                <Link href={route('login')}>Masuk</Link>
                            </Button>
                        </>
                    )}

                    <Button
                        variant="ghost"
                        size="icon"
                        className="md:hidden"
                        aria-label="Buka menu"
                        onClick={() => {
                            setMenuLoaded(true);
                            setMenuOpen(true);
                        }}
                    >
                        <MenuIcon />
                    </Button>
                    {menuLoaded && (
                        <Suspense fallback={null}>
                            <MobileNav open={menuOpen} onOpenChange={setMenuOpen} showSellerLink={!user && !auth.seller} />
                        </Suspense>
                    )}
                </div>
            </div>
        </header>
    );
}
