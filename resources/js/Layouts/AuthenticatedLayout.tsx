import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import FlashToast from '@/Components/FlashToast';
import LegalLinks from '@/Components/LegalLinks';
import NavIcon from '@/Components/NavIcon';
import NavLink from '@/Components/NavLink';
import RequiresSubscription from '@/Components/RequiresSubscription';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import ThemeToggle from '@/Components/ThemeToggle';
import TrialBanner from '@/Components/TrialBanner';
import { useCan } from '@/lib/permissions';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useEffect, useState } from 'react';

function GatedNavLink({
    enabled,
    locked = false,
    href,
    active,
    label,
}: {
    enabled: boolean;
    locked?: boolean;
    href: string;
    active: boolean;
    label: string;
}) {
    if (enabled) {
        return (
            <NavLink href={href} active={active}>
                <NavIcon label={label} />
                {label}
                {locked && <RequiresSubscription />}
            </NavLink>
        );
    }

    return (
        <span
            className="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-gray-300"
        >
            <NavIcon label={label} />
            {label}
            <RequiresSubscription />
        </span>
    );
}

function GatedResponsiveNavLink({
    enabled,
    locked = false,
    href,
    active,
    label,
}: {
    enabled: boolean;
    locked?: boolean;
    href: string;
    active: boolean;
    label: string;
}) {
    if (enabled) {
        return (
            <ResponsiveNavLink href={href} active={active}>
                <NavIcon label={label} />
                {label}
                {locked && <RequiresSubscription />}
            </ResponsiveNavLink>
        );
    }

    return (
        <span className="flex items-center py-2 pe-4 ps-3 text-base font-medium text-gray-300">
            <NavIcon label={label} />
            {label}
            <RequiresSubscription />
        </span>
    );
}

