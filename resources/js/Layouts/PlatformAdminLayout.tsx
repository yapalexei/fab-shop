import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import FlashToast from '@/Components/FlashToast';
import NavLink from '@/Components/NavLink';
import ThemeToggle from '@/Components/ThemeToggle';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode } from 'react';

/**
 * Deliberately separate from AuthenticatedLayout - that layout's nav is
 * built entirely around a current shop (feature gates, shop switcher, per-
 * shop roles), none of which applies here. A platform admin may not even
 * belong to a shop.
 */
export default function PlatformAdminLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth } = usePage<PageProps>().props;

    return (
        <div className="app-shell flex flex-col bg-gray-100">
            <nav className="shrink-0 border-b border-gray-100 bg-white">
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-3">
                        <Link href={route('platform-admin.index')}>
                            <ApplicationLogo className="block h-9 w-auto fill-current text-gray-800" />
                        </Link>
                        <span className="rounded-full bg-gray-900 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider text-white">
                            Platform admin
                        </span>

                        <div className="ms-6 flex h-16 items-center gap-6">
                            <NavLink
                                href={route('platform-admin.index')}
                                active={route().current('platform-admin.index')}
                            >
                                Shop directory
                            </NavLink>
                            <NavLink
                                href={route('platform-admin.features.index')}
                                active={route().current(
                                    'platform-admin.features.*',
                                )}
                            >
                                Feature pricing
                            </NavLink>
                            <NavLink
                                href={route('platform-admin.plans.index')}
                                active={route().current(
                                    'platform-admin.plans.*',
                                )}
                            >
                                Plan settings
                            </NavLink>
                        </div>
                    </div>

                    <div className="flex items-center gap-1">
                        <ThemeToggle />

                        <Dropdown>
                            <Dropdown.Trigger>
                                <button
                                    type="button"
                                    className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                >
                                    {auth.user.name}
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
                            </Dropdown.Trigger>
                            <Dropdown.Content align="right">
                                {auth.user.current_shop_id && (
                                    <Dropdown.Link href={route('dashboard')}>
                                        Back to dashboard
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
            </nav>

            <FlashToast />

            {/* Same shell as AuthenticatedLayout: the nav stays put, the page scrolls here. */}
            <div
                scroll-region=""
                className="app-scroll min-h-0 flex-1 overflow-y-auto"
            >
                {header && (
                    <header className="bg-white shadow-sm">
                        <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            {header}
                        </div>
                    </header>
                )}

                <main>{children}</main>
            </div>
        </div>
    );
}
