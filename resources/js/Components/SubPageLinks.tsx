import { Card } from '@/Components/ui/card';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export interface SubPage {
    title: string;
    description: string;
    href: string;
}

/**
 * The body of an index page: one card per sub-page, each linking to it.
 */
export default function SubPageLinks({ pages }: { pages: SubPage[] }) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {pages.map((page) => (
                <Link key={page.href} href={page.href} className="group">
                    <Card className="flex h-full items-start justify-between gap-3 p-5 transition-colors group-hover:bg-accent">
                        <span>
                            <span className="block text-base font-semibold text-gray-900">
                                {page.title}
                            </span>
                            <span className="mt-1 block text-sm text-gray-500">
                                {page.description}
                            </span>
                        </span>
                        <ChevronRight className="mt-1 h-5 w-5 shrink-0 text-gray-400" />
                    </Card>
                </Link>
            ))}
        </div>
    );
}

/**
 * Sits above a sub-page's content to lead back to its index.
 */
export function BackLink({ href, label }: { href: string; label: string }) {
    return (
        <Link
            href={href}
            className="mb-4 inline-flex items-center gap-1 text-sm text-gray-600 hover:text-foreground"
        >
            <ChevronLeft className="h-4 w-4" />
            {label}
        </Link>
    );
}
