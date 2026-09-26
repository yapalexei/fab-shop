import { Link } from '@inertiajs/react';

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/**
 * Renders Laravel's own paginator `links` array (numbered pages plus a
 * Previous/Next pair, whose labels arrive as `&laquo; Previous` /
 * `Next &raquo;`) - preserveScroll so acting on a row doesn't jump the page
 * back to the top, and every link keeps whatever filters are already in the
 * URL since Laravel includes the current query string on each link itself.
 */
export default function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav className="flex flex-wrap items-center gap-1 px-6 py-4">
            {links.map((link, index) =>
                link.url === null ? (
                    <span
                        key={index}
                        className="rounded-md px-3 py-1 text-sm text-gray-400"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        className={`rounded-md px-3 py-1 text-sm ${
                            link.active
                                ? 'bg-indigo-600 text-white'
                                : 'text-gray-600 hover:bg-gray-100'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ),
            )}
        </nav>
    );
}
