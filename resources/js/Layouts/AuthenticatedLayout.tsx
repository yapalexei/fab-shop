import { UserAvatar } from '@/Components/Avatar';
import ApplicationLogo from '@/Components/ApplicationLogo';
import FlashToast from '@/Components/FlashToast';
import LegalLinks from '@/Components/LegalLinks';
import NavIcon from '@/Components/NavIcon';
import RequiresSubscription from '@/Components/RequiresSubscription';
import ThemeToggle from '@/Components/ThemeToggle';
import TrialBanner from '@/Components/TrialBanner';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Separator } from '@/Components/ui/separator';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
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
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, LogOut, User } from 'lucide-react';
import { PropsWithChildren, ReactNode, useEffect } from 'react';

interface NavItem {
    label: string;
    href: string;
    active: boolean;
    enabled: boolean;
    locked: boolean;
}

/** One sidebar row; plan-gated items stay visible but greyed as an upgrade hint. */
function SidebarNavItem({ item }: { item: NavItem }) {
    if (!item.enabled) {
        return (
            <SidebarMenuItem>
                <SidebarMenuButton disabled className="opacity-50">
                    <NavIcon label={item.label} className="size-4" />
                    <span>{item.label}</span>
                    <RequiresSubscription />
                </SidebarMenuButton>
            </SidebarMenuItem>
        );
    }

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={item.active}
                tooltip={item.label}
                className="relative data-[active=true]:bg-primary data-[active=true]:font-semibold data-[active=true]:text-primary-foreground data-[active=true]:hover:bg-primary/90 data-[active=true]:hover:text-primary-foreground"
            >
                <Link
                    href={item.href}
                    aria-current={item.active ? 'page' : undefined}
                >
                    <NavIcon label={item.label} className="size-4" />
                    <span>{item.label}</span>
                    {item.locked && <RequiresSubscription />}
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}

