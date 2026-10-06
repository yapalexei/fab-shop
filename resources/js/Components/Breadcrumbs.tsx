import { Link } from '@inertiajs/react';

export interface Crumb {
    label: string;
    /** Omit on the current page (the last crumb). */
    href?: string;
}

/**
 * Page-header trail back up to the resource's list, e.g.
 * "Business partners > Edit business partner". Styled to sit where the
 * page's plain <h2> title did.
 */
export default function Breadcrumbs({ items }: { items: Crumb[] }) {
    return (
        <nav aria-label="Breadcrumb">
            <ol className="flex flex-wrap items-center gap-x-2 text-xl font-semibold leading-tight text-gray-800">
                {items.map((item, index) => (
                    <li key={index} className="flex items-center gap-x-2">
                        {index > 0 && (
                            <span aria-hidden="true" className="text-gray-400">
                                /
                            </span>
                        )}
                        {item.href ? (
                            <Link
                                href={item.href}
                                className="text-gray-500 hover:text-foreground"
                            >
                                {item.label}
                            </Link>
                        ) : (
                            <span aria-current="page">{item.label}</span>
                        )}
                    </li>
                ))}
            </ol>
        </nav>
    );
}
