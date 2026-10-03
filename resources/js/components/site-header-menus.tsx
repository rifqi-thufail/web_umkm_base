import { Link, router } from '@inertiajs/react';
import { HeartIcon, LayoutDashboardIcon, LogOutIcon, PackageIcon, UserIcon, WalletIcon } from 'lucide-react';
import { AccountButton } from '@/components/site-header-account-button';
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
import { Sheet, SheetClose, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { isActive, NAV } from '@/lib/nav';
import { cn } from '@/lib/utils';
import type { AuthUser } from '@/types';

/** Lazy-loaded from SiteHeader so Radix menu/dialog code stays out of the initial bundle. */
export function AccountMenu({ user, defaultOpen }: { user: AuthUser; defaultOpen?: boolean }) {
    return (
        <DropdownMenu defaultOpen={defaultOpen}>
            <DropdownMenuTrigger asChild>
                <AccountButton user={user} />
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
                        <Link href={route('wallet.index')}>
                            <WalletIcon /> Wallet
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
    );
}

export function MobileNav({
    open,
    onOpenChange,
    showSellerLink,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    showSellerLink: boolean;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
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
                    {showSellerLink && (
                        <SheetClose asChild>
                            <Link href={route('seller.register')} className="py-2.5 text-base text-muted-foreground">
                                Buka toko di AMPUH
                            </Link>
                        </SheetClose>
                    )}
                </nav>
            </SheetContent>
        </Sheet>
    );
}
