import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import { Alert } from '@/Components/ui/alert';
import { PropsWithChildren } from 'react';

/**
 * One place to explain what a catalog/list page is for, in two forms: a
 * banner shown inline while the list is empty (when a plain "no X yet" row
 * has nothing else useful to say anyway), and a "?" icon that opens the
 * same text in a popover once there's real content that shouldn't be
 * pushed down by a paragraph every time the page loads.
 */
export default function PageHelp({
    empty = false,
    children,
}: PropsWithChildren<{ empty?: boolean }>) {
    if (empty) {
        return (
            <Alert className="border-indigo-100 bg-indigo-50 text-indigo-900 dark:border-indigo-700 dark:bg-indigo-900 dark:text-indigo-100">
                {children}
            </Alert>
        );
    }

    return (
        <Popover>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="inline-flex h-6 w-6 items-center justify-center rounded-full border border-input text-xs font-semibold text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                    aria-label="What is this page for?"
                    title="What is this page for?"
                >
                    ?
                </button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-80 space-y-2 text-sm text-muted-foreground">
                {children}
            </PopoverContent>
        </Popover>
    );
}
