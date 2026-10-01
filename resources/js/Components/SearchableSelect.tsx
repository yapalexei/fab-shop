import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { KeyboardEvent, ReactNode, useEffect, useRef, useState } from 'react';

export interface SearchableOption {
    value: string;
    label: string;
    /** Heading to list this option under; options must already be ordered by group. */
    group?: string;
    disabled?: boolean;
}

/**
 * A drop-in replacement for a native <select> (same string value/onChange
 * contract). The field itself is a button showing the current choice;
 * clicking it opens a panel with a search box on the first row and the
 * options below it. Focus lands on the first option, not the search box:
 * pick it straight away, use the arrow keys, or click into the search box
 * (or just start typing) to filter a long list.
 *
 * - `emptyLabel` adds an always-present "no selection" choice with value ''
 *   (e.g. "Custom (not in catalog)"); leave it out when a value is mandatory.
 * - `emptyState` is shown under the field when there's nothing to choose
 *   from at all - use it to tell the user to create the items first, with a
 *   link to where they're created.
 * - `required` blocks form submission while nothing is selected, like the
 *   native attribute.
 */
export default function SearchableSelect({
    value,
    onChange,
    options,
    emptyLabel,
    emptyState,
    required = false,
    disabled = false,
    id,
    ariaLabel,
    className = '',
    wrapperClassName = '',
}: {
    value: string;
    onChange: (value: string) => void;
    options: SearchableOption[];
    emptyLabel?: string;
    emptyState?: ReactNode;
    required?: boolean;
    disabled?: boolean;
    id?: string;
    ariaLabel?: string;
    className?: string;
    wrapperClassName?: string;
}) {
    const all: SearchableOption[] =
        emptyLabel === undefined
            ? options
            : [{ value: '', label: emptyLabel }, ...options];
    const selected = all.find((o) => o.value === value) ?? null;
    const nothingToChoose = options.length === 0;
    // A caller's own vertical padding (compact variants) must win over ours.
    const padding = /\bpy-/.test(className) ? 'px-3 pe-9' : 'py-2 ps-3 pe-9';

    return (
        <div className={wrapperClassName}>
            <Popover className="relative">
                <PopoverButton
                    id={id}
                    aria-label={ariaLabel}
                    aria-haspopup="listbox"
                    disabled={disabled}
                    className={`relative block w-full truncate border bg-white text-start disabled:bg-gray-50 disabled:text-gray-500 ${padding} ${className} ${
                        value === '' ? 'text-gray-500' : ''
                    }`}
                >
                    {selected?.label || ' '}
                    <svg
                        className="pointer-events-none absolute end-2 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            fillRule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clipRule="evenodd"
                        />
                    </svg>
                </PopoverButton>

                {required && (
                    // Native validation anchor: the button has no value of its
                    // own, so mirror the selection in a hidden required input.
                    <input
                        required
                        tabIndex={-1}
                        aria-hidden="true"
                        value={selected?.value ?? ''}
                        onChange={() => {}}
                        className="pointer-events-none absolute inset-0 h-full w-full opacity-0"
                    />
                )}

                <PopoverPanel
                    anchor="bottom start"
                    className="z-50 w-[var(--button-width)] min-w-48 rounded-md border border-gray-200 bg-white shadow-lg"
                >
                    {({ close }) => (
                        <OptionsPanel
                            options={all}
                            value={value}
                            onPick={(v) => {
                                onChange(v);
                                close();
                            }}
                        />
                    )}
                </PopoverPanel>
            </Popover>

            {nothingToChoose && emptyState && (
                <p
                    role="alert"
                    className="mt-1 flex items-start gap-1 text-xs text-amber-700"
                >
                    <svg
                        className="mt-px h-3.5 w-3.5 shrink-0"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            fillRule="evenodd"
                            d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z"
                            clipRule="evenodd"
                        />
                    </svg>
                    <span>{emptyState}</span>
                </p>
            )}
        </div>
    );
}

/**
 * The open panel: search row, then the options. Mounted only while open, so
 * the query and highlight start fresh every time.
 */
