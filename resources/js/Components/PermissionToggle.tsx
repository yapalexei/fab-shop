import { cn } from '@/lib/utils';
import { CircleCheck, UserIcon, X } from 'lucide-react';
import { useId } from 'react';

export type PermissionChoice = 'deny' | 'inherit' | 'allow';

const choices: {
    value: PermissionChoice;
    label: string;
    icon: typeof X;
    thumb: string;
}[] = [
    { value: 'deny', label: 'Deny', icon: X, thumb: 'bg-red-600' },
    {
        value: 'inherit',
        label: 'Role default',
        icon: UserIcon,
        thumb: 'bg-blue-600',
    },
    { value: 'allow', label: 'Allow', icon: CircleCheck, thumb: 'bg-green-600' },
];

/**
 * Three-way radio switch: deny (red X) / role default (blue user) / allow
 * (green check). The thumb slides to the chosen option. Big enough to tap on
 * a phone.
 */
export default function PermissionToggle({
    value,
    onChange,
    name,
    ariaLabel,
    disabledChoices = [],
}: {
    value: PermissionChoice;
    onChange: (value: PermissionChoice) => void;
    name?: string;
    ariaLabel: string;
    disabledChoices?: PermissionChoice[];
}) {
    const generated = useId();
    const group = name ?? generated;
    const index = choices.findIndex((c) => c.value === value);

    return (
        <div
            role="radiogroup"
            aria-label={ariaLabel}
            className="relative grid h-11 w-40 shrink-0 grid-cols-3 rounded-full border border-border bg-muted p-1 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring sm:h-8 sm:w-28"
        >
            <span
                aria-hidden
                className={cn(
                    'absolute inset-y-1 left-1 w-[calc((100%-0.5rem)/3)] rounded-full shadow-sm transition-all duration-200 ease-out',
                    choices[index].thumb,
                )}
                style={{ transform: `translateX(${index * 100}%)` }}
            />
            {choices.map(({ value: option, label, icon: Icon }) => {
                const disabled = disabledChoices.includes(option);
                const selected = option === value;

                return (
                    <label
                        key={option}
                        title={label}
                        className={cn(
                            'relative z-10 flex items-center justify-center rounded-full transition-colors',
                            disabled
                                ? 'cursor-not-allowed opacity-40'
                                : 'cursor-pointer',
                            selected
                                ? 'text-white'
                                : option === 'deny'
                                  ? 'text-red-600'
                                  : option === 'inherit'
                                    ? 'text-blue-600'
                                    : 'text-green-600',
                        )}
                    >
                        <input
                            type="radio"
                            name={group}
                            value={option}
                            checked={selected}
                            disabled={disabled}
                            onChange={() => onChange(option)}
                            className="peer sr-only"
                        />
                        <Icon className="size-5 sm:size-4" aria-hidden />
                        <span className="sr-only">{label}</span>
                    </label>
                );
            })}
        </div>
    );
}
