import ApplicationLogo from "@/Components/ApplicationLogo";
import Dropdown from "@/Components/Dropdown";
import NavLink from "@/Components/NavLink";
import ResponsiveNavLink from "@/Components/ResponsiveNavLink";
import { Link, usePage } from "@inertiajs/react";
import { PropsWithChildren, ReactNode, useState } from "react";

function GatedNavLink({
    enabled,
    href,
    active,
    label,
}: {
    enabled: boolean;
    href: string;
    active: boolean;
    label: string;
}) {
    if (enabled) {
        return (
            <NavLink href={href} active={active}>
                {label}
            </NavLink>
        );
    }

    return (
        <span
            className="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-gray-300"
            title={`Upgrade your plan to unlock ${label}`}
        >
            {label}
        </span>
    );
}

function GatedResponsiveNavLink({
    enabled,
    href,
    active,
    label,
}: {
    enabled: boolean;
    href: string;
    active: boolean;
    label: string;
}) {
    if (enabled) {
        return (
            <ResponsiveNavLink href={href} active={active}>
                {label}
            </ResponsiveNavLink>
        );
    }

    return (
        <span className="block py-2 pe-4 ps-3 text-base font-medium text-gray-300">
            {label}
        </span>
    );
}

function GatedDropdownLink({
    enabled,
    href,
    label,
}: {
    enabled: boolean;
    href: string;
    label: string;
}) {
    if (enabled) {
        return <Dropdown.Link href={href}>{label}</Dropdown.Link>;
    }

    return (
        <span
            className="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-300"
            title={`Upgrade your plan to unlock ${label}`}
        >
            {label}
        </span>
    );
}

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth, enabledFeatures, availableShops, flash } = usePage().props;
    const user = auth.user;
    const customersEnabled = enabledFeatures.includes("customers");
    const quoteBuilderEnabled = enabledFeatures.includes("quote-builder");
    const jobBoardEnabled = enabledFeatures.includes("job-board");
    const quickbooksEnabled = enabledFeatures.includes("quickbooks-sync");
    const currentShop = availableShops.find(
        (shop) => shop.id === user.current_shop_id,
    );

    // Catalog/pricing setup - touched occasionally, not part of the daily
    // quote/job workflow, so it lives in its own "Setup" menu instead of
    // crowding the primary nav.
    const setupNavItems = [
        {
            label: "Customers",
            href: route("customers.index"),
            active: route().current("customers.*"),
            enabled: customersEnabled,
        },
        {
            label: "Materials",
            href: route("materials.index"),
            active: route().current("materials.*"),
            enabled: quoteBuilderEnabled,
        },
        {
            label: "Services",
            href: route("services.index"),
            active: route().current("services.*"),
            enabled: quoteBuilderEnabled,
        },
        {
            label: "Business partners",
            href: route("business-partners.index"),
            active: route().current("business-partners.*"),
            enabled: quoteBuilderEnabled,
        },
        {
            label: "Equipment",
            href: route("equipment.index"),
            active: route().current("equipment.*"),
            enabled: quoteBuilderEnabled,
        },
        {
            label: "Markup",
            href: route("markup-defaults.index"),
            active: route().current("markup-defaults.*"),
            enabled: quoteBuilderEnabled,
        },
    ];
    const setupActive = setupNavItems.some((item) => item.active);

    // The shop itself - its settings, team, who can do what, and its
    // connected accounts - grouped under the shop's own name. Items the
    // user has no permission for are left out entirely (unlike plan-gated
    // items, which stay visible as an upgrade hint).
    const shopNavItems = [
        {
            label: "Shop settings",
            href: route("shop.settings.edit"),
            active: route().current("shop.settings.*"),
            enabled: true,
            visible: auth.can?.manageShopSettings ?? false,
        },
        {
            label: "Team",
            href: route("team.index"),
            active: route().current("team.*"),
            enabled: true,
            visible: true,
        },
        {
            label: "Roles & permissions",
            href: route("shop.permissions.edit"),
            active: route().current("shop.permissions.*"),
            enabled: true,
            visible: auth.can?.manageShop ?? false,
        },
        {
            label: "QuickBooks",
            href: route("quickbooks.show"),
            active: route().current("quickbooks.*"),
            enabled: quickbooksEnabled,
            visible: true,
        },
    ].filter((item) => item.visible);

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    return (
        <div className="min-h-screen bg-gray-100">
            <nav className="border-b border-gray-100 bg-white">
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
                                    href={route("dashboard")}
                                    active={route().current("dashboard")}
                                >
                                    Dashboard
                                </NavLink>
                                <GatedNavLink
                                    enabled={quoteBuilderEnabled}
                                    href={route("quotes.index")}
                                    active={route().current("quotes.*")}
                                    label="Quotes"
                                />
                                <GatedNavLink
                                    enabled={jobBoardEnabled}
                                    href={route("jobs.index")}
                                    active={route().current("jobs.*")}
                                    label="Jobs"
                                />

                                <div className="flex items-center">
                                    <Dropdown>
                                        <Dropdown.Trigger>
                                            <button
                                                type="button"
                                                className={
                                                    "inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none " +
                                                    (setupActive
                                                        ? "border-indigo-400 text-gray-900 focus:border-indigo-700"
                                                        : "border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 focus:border-gray-300 focus:text-gray-700")
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
                                                    href={item.href}
                                                    label={item.label}
                                                />
                                            ))}
                                        </Dropdown.Content>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            <div className="relative">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className={
                                                    "inline-flex items-center rounded-md border bg-white px-3 py-2 text-sm font-medium leading-4 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none " +
                                                    (shopNavItems.some(
                                                        (item) => item.active,
                                                    )
                                                        ? "border-indigo-300 text-gray-900"
                                                        : "border-gray-300 text-gray-500")
                                                }
                                            >
                                                {currentShop?.name ?? "Shop"}

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
                                                            "current-shop.update",
                                                            shop.id,
                                                        )}
                                                        method="post"
                                                        as="button"
                                                        className={
                                                            shop.id ===
                                                            user.current_shop_id
                                                                ? "font-semibold"
                                                                : ""
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
                                            href={route("profile.edit")}
                                        >
                                            Profile
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route("logout")}
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden">
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
                                                ? "inline-flex"
                                                : "hidden"
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={
                                            showingNavigationDropdown
                                                ? "inline-flex"
                                                : "hidden"
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

                <div
                    className={
                        (showingNavigationDropdown ? "block" : "hidden") +
                        " sm:hidden"
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            href={route("dashboard")}
                            active={route().current("dashboard")}
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        <GatedResponsiveNavLink
                            enabled={quoteBuilderEnabled}
                            href={route("quotes.index")}
                            active={route().current("quotes.*")}
                            label="Quotes"
                        />
                        <GatedResponsiveNavLink
                            enabled={jobBoardEnabled}
                            href={route("jobs.index")}
                            active={route().current("jobs.*")}
                            label="Jobs"
                        />
                    </div>

                    <div className="border-t border-gray-200 pb-1 pt-4">
                        <div className="px-4 text-xs font-semibold uppercase tracking-wider text-gray-400">
                            Setup
                        </div>
                        <div className="mt-1 space-y-1">
                            {setupNavItems.map((item) => (
                                <GatedResponsiveNavLink
                                    key={item.label}
                                    enabled={item.enabled}
                                    href={item.href}
                                    active={item.active}
                                    label={item.label}
                                />
                            ))}
                        </div>
                    </div>

                    <div className="border-t border-gray-200 pb-1 pt-4">
                        <div className="px-4 text-xs font-semibold uppercase tracking-wider text-gray-400">
                            {currentShop?.name ?? "Shop"}
                        </div>
                        <div className="mt-1 space-y-1">
                            {shopNavItems.map((item) => (
                                <GatedResponsiveNavLink
                                    key={item.label}
                                    enabled={item.enabled}
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
                                            "current-shop.update",
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
                            <ResponsiveNavLink href={route("profile.edit")}>
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                method="post"
                                href={route("logout")}
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <header className="bg-white shadow">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            {flash?.status && (
                <div className="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-md bg-green-50 p-4 text-sm text-green-700">
                        {flash.status}
                    </div>
                </div>
            )}

            <main>{children}</main>
        </div>
    );
}