function GatedDropdownLink({
    enabled,
    locked = false,
    href,
    label,
}: {
    enabled: boolean;
    locked?: boolean;
    href: string;
    label: string;
}) {
    if (enabled) {
        return (
            <Dropdown.Link href={href}>
                <NavIcon label={label} />
                {label}
                {locked && <RequiresSubscription />}
            </Dropdown.Link>
        );
    }

    return (
        <span
            className="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-300"
        >
            <NavIcon label={label} />
            {label}
            <RequiresSubscription />
        </span>
    );
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
        <Link
            href={route('shop.plan.index')}
            className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-indigo-500"
        >
            Upgrade
        </Link>
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
    ]
        .filter((item) => item.visible)
        .sort((a, b) => a.label.localeCompare(b.label));

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    // The mobile menu is a drawer over the page: close it when navigating
    // (the layout persists across Inertia visits) or on Escape.
    useEffect(() => {
        setShowingNavigationDropdown(false);
    }, [page.url]);

    useEffect(() => {
        if (!showingNavigationDropdown) return;
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') setShowingNavigationDropdown(false);
        };
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [showingNavigationDropdown]);

    return (
        <div className="app-shell flex flex-col bg-gray-100">
            <nav className="shrink-0 border-b border-gray-100 bg-white">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between">
                        <div className="flex">
                            <div className="flex shrink-0 items-center">
                                <Link href="/">
                                    <ApplicationLogo className="block h-9 w-auto fill-current text-gray-800" />
                                </Link>
                            </div>

                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink
                                    href={route('dashboard')}
                                    active={route().current('dashboard')}
                                >
                                    <NavIcon label="Dashboard" />
                            Dashboard
                                </NavLink>
                                {can('quotes.view') && (
                                    <GatedNavLink
                                        enabled={quoteBuilderEnabled}
                                        locked={isLocked('quote-builder')}
                                        href={route('quotes.index')}
                                        active={route().current('quotes.*')}
                                        label="Quotes"
                                    />
                                )}
                                {can('jobs.view') && (
                                    <GatedNavLink
                                        enabled={jobBoardEnabled}
                                        locked={isLocked('job-board')}
                                        href={route('jobs.index')}
                                        active={route().current('jobs.*')}
                                        label="Jobs"
                                    />
                                )}

                                {setupNavItems.length > 0 && (
                                    <div className="flex items-center">
                                        <Dropdown>
                                            <Dropdown.Trigger>
                                                <button
                                                    type="button"
                                                    className={
                                                        'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none ' +
                                                        (setupActive
                                                            ? 'border-indigo-400 text-gray-900 focus:border-indigo-700'
                                                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 focus:border-gray-300 focus:text-gray-700')
                                                    }
                                                >
                                                    Setup
                                                    <svg
                                                        className="-me-0.5 ms-1 h-4 w-4"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        viewBox="0 0 20 20"
                                                        fill="currentColor"
                                                    >
                                                        <path
                                                            fillRule="evenodd"
                                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                            clipRule="evenodd"
                                                        />
                                                    </svg>
                                                </button>
                                            </Dropdown.Trigger>

                                            <Dropdown.Content align="left">
                                                {setupNavItems.map((item) => (
                                                    <GatedDropdownLink
                                                        key={item.label}
                                                        enabled={item.enabled}
                                                        locked={item.locked}
                                                        href={item.href}
                                                        label={item.label}
                                                    />
                                                ))}
                                            </Dropdown.Content>
                                        </Dropdown>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center sm:gap-3">
                            {upgradeLink}
                            <div className="relative">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className={
                                                    'inline-flex items-center rounded-md border bg-white px-3 py-2 text-sm font-medium leading-4 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none ' +
                                                    (shopNavItems.some(
                                                        (item) => item.active,
                                                    )
                                                        ? 'border-indigo-300 text-gray-900'
                                                        : 'border-gray-300 text-gray-500')
                                                }
                                            >
                                                {currentShop?.name ?? 'Shop'}

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content align="right" width="56">
                                        {shopNavItems.map((item) => (
                                            <GatedDropdownLink
                                                key={item.label}
                                                enabled={item.enabled}
                                                locked={item.locked}
                                                href={item.href}
                                                label={item.label}
                                            />
                                        ))}

                                        {availableShops.length > 1 && (
                                            <>
                                                <div className="mt-1 border-t border-gray-100 px-4 pb-1 pt-2 text-xs font-semibold uppercase tracking-wider text-gray-400">
                                                    Switch shop
                                                </div>
                                                {availableShops.map((shop) => (
                                                    <Dropdown.Link
                                                        key={shop.id}
                                                        href={route(
                                                            'current-shop.update',
                                                            shop.id,
                                                        )}
                                                        method="post"
                                                        as="button"
                                                        className={
                                                            shop.id ===
                                                            user.current_shop_id
                                                                ? 'font-semibold'
                                                                : ''
                                                        }
                                                    >
                                                        {shop.name}
                                                    </Dropdown.Link>
                                                ))}
                                            </>
                                        )}
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>

                            <ThemeToggle className="ms-3" />

                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {user.name}

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link
                                            href={route('profile.edit')}
                                        >
                                            Profile
                                        </Dropdown.Link>
                                        {auth.isPlatformAdmin && (
                                            <Dropdown.Link
                                                href={route(
                                                    'platform-admin.index',
                                                )}
                                            >
                                                Platform admin
                                            </Dropdown.Link>
                                        )}
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        <div className="-me-2 flex items-center gap-1 sm:hidden">
                            {upgradeLink}
                            <ThemeToggle />

                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (previousState) => !previousState,
                                    )
                                }
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    className="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        className={
                                            !showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={
                                            showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div className="sm:hidden">
                    <div
                        onClick={() => setShowingNavigationDropdown(false)}
                        aria-hidden="true"
                        className={`fixed inset-0 z-40 bg-black/40 transition-opacity duration-200 ${
                            showingNavigationDropdown
                                ? 'opacity-100'
                                : 'pointer-events-none opacity-0'
                        }`}
                    />
                    <div
                        id="mobile-menu"
                        role="dialog"
                        aria-label="Menu"
                        className={`fixed inset-y-0 right-0 z-50 w-72 max-w-[85vw] overflow-y-auto bg-white shadow-xl transition-[transform,visibility] duration-200 ease-out ${
                            showingNavigationDropdown
                                ? 'visible translate-x-0'
                                : 'invisible translate-x-full'
                        }`}
                    >
                    <div className="flex justify-end px-2 pt-2">
                        <button
                            type="button"
                            onClick={() => setShowingNavigationDropdown(false)}
                            aria-label="Close menu"
                            className="rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus:outline-none"
                        >
                            <svg
                                className="h-6 w-6"
                                stroke="currentColor"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                    <div className="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        {can('quotes.view') && (
                            <GatedResponsiveNavLink
                                enabled={quoteBuilderEnabled}
                                locked={isLocked('quote-builder')}
                                href={route('quotes.index')}
                                active={route().current('quotes.*')}
                                label="Quotes"
                            />
                        )}
                        {can('jobs.view') && (
                            <GatedResponsiveNavLink
                                enabled={jobBoardEnabled}
                                locked={isLocked('job-board')}
                                href={route('jobs.index')}
                                active={route().current('jobs.*')}
                                label="Jobs"
                            />
                        )}
                    </div>

                    {setupNavItems.length > 0 && (
                        <div className="border-t border-gray-200 pb-1 pt-4">
                            <div className="px-4 text-xs font-semibold uppercase tracking-wider text-gray-400">
                                Setup
                            </div>
                            <div className="mt-1 space-y-1">
                                {setupNavItems.map((item) => (
                                    <GatedResponsiveNavLink
                                        key={item.label}
                                        enabled={item.enabled}
                                        locked={item.locked}
                                        href={item.href}
                                        active={item.active}
                                        label={item.label}
                                    />
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="border-t border-gray-200 pb-1 pt-4">
                        <div className="px-4 text-xs font-semibold uppercase tracking-wider text-gray-400">
                            {currentShop?.name ?? 'Shop'}
                        </div>
                        <div className="mt-1 space-y-1">
                            {shopNavItems.map((item) => (
                                <GatedResponsiveNavLink
                                    key={item.label}
                                    enabled={item.enabled}
                                    locked={item.locked}
                                    href={item.href}
                                    active={item.active}
                                    label={item.label}
                                />
                            ))}
                        </div>
                    </div>

                    {availableShops.length > 1 && (
                        <div className="border-t border-gray-200 pb-1 pt-4">
                            <div className="px-4 text-xs font-semibold uppercase tracking-wider text-gray-400">
                                Switch shop
                            </div>
                            <div className="mt-1 space-y-1">
                                {availableShops.map((shop) => (
                                    <ResponsiveNavLink
                                        key={shop.id}
                                        href={route(
                                            'current-shop.update',
                                            shop.id,
                                        )}
                                        method="post"
                                        as="button"
                                        active={
                                            shop.id === user.current_shop_id
                                        }
                                    >
                                        {shop.name}
                                    </ResponsiveNavLink>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="border-t border-gray-200 pb-1 pt-4">
                        <div className="px-4">
                            <div className="text-base font-medium text-gray-800">
                                {user.name}
                            </div>
                            <div className="text-sm font-medium text-gray-500">
                                {user.email}
                            </div>
                        </div>

                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink href={route('profile.edit')}>
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                method="post"
                                href={route('logout')}
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                    </div>
                </div>
            </nav>

            <div className="shrink-0">
                <TrialBanner />
            </div>

            <FlashToast />

            {/* The page scrolls here, not the window, so the nav above stays
                put. `scroll-region` lets Inertia reset/preserve this scroll
                position on visits like it does the window's. */}
            <div scroll-region="" className="app-scroll min-h-0 flex-1 overflow-y-auto">
                {header && (
                    <header className="bg-white shadow">
                        <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            {header}
                        </div>
                    </header>
                )}

                <main>{children}</main>

                <footer className="px-4 py-6">
                    <LegalLinks />
                </footer>
            </div>
        </div>
    );
}
