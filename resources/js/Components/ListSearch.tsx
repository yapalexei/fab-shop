import { Input } from '@/Components/ui/input';
import type { ListSearchState } from '@/lib/listSearch';
import { Search, X } from 'lucide-react';

/** The search box for `useListSearch` - sits in a list page's toolbar. */
export default function ListSearch({
    search,
    placeholder = 'Search...',
    className = '',
}: {
    search: Pick<ListSearchState, 'query' | 'setQuery'>;
    placeholder?: string;
    className?: string;
}) {
    return (
        <div className={`relative w-full max-w-xs ${className}`}>
            <Search
                className="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden
            />
            <Input
                type="search"
                value={search.query}
                onChange={(e) => search.setQuery(e.target.value)}
                placeholder={placeholder}
                aria-label={placeholder}
                className="ps-8 pe-8 [&::-webkit-search-cancel-button]:hidden"
            />
            {search.query && (
                <button
                    type="button"
                    onClick={() => search.setQuery('')}
                    aria-label="Clear search"
                    className="absolute end-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                >
                    <X className="size-4" />
                </button>
            )}
        </div>
    );
}
