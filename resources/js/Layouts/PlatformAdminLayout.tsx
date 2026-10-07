import ApplicationLogo from '@/Components/ApplicationLogo';
import { UserAvatar } from '@/Components/Avatar';
import FlashToast from '@/Components/FlashToast';
import NavIcon from '@/Components/NavIcon';
import ThemeToggle from '@/Components/ThemeToggle';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Separator } from '@/Components/ui/separator';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarRail,
    SidebarTrigger,
    useSidebar,
} from '@/Components/ui/sidebar';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronsUpDown, LayoutDashboard, LogOut } from 'lucide-react';
import { PropsWithChildren, ReactNode, useEffect } from 'react';

/** Closes the mobile drawer when navigating - the layout persists across Inertia visits. */
function CloseMobileSidebarOnNavigate({ url }: { url: string }) {
    const { setOpenMobile } = useSidebar();

    useEffect(() => {
        setOpenMobile(false);
    }, [url, setOpenMobile]);

    return null;
}

/**
 * Deliberately separate from AuthenticatedLayout - that layout's nav is
 * built entirely around a current shop (feature gates, shop switcher, per-
 * shop roles), none of which applies here. A platform admin may not even
 * belong to a shop. It does share the look: a left sidebar, same shell.
 */
export default function PlatformAdminLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const page = usePage<PageProps>();
    const { auth } = page.props;
    const user = auth.user;

    const navItems = [
        {
            label: 'Shop directory',
            href: route('platform-admin.index'),
            active: route().current('platform-admin.index') === true,
        },
        {
            label: 'Feature pricing',
            href: route('platform-admin.features.index'),
            active: route().current('platform-admin.features.*') === true,
        },
        {
            label: 'Security',
            href: route('platform-admin.security.index'),
            active: route().current('platform-admin.security.*') === true,
        },
        {
            label: 'Plan settings',
            href: route('platform-admin.plans.index'),
            active: route().current('platform-admin.plans.*') === true,
        },
    ];

    return (
        <SidebarProvider className="app-shell min-h-0">
            <CloseMobileSidebarOnNavigate url={page.url} />

            <Sidebar collapsible="icon">
                <SidebarHeader>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                size="lg"
                                tooltip="Platform admin"
                            >
                                <Link href={route('platform-admin.index')}>
                                    <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                        <ApplicationLogo className="size-5 fill-current" />
                                    </div>
                                    <div className="grid flex-1 text-start text-sm leading-tight">
                                        <span className="truncate font-semibold">
                                            Platform admin
                                        </span>
                                    </div>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarHeader>

                <SidebarContent>
                    <SidebarGroup>
                        <SidebarMenu>
                            {navItems.map((item) => (
                                <SidebarMenuItem key={item.label}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={item.active}
                                        tooltip={item.label}
                                        className="relative data-[active=true]:bg-primary data-[active=true]:font-semibold data-[active=true]:text-primary-foreground data-[active=true]:hover:bg-primary/90 data-[active=true]:hover:text-primary-foreground"
                                    >
                                        <Link
                                            href={item.href}
                                            aria-current={
                                                item.active ? 'page' : undefined
                                            }
                                        >
                                            <NavIcon
                                                label={item.label}
                                                className="size-4"
                                            />
                                            <span>{item.label}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                </SidebarContent>

                <SidebarFooter>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <SidebarMenuButton
                                        size="lg"
                                        tooltip={user.name}
                                    >
                                        <UserAvatar
                                            name={user.name}
                                            src={user.avatar_url}
                                            className="bg-sidebar-accent"
                                        />
                                        <div className="grid flex-1 text-start text-sm leading-tight">
                                            <span className="truncate font-medium">
                                                {user.name}
                                            </span>
                                            <span className="truncate text-xs text-muted-foreground">
                                                {user.email}
                                            </span>
                                        </div>
                                        <ChevronsUpDown className="ms-auto" />
                                    </SidebarMenuButton>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    side="top"
                                    align="start"
                                    className="w-(--radix-dropdown-menu-trigger-width) min-w-56"
                                >
                                    {user.current_shop_id && (
                                        <>
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href={route('dashboard')}
                                                    className="cursor-pointer"
                                                >
                                                    <LayoutDashboard /> Back to
                                                    dashboard
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                        </>
                                    )}
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                            className="w-full cursor-pointer"
                                        >
                                            <LogOut /> Log Out
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarFooter>
                <SidebarRail />
            </Sidebar>

            <SidebarInset className="min-h-0 overflow-hidden bg-gray-100">
                <header className="flex h-14 shrink-0 items-center gap-2 border-b bg-white px-4">
                    <SidebarTrigger className="-ms-1" />
                    <Separator orientation="vertical" className="me-2 h-4" />
                    <div className="flex-1" />
                    <ThemeToggle />
                </header>

                <FlashToast />

                {/* The page scrolls here, not the window, so the header above
                    stays put. `scroll-region` lets Inertia reset/preserve this
                    scroll position on visits like it does the window's. */}
                <div
                    scroll-region=""
                    className="min-h-0 flex-1 overflow-y-auto"
                >
                    {header && (
                        <header className="border-b bg-white">
                            <div className="mx-auto max-w-7xl px-4 py-4 sm:px-4 lg:px-8">
                                {header}
                            </div>
                        </header>
                    )}

                    <main>{children}</main>
                </div>
            </SidebarInset>
        </SidebarProvider>
    );
}
