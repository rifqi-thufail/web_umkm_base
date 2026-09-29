import { Link, router, usePage } from '@inertiajs/react';
import { HeartIcon, LayoutDashboardIcon, LogOutIcon, MenuIcon, PackageIcon, ShoppingBagIcon, StoreIcon, UserIcon } from 'lucide-react';
import { Brand } from '@/components/brand';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetClose, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { initials } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

const NAV = [
    { label: 'Katalog', route: 'home' },
    { label: 'Transparansi', route: 'public.transactions.index' },
    { label: 'Verifikasi', route: 'blockchain.verify' },
    { label: 'Bantuan', route: 'help.index' },
] as const;

function isActive(name: string) {
    return route().current(name) || route().current(`${name}.*`);
}

export function SiteHeader() {
    const { auth, cartCount } = usePage<SharedProps>().props;
    const user = auth.user;

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
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" className="gap-2 pr-1.5 pl-1" aria-label="Menu akun">
                                    <Avatar className="size-6">
                                        {user.avatar && <AvatarImage src={user.avatar} alt="" />}
                                        <AvatarFallback className="text-[10px]">{initials(user.name)}</AvatarFallback>
                                    </Avatar>
                                    <span className="hidden max-w-32 truncate text-sm sm:inline">{user.name}</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                <DropdownMenuLabel className="truncate font-normal text-muted-foreground">{user.email}</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuGroup>
                                    <DropdownMenuItem asChild>
                                        <Link href={route('dashboard')}>
                                            <LayoutDashboardIcon /> Ringkasan
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href={route('orders.index')}>
                                            <PackageIcon /> Riwayat pesanan
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href={route('wishlist.index')}>
                                            <HeartIcon /> Wishlist
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href={route('profile.edit')}>
                                            <UserIcon /> Profil
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                                <DropdownMenuSeparator />
                                <DropdownMenuGroup>
                                    <DropdownMenuItem onSelect={() => router.post(route('logout'))}>
                                        <LogOutIcon /> Keluar
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
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

                    <Sheet>
                        <SheetTrigger asChild>
                            <Button variant="ghost" size="icon" className="md:hidden" aria-label="Buka menu">
                                <MenuIcon />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="right" className="w-72">
                            <SheetHeader>
                                <SheetTitle className="text-left">Menu</SheetTitle>
                            </SheetHeader>
                            <nav aria-label="Menu seluler" className="flex flex-col px-4">
                                {NAV.map((item) => (
                                    <SheetClose asChild key={item.route}>
                                        <Link
                                            href={route(item.route)}
                                            className={cn('rounded-md py-2.5 text-base', isActive(item.route) ? 'font-medium' : 'text-muted-foreground')}
                                        >
                                            {item.label}
                                        </Link>
                                    </SheetClose>
                                ))}
                                <Separator className="my-3" />
                                {!user && !auth.seller && (
                                    <SheetClose asChild>
                                        <Link href={route('seller.register')} className="py-2.5 text-base text-muted-foreground">
                                            Buka toko di AMPUH
                                        </Link>
                                    </SheetClose>
                                )}
                            </nav>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>
    );
}
