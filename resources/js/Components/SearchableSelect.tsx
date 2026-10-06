import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/Components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import { cn } from '@/lib/utils';
import { Check, ChevronDown, TriangleAlert } from 'lucide-react';
import { ReactNode, useState } from 'react';

export interface SearchableOption {
    value: string;
    label: string;
    /** Heading to list this option under; options must already be ordered by group. */
    group?: string;
    disabled?: boolean;
}

// cmdk can't use '' as an item value, so the "no selection" choice gets a stand-in.
const EMPTY = '__empty__';

/**
 * A drop-in replacement for a native <select> (same string value/onChange
 * contract). The field itself is a button showing the current choice;
 * clicking it opens a popover with a search box and the options below it
 * (shadcn Popover + Command, so keyboard navigation, type-to-filter and
 * focus handling come from the library).
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
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    const all: SearchableOption[] =
        emptyLabel === undefined
            ? options
            : [{ value: '', label: emptyLabel }, ...options];
    const selected = all.find((o) => o.value === value) ?? null;
    const nothingToChoose = options.length === 0;
    // A caller's own vertical padding (compact variants) must win over ours.
    const padding = /\bpy-/.test(className) ? 'px-3 pe-9' : 'py-2 ps-3 pe-9';

    // The empty choice stays listed while searching so it can still be picked.
    const needle = query.trim().toLowerCase();
    const filtered =
        needle === ''
            ? all
            : all.filter(
                  (o) =>
                      o.value === '' || o.label.toLowerCase().includes(needle),
              );
    const noMatches = needle !== '' && filtered.every((o) => o.value === '');

    const pick = (v: string) => {
        onChange(v === EMPTY ? '' : v);
        setOpen(false);
    };

    const groups: { heading?: string; items: SearchableOption[] }[] = [];
    for (const option of filtered) {
        const last = groups[groups.length - 1];
        if (last && last.heading === option.group) last.items.push(option);
        else groups.push({ heading: option.group, items: [option] });
    }

    return (
        <div className={wrapperClassName}>
            <Popover
                open={open}
                onOpenChange={(next) => {
                    setOpen(next);
                    if (!next) setQuery('');
                }}
            >
                <div className="relative">
                    <PopoverTrigger asChild>
                        <button
                            type="button"
                            id={id}
                            aria-label={ariaLabel}
                            aria-haspopup="listbox"
                            disabled={disabled}
                            className={cn(
                                'relative block w-full truncate border bg-background text-start transition-colors hover:border-foreground/40 hover:bg-accent/40 disabled:opacity-60',
                                padding,
                                className,
                                value === '' && 'text-muted-foreground',
                            )}
                        >
                            {selected?.label || ' '}
                            <ChevronDown
                                className="pointer-events-none absolute inset-e-2 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </button>
                    </PopoverTrigger>

                    {required && (
                        // Native validation anchor: the button has no value of
                        // its own, so mirror the selection in a hidden
                        // required input.
                        <input
                            required
                            tabIndex={-1}
                            aria-hidden="true"
                            value={selected?.value ?? ''}
                            onChange={() => {}}
                            className="pointer-events-none absolute inset-0 h-full w-full opacity-0"
                        />
                    )}
                </div>

                <PopoverContent
                    align="start"
                    className="w-(--radix-popover-trigger-width) min-w-48 p-0"
                >
                    <Command shouldFilter={false} value={value === '' ? EMPTY : value}>
                        <CommandInput
                            value={query}
                            onValueChange={setQuery}
                            placeholder="Search..."
                            aria-label="Search options"
                        />
                        <CommandList>
                            {noMatches && (
                                <CommandEmpty>No matches</CommandEmpty>
                            )}
                            {groups.map((group, i) => (
                                <CommandGroup key={i} heading={group.heading}>
                                    {group.items.map((option) => (
                                        <CommandItem
                                            key={option.value || EMPTY}
                                            value={option.value || EMPTY}
                                            disabled={option.disabled}
                                            onSelect={pick}
                                            className={cn(
                                                option.value === value &&
                                                    'font-semibold',
                                            )}
                                        >
                                            {option.label}
                                            {option.value === value && (
                                                <Check className="ms-auto size-4" />
                                            )}
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            ))}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>

            {nothingToChoose && emptyState && (
                <p
                    role="alert"
                    className="mt-1 flex items-start gap-1 text-xs text-amber-700"
                >
                    <TriangleAlert
                        className="mt-px size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    <span>{emptyState}</span>
                </p>
            )}
        </div>
    );
}
