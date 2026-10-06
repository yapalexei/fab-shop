import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { InertiaLinkProps, Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

/**
 * Thin, API-stable wrapper over shadcn's DropdownMenu (Radix) so the nav
 * keeps its `<Dropdown><Dropdown.Trigger/><Dropdown.Content/></Dropdown>`
 * shape. Focus management, keyboard navigation, outside-click and Escape
 * come from Radix rather than hand-rolled state.
 */
const Dropdown = ({
    className,
    children,
}: PropsWithChildren<{ className?: string }>) => (
    <DropdownMenu modal={false}>
        <div className={cn('relative', className)}>{children}</div>
    </DropdownMenu>
);

const Trigger = ({
    className,
    children,
}: PropsWithChildren<{ className?: string }>) => (
    <DropdownMenuTrigger asChild>
        <div className={className}>{children}</div>
    </DropdownMenuTrigger>
);

const widths = { '48': 'w-48', '56': 'w-56', '80': 'w-80' };

const Content = ({
    align = 'right',
    width = '48',
    children,
}: PropsWithChildren<{
    align?: 'left' | 'right';
    width?: keyof typeof widths;
    /** @deprecated styling comes from the theme now; accepted for old call sites. */
    contentClasses?: string;
}>) => (
    <DropdownMenuContent
        align={align === 'left' ? 'start' : 'end'}
        className={cn('max-w-[calc(100vw-2rem)]', widths[width])}
    >
        {children}
    </DropdownMenuContent>
);

const DropdownLink = ({
    className,
    children,
    ...props
}: InertiaLinkProps) => (
    <DropdownMenuItem asChild>
        <Link {...props} className={cn('w-full cursor-pointer text-start', className)}>
            {children}
        </Link>
    </DropdownMenuItem>
);

Dropdown.Trigger = Trigger;
Dropdown.Content = Content;
Dropdown.Link = DropdownLink;

export default Dropdown;
