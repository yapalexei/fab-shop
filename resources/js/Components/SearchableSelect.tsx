import {
    Combobox,
    ComboboxButton,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/react';
import { ReactNode, useState } from 'react';

export interface SearchableOption {
    value: string;
    label: string;
    /** Heading to list this option under; options must already be ordered by group. */
    group?: string;
    disabled?: boolean;
}

/**
 * A select you can type into to filter - the app's replacement for a native
 * <select>, with the same value/onChange contract (string values).
 *
 * - `emptyLabel` adds an always-present "no selection" choice with value ''
 *   (e.g. "Custom (not in catalog)"); leave it out when a value is mandatory.
 * - `emptyState` is shown under the control when there's nothing to choose
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
    const [query, setQuery] = useState('');

    const all: SearchableOption[] =
        emptyLabel === undefined
            ? options
            : [{ value: '', label: emptyLabel }, ...options];
    const needle = query.trim().toLowerCase();
    // The empty choice stays listed while searching so it can still be picked.
    const filtered =
        needle === ''
            ? all
            : all.filter(
                  (o) =>
                      o.value === '' || o.label.toLowerCase().includes(needle),
              );
    const selected = all.find((o) => o.value === value) ?? null;
    const nothingToChoose = options.length === 0;

    return (
        <div className={wrapperClassName}>
            <Combobox
                value={selected?.value ?? ''}
                onChange={(v: string | null) => onChange(v ?? '')}
                onClose={() => setQuery('')}
                disabled={disabled}
            >
                <div className="relative">
                    <ComboboxInput
                        id={id}
                        aria-label={ariaLabel}
                        className={`w-full pe-9 disabled:bg-gray-50 disabled:text-gray-500 ${className}`}
                        displayValue={(v: string) =>
                            all.find((o) => o.value === v)?.label ?? ''
                        }
                        onChange={(e) => setQuery(e.target.value)}
                        onFocus={(e) => e.target.select()}
                        placeholder="Search..."
                        autoComplete="off"
                    />
                    <ComboboxButton className="absolute inset-y-0 end-0 flex items-center pe-2 text-gray-400">
                        <svg
                            className="h-4 w-4"
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
                    </ComboboxButton>
                    {required && (
                        // Native validation anchor: the combobox's text input
                        // holds a label, not the value, so mirror the value here.
                        <input
                            required
                            tabIndex={-1}
                            aria-hidden="true"
                            value={selected?.value ?? ''}
                            onChange={() => {}}
                            className="pointer-events-none absolute inset-0 h-full w-full opacity-0"
                        />
                    )}
                    <ComboboxOptions
                        anchor="bottom start"
                        className="z-50 max-h-60 w-[var(--input-width)] overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg empty:invisible"
                    >
                        {needle !== '' &&
                            filtered.every((o) => o.value === '') && (
                                <div className="px-3 py-2 text-gray-500">
                                    No matches
                                </div>
                            )}
                        {filtered.map((o, i) => (
                            <div key={o.value}>
                                {o.group !== undefined &&
                                    o.group !== filtered[i - 1]?.group && (
                                        <div className="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                            {o.group}
                                        </div>
                                    )}
                                <ComboboxOption
                                    value={o.value}
                                    disabled={o.disabled}
                                    className="cursor-pointer px-3 py-2 text-gray-700 data-[focus]:bg-indigo-50 data-[selected]:font-semibold data-[disabled]:cursor-not-allowed data-[disabled]:text-gray-300"
                                >
                                    {o.label}
                                </ComboboxOption>
                            </div>
                        ))}
                    </ComboboxOptions>
                </div>
            </Combobox>
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
