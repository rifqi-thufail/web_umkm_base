import { Head, Link, router, usePage } from '@inertiajs/react';
import { ExternalLinkIcon, LogOutIcon, MenuIcon } from 'lucide-react';
import { Brand } from '@/components/brand';
import { FlashToaster } from '@/components/flash-toaster';
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
import { Sheet, SheetClose, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initials } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

const NAV = [
    { label: 'Ringkasan', route: 'seller.dashboard', match: 'seller.dashboard' },
    { label: 'Produk', route: 'seller.products.index', match: 'seller.products.*' },
    { label: 'Pesanan', route: 'seller.orders.index', match: 'seller.orders.*' },
    { label: 'Pembayaran', route: 'seller.activation.index', match: 'seller.activation.*' },
    { label: 'Profil toko', route: 'seller.profile.edit', match: 'seller.profile.edit' },
] as const;

export function SellerLayout({ title, children }: { title: string; children: React.ReactNode }) {
    const { auth } = usePage<SharedProps>().props;
    const seller = auth.seller!;

    return (
        <TooltipProvider delayDuration={200}>
            <Head title={title} />
            <div className="flex min-h-svh flex-col">
                <header className="sticky top-0 z-40 border-b bg-background/90 backdrop-blur">
                    <div className="mx-auto flex h-14 max-w-6xl items-center gap-6 px-4 sm:px-6">
                        <Brand href={route('seller.dashboard')} suffix="Penjual" />
                        <nav aria-label="Menu penjual" className="hidden items-center gap-1 lg:flex">
                            {NAV.map((item) => (
                                <Link
                                    key={item.route}
                                    href={route(item.route)}
                                    className={cn(
                                        'rounded-md px-2.5 py-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground',
                                        route().current(item.match) && 'bg-muted font-medium text-foreground',
                                    )}
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </nav>
                        <div className="ml-auto flex items-center gap-1">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" className="gap-2 pr-1.5 pl-1" aria-label="Menu toko">
                                        <Avatar className="size-6">
                                            {seller.avatar && <AvatarImage src={seller.avatar} alt="" />}
                                            <AvatarFallback className="text-[10px]">{initials(seller.name)}</AvatarFallback>
                                        </Avatar>
                                        <span className="hidden max-w-40 truncate text-sm sm:inline">{seller.name}</span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-56">
                                    <DropdownMenuLabel className="font-normal text-muted-foreground">{seller.phone}</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuGroup>
                                        <DropdownMenuItem asChild>
                                            <a href={route('seller.profile.show', seller.id)} target="_blank" rel="noreferrer">
                                                <ExternalLinkIcon /> Lihat toko publik
                                            </a>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem onSelect={() => router.post(route('seller.logout'))}>
                                            <LogOutIcon /> Keluar
                                        </DropdownMenuItem>
                                    </DropdownMenuGroup>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <Sheet>
                                <SheetTrigger asChild>
                                    <Button variant="ghost" size="icon" className="lg:hidden" aria-label="Buka menu">
                                        <MenuIcon />
                                    </Button>
                                </SheetTrigger>
                                <SheetContent side="right" className="w-72">
                                    <SheetHeader>
                                        <SheetTitle className="text-left">{seller.name}</SheetTitle>
                                    </SheetHeader>
                                    <nav aria-label="Menu penjual seluler" className="flex flex-col px-4">
                                        {NAV.map((item) => (
                                            <SheetClose asChild key={item.route}>
                                                <Link
                                                    href={route(item.route)}
                                                    className={cn('py-2.5 text-base', route().current(item.match) ? 'font-medium' : 'text-muted-foreground')}
                                                >
                                                    {item.label}
                                                </Link>
                                            </SheetClose>
                                        ))}
                                    </nav>
                                </SheetContent>
                            </Sheet>
                        </div>
                    </div>
                </header>
                <main className="flex-1">{children}</main>
            </div>
            <FlashToaster />
        </TooltipProvider>
    );
}
