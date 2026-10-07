import Pagination, { PaginationLink } from '@/Components/Pagination';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Card } from '@/Components/ui/card';
import { router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export interface SecurityLogEntry {
    id: number;
    at: string;
    event: string;
    user: { name: string; email: string } | null;
    ip: string | null;
    details: Record<string, string>;
}

export interface SecurityLogPage {
    data: SecurityLogEntry[];
    links: PaginationLink[];
    total: number;
}

const formatTime = (iso: string) =>
    new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'medium',
    });

/**
 * The security log as a filterable table. Filtering is a plain GET back to
 * the same page, so the URL is shareable and pagination keeps the filters.
 */
export default function SecurityLogTable({
    log,
    events,
    filters,
    url,
}: {
    log: SecurityLogPage;
    events: string[];
    filters: { event?: string; q?: string };
    url: string;
}) {
    const [event, setEvent] = useState(filters.event ?? '');
    const [q, setQ] = useState(filters.q ?? '');

    const apply = (next: { event: string; q: string }) =>
        router.get(
            url,
            { event: next.event || undefined, q: next.q || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        apply({ event, q });
    };

    return (
        <Card>
            <form
                onSubmit={submit}
                className="flex flex-wrap items-center gap-3 border-b p-4"
            >
                <select
                    value={event}
                    onChange={(e) => {
                        setEvent(e.target.value);
                        apply({ event: e.target.value, q });
                    }}
                    aria-label="Event"
                    className="h-9 rounded-md border bg-background px-2 text-sm"
                >
                    <option value="">All events</option>
                    {events.map((name) => (
                        <option key={name} value={name}>
                            {name}
                        </option>
                    ))}
                </select>
                <TextInput
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder="Search name, email or IP"
                    className="w-64"
                    aria-label="Search"
                />
                <SecondaryButton type="submit">Search</SecondaryButton>
                <span className="ms-auto text-sm text-gray-500">
                    {log.total} entr{log.total === 1 ? 'y' : 'ies'}
                </span>
            </form>

            <div className="overflow-x-auto">
                <table className="w-full text-start text-sm">
                    <thead className="border-b text-xs uppercase text-gray-500">
                        <tr>
                            <th className="px-4 py-2 text-start">When</th>
                            <th className="px-4 py-2 text-start">Event</th>
                            <th className="px-4 py-2 text-start">Who</th>
                            <th className="px-4 py-2 text-start">IP</th>
                            <th className="px-4 py-2 text-start">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        {log.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="px-4 py-8 text-center text-gray-500"
                                >
                                    Nothing logged yet.
                                </td>
                            </tr>
                        )}
                        {log.data.map((entry) => (
                            <tr key={entry.id} className="border-b align-top">
                                <td className="whitespace-nowrap px-4 py-2">
                                    {formatTime(entry.at)}
                                </td>
                                <td className="px-4 py-2 font-medium">
                                    {entry.event}
                                </td>
                                <td className="px-4 py-2">
                                    {entry.user ? (
                                        <>
                                            {entry.user.name}
                                            <span className="block text-xs text-gray-500">
                                                {entry.user.email}
                                            </span>
                                        </>
                                    ) : (
                                        <span className="text-gray-500">
                                            {entry.details.email ?? '-'}
                                        </span>
                                    )}
                                </td>
                                <td className="whitespace-nowrap px-4 py-2 font-mono text-xs">
                                    {entry.ip ?? '-'}
                                </td>
                                <td className="px-4 py-2 text-xs text-gray-600">
                                    {Object.entries(entry.details)
                                        .filter(
                                            ([key]) =>
                                                entry.user || key !== 'email',
                                        )
                                        .map(([key, value]) => (
                                            <span
                                                key={key}
                                                className="me-3 inline-block"
                                            >
                                                {key.replace(/_/g, ' ')}:{' '}
                                                {value}
                                            </span>
                                        ))}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Pagination links={log.links} />
        </Card>
    );
}
