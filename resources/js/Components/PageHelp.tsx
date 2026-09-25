import Dropdown from '@/Components/Dropdown';
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
            <div className="rounded-md border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-900">
                {children}
            </div>
        );
    }

    return (
        <Dropdown>
            <Dropdown.Trigger>
                <button
                    type="button"
                    className="inline-flex h-6 w-6 items-center justify-center rounded-full border border-gray-300 text-xs font-semibold text-gray-500 transition duration-150 ease-in-out hover:bg-gray-50 hover:text-gray-700"
                    aria-label="What is this page for?"
                    title="What is this page for?"
                >
                    ?
                </button>
            </Dropdown.Trigger>
            <Dropdown.Content
                align="left"
                width="80"
                contentClasses="bg-white p-4 text-sm text-gray-600 space-y-2"
            >
                {children}
            </Dropdown.Content>
        </Dropdown>
    );
}
