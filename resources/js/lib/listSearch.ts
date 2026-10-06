import { useMemo, useState } from 'react';

/**
 * Client-side search for a list page that already has all its rows. A row
 * matches when every whitespace-separated word of the query appears
 * (case-insensitively) in at least one of the fields `fields` returns for it.
 */
export function useListSearch<T>(
    rows: T[],
    fields: (row: T) => (string | number | null | undefined)[],
) {
    const [query, setQuery] = useState('');

    const visible = useMemo(() => {
        const words = query.toLowerCase().split(/\s+/).filter(Boolean);

        if (words.length === 0) return rows;

        return rows.filter((row) => {
            const haystack = fields(row).join(' ').toLowerCase();

            return words.every((word) => haystack.includes(word));
        });
        // `fields` is an inline function; the rows and query are what matter.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [rows, query]);

    return { query, setQuery, visible, active: query.trim() !== '' };
}

export type ListSearchState = ReturnType<typeof useListSearch>;