function SidebarNavGroup({
    label,
    items,
}: {
    label?: string;
    items: NavItem[];
}) {
    if (items.length === 0) return null;

    return (
        <SidebarGroup>
            {label && <SidebarGroupLabel>{label}</SidebarGroupLabel>}
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarNavItem key={item.label} item={item} />
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

/** Closes the mobile drawer when navigating - the layout persists across Inertia visits. */
function CloseMobileSidebarOnNavigate({ url }: { url: string }) {
    const { setOpenMobile } = useSidebar();

    useEffect(() => {
        setOpenMobile(false);
    }, [url, setOpenMobile]);

    return null;
}

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const page = usePage<PageProps>();
    const { auth, enabledFeatures, readOnlyFeatures, availableShops, shop } =
        page.props;
    const user = auth.user;
    // A read-only feature (trial ended) keeps its pages reachable - the
    // pages themselves hide every edit control.
    const accessibleFeatures = [...enabledFeatures, ...readOnlyFeatures];
    const upgradeLink = shop?.can_upgrade ? (
        <Button asChild size="sm">
            <Link href={route('shop.plan.index')}>Upgrade</Link>
        </Button>
    ) : null;
    const isLocked = (feature: string) => readOnlyFeatures.includes(feature);
    const customersEnabled = accessibleFeatures.includes('customers');
    const quoteBuilderEnabled = accessibleFeatures.includes('quote-builder');
    const jobBoardEnabled = accessibleFeatures.includes('job-board');
    const qualificationRecordsEnabled = accessibleFeatures.includes(
        'qualification-records',
    );
    const currentShop = availableShops.find(
        (shop) => shop.id === user.current_shop_id,
    );
    const can = useCan();

    // Catalog/pricing setup - touched occasionally, not part of the daily
    // quote/job workflow, so it lives in its own "Setup" menu instead of
    // crowding the primary nav. Items the user can't even view are left out
    // (plan-gated ones stay visible, greyed, as an upgrade hint).
    const setupNavItems = [
        {
            label: 'Customers',
            href: route('customers.index'),
            active: route().current('customers.*'),
            enabled: customersEnabled,
            locked: isLocked('customers'),
            visible: can('customers.view'),
        },
        {
            label: 'Materials',
            href: route('materials.index'),
            active: route().current('materials.*'),
            enabled: quoteBuilderEnabled,
            locked: isLocked('quote-builder'),
            visible: can('materials.view'),
        },
        {
            label: 'Services',
            href: route('services.index'),
            active: route().current('services.*'),
            enabled: quoteBuilderEnabled,
            locked: isLocked('quote-builder'),
            visible: can('services.view'),
        },
        {
            label: 'Business partners',
            href: route('business-partners.index'),
            active: route().current('business-partners.*'),
            enabled: quoteBuilderEnabled,
            locked: isLocked('quote-builder'),
            visible: can('business-partners.view'),
        },
        {
            label: 'Equipment',
            href: route('equipment.index'),
            active: route().current('equipment.*'),
            enabled: quoteBuilderEnabled,
            locked: isLocked('quote-builder'),
            visible: can('equipment.view'),
        },
        {
            label: 'Tooling',
            href: route('tools.index'),
            active: route().current('tools.*'),
            enabled: quoteBuilderEnabled,
            locked: isLocked('quote-builder'),
            visible: can('tooling.view'),
        },
        {
            label: 'Material types',
            href: route('material-types.index'),
            active: route().current('material-types.*'),
            enabled: quoteBuilderEnabled,
            locked: isLocked('quote-builder'),
            visible: can('material-types.view'),
        },
        {
            label: 'Procedures',
            href: route('procedures.index'),
            active: route().current('procedures.*'),
            enabled: qualificationRecordsEnabled,
            locked: isLocked('qualification-records'),
            visible: can('procedures.view'),
        },
        {
            label: 'Certifications',
            href: route('certifications.index'),
            active: route().current('certifications.*'),
            enabled: qualificationRecordsEnabled,
            locked: isLocked('qualification-records'),
            visible: can('certifications.view'),
        },
        {
            label: 'Markup',
            href: route('markup-defaults.index'),
            active: route().current('markup-defaults.*'),
            enabled: quoteBuilderEnabled,
            locked: isLocked('quote-builder'),
            visible: can('markup.view'),
        },
        {
            label: 'Job board stages',
            href: route('job-stages.index'),
            active: route().current('job-stages.*'),
            enabled: jobBoardEnabled,
            locked: isLocked('job-board'),
            visible: can('job-stages.view'),
        },
    ]
        .filter((item) => item.visible)
        .sort((a, b) => a.label.localeCompare(b.label));
    const setupActive = setupNavItems.some((item) => item.active);

    // The shop itself - its settings, team, who can do what, and its
    // connected accounts - grouped under the shop's own name. Items the
    // user has no permission for are left out entirely (unlike plan-gated
    // items, which stay visible as an upgrade hint).
    const shopNavItems = [
        {
            label: 'Shop settings',
            href: route('shop.settings.edit'),
            active: route().current('shop.settings.*'),
            enabled: true,
            locked: false,
            visible: can('shop-settings.update'),
        },
        {
            label: 'Team',
            href: route('team.index'),
            active: route().current('team.*'),
            enabled: true,
            locked: false,
            visible: can('team.view'),
        },
        {
            label: 'Roles & permissions',
            href: route('shop.permissions.edit'),
            active: route().current('shop.permissions.*'),
            enabled: true,
            locked: false,
            visible: auth.isOwner,
        },
        {
            label: 'Add-ons',
            href: route('shop.addons.index'),
            active: route().current('shop.addons.*'),
            enabled: true,
            locked: false,
            visible: auth.isOwner,
        },
        {
            label: 'AI assistant',
            href: route('shop.ai.edit'),
            active: route().current('shop.ai.*'),
            enabled: true,
            locked: false,
            visible: auth.isOwner,
        },
    ]
        .filter((item) => item.visible)
        .sort((a, b) => a.label.localeCompare(b.label));

    const mainNavItems: NavItem[] = [
        {
            label: 'Dashboard',
            href: route('dashboard'),
            active: route().current('dashboard') === true,
            enabled: true,
            locked: false,
        },
        ...(can('quotes.view')
            ? [
                  {
                      label: 'Quotes',
                      href: route('quotes.index'),
                      active: route().current('quotes.*') === true,
                      enabled: quoteBuilderEnabled,
                      locked: isLocked('quote-builder'),
                  },
              ]
            : []),
        ...(can('jobs.view')
            ? [
                  {
                      label: 'Jobs',
                      href: route('jobs.index'),
                      active: route().current('jobs.*') === true,
                      enabled: jobBoardEnabled,
                      locked: isLocked('job-board'),
                  },
                  {
                      label: 'Reports',
                      href: route('reports.job-timeline'),
                      active: route().current('reports.*') === true,
                      enabled: jobBoardEnabled,
                      locked: isLocked('job-board'),
                  },
              ]
            : []),
    ];

    return (
        <SidebarProvider className="app-shell min-h-0">
            <CloseMobileSidebarOnNavigate url={page.url} />

            <Sidebar collapsible="icon">
                <SidebarHeader>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <DropdownMenu>
                                <DropdownMenuTrigger
                                    asChild
                                    disabled={availableShops.length <= 1}
                                >
                                    <SidebarMenuButton
                                        size="lg"
                                        tooltip={currentShop?.name ?? 'Shop'}
                                    >
                                        <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                            <ApplicationLogo className="size-5 fill-current" />
                                        </div>
                                        <div className="grid flex-1 text-start text-sm leading-tight">
                                            <span className="truncate font-semibold">
                                                {currentShop?.name ?? 'Shop'}
                                            </span>
                                        </div>
                                        {availableShops.length > 1 && (
                                            <ChevronsUpDown className="ms-auto" />
                                        )}
                                    </SidebarMenuButton>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="start"
                                    className="w-(--radix-dropdown-menu-trigger-width) min-w-56"
                                >
                                    <DropdownMenuLabel>
                                        Switch shop
                                    </DropdownMenuLabel>
                                    {availableShops.map((s) => (
                                        <DropdownMenuItem key={s.id} asChild>
                                            <Link
                                                href={route(
                                                    'current-shop.update',
                                                    s.id,
                                                )}
                                                method="post"
                                                as="button"
                                                className="w-full cursor-pointer"
                                            >
                                                {s.name}
                                                {s.id ===
                                                    user.current_shop_id && (
                                                    <Check className="ms-auto" />
                                                )}
                                            </Link>
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarHeader>

                <SidebarContent>
                    {/* <SidebarNavGroup items={mainNavItems} /> */}
                    <SidebarNavGroup items={mainNavItems.concat(setupNavItems)} />
                    <hr />
                    <SidebarNavGroup
                        items={shopNavItems}
                    />
                </SidebarContent>

                <SidebarFooter>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <SidebarMenuButton size="lg" tooltip={user.name}>
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
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={route('profile.edit')}
                                            className="cursor-pointer"
                                        >
                                            <User /> Profile
                                        </Link>
                                    </DropdownMenuItem>
                                    {auth.isPlatformAdmin && (
                                        <DropdownMenuItem asChild>
                                            <Link
                                                href={route(
                                                    'platform-admin.index',
                                                )}
                                                className="cursor-pointer"
                                            >
                                                Platform admin
                                            </Link>
                                        </DropdownMenuItem>
                                    )}
                                    <DropdownMenuSeparator />
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
                    {upgradeLink}
                    <ThemeToggle />
                </header>

                <div className="shrink-0">
                    <TrialBanner />
                </div>

                <FlashToast />

                {/* The page scrolls here, not the window, so the header above
                    stays put. `scroll-region` lets Inertia reset/preserve this
                    scroll position on visits like it does the window's. */}
                <div
                    scroll-region=""
                    className="min-h-0 flex-1 overflow-y-auto grid grid-rows-[auto_1fr_auto]"
                >
                    {header && (
                        <header className="border-b bg-white">
                            <div className="mx-auto max-w-7xl px-4 py-4 sm:px-4 lg:px-8 flex align-items-center">
                                {header}
                            </div>
                        </header>
                    )}

                    <main>{children}</main>

                    <footer className="px-4 py-6">
                        <LegalLinks />
                    </footer>
                </div>
            </SidebarInset>
        </SidebarProvider>
    );
}