function OptionsPanel({
    options,
    value,
    onPick,
}: {
    options: SearchableOption[];
    value: string;
    onPick: (value: string) => void;
}) {
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const listRef = useRef<HTMLDivElement>(null);
    const searchRef = useRef<HTMLInputElement>(null);

    const needle = query.trim().toLowerCase();
    // The empty choice stays listed while searching so it can still be picked.
    const filtered =
        needle === ''
            ? options
            : options.filter(
                  (o) =>
                      o.value === '' || o.label.toLowerCase().includes(needle),
              );
    const noMatches = needle !== '' && filtered.every((o) => o.value === '');

    // Land on the list (first selectable row highlighted), not the search box.
    useEffect(() => {
        // preventScroll: the panel may not be positioned yet, and focusing
        // it would otherwise scroll the page to wherever it momentarily is.
        listRef.current?.focus({ preventScroll: true });
        const current = options.findIndex(
            (o) => o.value === value && !o.disabled,
        );
        setActive(
            current >= 0 ? current : options.findIndex((o) => !o.disabled),
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Scroll only the list itself - scrollIntoView would also scroll the page.
    useEffect(() => {
        const list = listRef.current;
        const row = list?.querySelector<HTMLElement>(
            `[data-index="${active}"]`,
        );
        if (!list || !row) return;
        if (row.offsetTop < list.scrollTop) {
            list.scrollTop = row.offsetTop;
        } else if (
            row.offsetTop + row.offsetHeight >
            list.scrollTop + list.clientHeight
        ) {
            list.scrollTop =
                row.offsetTop + row.offsetHeight - list.clientHeight;
        }
    }, [active]);

    const move = (direction: 1 | -1, from = active) => {
        let next = from + direction;
        while (next >= 0 && next < filtered.length && filtered[next].disabled) {
            next += direction;
        }
        if (next >= 0 && next < filtered.length) setActive(next);
    };

    const onListKeyDown = (event: KeyboardEvent) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            move(1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            move(-1);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const option = filtered[active];
            if (option && !option.disabled) onPick(option.value);
        } else if (
            event.key.length === 1 &&
            !event.ctrlKey &&
            !event.metaKey &&
            !event.altKey
        ) {
            // Typing anywhere in the panel searches.
            searchRef.current?.focus({ preventScroll: true });
        }
    };

    return (
        <div onKeyDown={onListKeyDown}>
            <div className="border-b border-gray-100 p-2">
                <input
                    ref={searchRef}
                    type="text"
                    value={query}
                    onChange={(e) => {
                        setQuery(e.target.value);
                        setActive(0);
                    }}
                    onKeyDown={(e) => {
                        // Keep the list's own key handling out of the search
                        // box, but let Escape through so the panel still closes.
                        if (e.key === 'ArrowDown' || e.key === 'Enter') {
                            e.stopPropagation();
                        }
                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            listRef.current?.focus({ preventScroll: true });
                        } else if (e.key === 'Enter') {
                            e.preventDefault();
                            const option = filtered[active];
                            if (option && !option.disabled) {
                                onPick(option.value);
                            }
                        }
                    }}
                    placeholder="Search..."
                    autoComplete="off"
                    aria-label="Search options"
                    className="block w-full rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
            <div
                ref={listRef}
                role="listbox"
                tabIndex={-1}
                className="relative max-h-60 overflow-auto py-1 text-sm focus:outline-none"
            >
                {noMatches && (
                    <div className="px-3 py-2 text-gray-500">No matches</div>
                )}
                {filtered.map((option, index) => (
                    <div key={option.value}>
                        {option.group !== undefined &&
                            option.group !== filtered[index - 1]?.group && (
                                <div className="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                    {option.group}
                                </div>
                            )}
                        <div
                            role="option"
                            data-index={index}
                            aria-selected={option.value === value}
                            aria-disabled={option.disabled}
                            onMouseEnter={() =>
                                !option.disabled && setActive(index)
                            }
                            onClick={() =>
                                !option.disabled && onPick(option.value)
                            }
                            className={`px-3 py-2 ${
                                option.disabled
                                    ? 'cursor-not-allowed text-gray-300'
                                    : 'cursor-pointer text-gray-700'
                            } ${index === active && !option.disabled ? 'bg-indigo-50' : ''} ${
                                option.value === value ? 'font-semibold' : ''
                            }`}
                        >
                            {option.label}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
