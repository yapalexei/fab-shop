import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

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

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth, enabledFeatures, flash } = usePage().props;
    const user = auth.user;
    const customersEnabled = enabledFeatures.includes('customers');
    const quoteBuilderEnabled = enabledFeatures.includes('quote-builder');
    const jobBoardEnabled = enabledFeatures.includes('job-board');

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
                                    href={route('dashboard')}
                                    active={route().current('dashboard')}
                                >
                                    Dashboard
                                </NavLink>
                                <GatedNavLink
                                    enabled={customersEnabled}
                                    href={route('customers.index')}
                                    active={route().current('customers.*')}
                                    label="Customers"
                                />
                                <GatedNavLink
                                    enabled={quoteBuilderEnabled}
                                    href={route('quotes.index')}
                                    active={route().current('quotes.*')}
                                    label="Quotes"
                                />
                                <GatedNavLink
                                    enabled={quoteBuilderEnabled}
                                    href={route('materials.index')}
                                    active={route().current('materials.*')}
                                    label="Materials"
                                />
                                <GatedNavLink
                                    enabled={quoteBuilderEnabled}
                                    href={route('services.index')}
                                    active={route().current('services.*')}
                                    label="Services"
                                />
                                <GatedNavLink
                                    enabled={quoteBuilderEnabled}
                                    href={route('business-partners.index')}
                                    active={route().current('business-partners.*')}
                                    label="Business partners"
                                />
                                <GatedNavLink
                                    enabled={quoteBuilderEnabled}
                                    href={route('equipment.index')}
                                    active={route().current('equipment.*')}
                                    label="Equipment"
                                />
                                <GatedNavLink
                                    enabled={quoteBuilderEnabled}
                                    href={route('markup-defaults.index')}
                                    active={route().current('markup-defaults.*')}
                                    label="Markup"
                                />
                                <GatedNavLink
                                    enabled={jobBoardEnabled}
                                    href={route('jobs.index')}
                                    active={route().current('jobs.*')}
                                    label="Jobs"
                                />
                                <NavLink
                                    href={route('team.index')}
                                    active={route().current('team.*')}
                                >
                                    Team
                                </NavLink>
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
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

                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' sm:hidden'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        <GatedResponsiveNavLink
                            enabled={customersEnabled}
                            href={route('customers.index')}
                            active={route().current('customers.*')}
                            label="Customers"
                        />
                        <GatedResponsiveNavLink
                            enabled={quoteBuilderEnabled}
                            href={route('quotes.index')}
                            active={route().current('quotes.*')}
                            label="Quotes"
                        />
                        <GatedResponsiveNavLink
                            enabled={quoteBuilderEnabled}
                            href={route('materials.index')}
                            active={route().current('materials.*')}
                            label="Materials"
                        />
                        <GatedResponsiveNavLink
                            enabled={quoteBuilderEnabled}
                            href={route('services.index')}
                            active={route().current('services.*')}
                            label="Services"
                        />
                        <GatedResponsiveNavLink
                            enabled={quoteBuilderEnabled}
                            href={route('business-partners.index')}
                            active={route().current('business-partners.*')}
                            label="Business partners"
                        />
                        <GatedResponsiveNavLink
                            enabled={quoteBuilderEnabled}
                            href={route('equipment.index')}
                            active={route().current('equipment.*')}
                            label="Equipment"
                        />
                        <GatedResponsiveNavLink
                            enabled={quoteBuilderEnabled}
                            href={route('markup-defaults.index')}
                            active={route().current('markup-defaults.*')}
                            label="Markup"
                        />
                        <GatedResponsiveNavLink
                            enabled={jobBoardEnabled}
                            href={route('jobs.index')}
                            active={route().current('jobs.*')}
                            label="Jobs"
                        />
                        <ResponsiveNavLink
                            href={route('team.index')}
                            active={route().current('team.*')}
                        >
                            Team
                        </ResponsiveNavLink>
                    </div>

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
