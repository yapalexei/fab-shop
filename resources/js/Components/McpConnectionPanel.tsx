import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Checkbox } from '@/Components/ui/checkbox';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export interface McpToken {
    id: number;
    name: string;
    /** Whether this token may make changes, or only read. */
    can_change: boolean;
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string;
}

export interface McpData {
    url: string;
    /** Every resource an AI can be given, and which actions exist for it. */
    grantable: Record<string, { label: string; actions: string[] }>;
    enabled: boolean;
    /** What it has been given now, as "resource.action" (e.g. "quotes.create"). */
    permissions: string[];
    tokens: McpToken[];
    /** What the connected AI has done lately, newest first. */
    recent_activity: { id: number; description: string; created_at: string }[];
    /** Attempts the safety checks stopped in the last 7 days. */
    refused_last_week: number;
    /** The secret of a token created a moment ago - shown once, then gone. */
    new_token: string | null;
    default_token_days: number;
}

interface Example {
    prompt: string;
    /** Permissions ("resource.action") the AI needs for this to work. */
    needs: string[];
}

const exampleGroups: { title: string; examples: Example[] }[] = [
    {
        title: 'Start here',
        examples: [
            {
                prompt: 'What can you do in my shop? Check what access you have.',
                needs: [],
            },
        ],
    },
    {
        title: 'Look things up',
        examples: [
            {
                prompt: 'Which quotes have gone stale and need a follow-up?',
                needs: ['quotes.view'],
            },
            {
                prompt: 'Find the customer Ridgeline Ranch and show me their open quotes.',
                needs: ['customers.view', 'quotes.view'],
            },
            {
                prompt: "What's on the job board right now, and which jobs are overdue?",
                needs: ['jobs.view'],
            },
        ],
    },
    {
        title: 'Create and change things',
        examples: [
            {
                prompt: 'Add a customer called Dana Whitfield, dana@ridgeline.example, 555-0100.',
                needs: ['customers.create'],
            },
            {
                prompt: 'Draft a quote for Dana Whitfield: 6 sheets of 1/4in steel plate and powder coating. Use my catalog for the costs.',
                needs: ['quotes.create', 'customers.view', 'materials.view', 'services.view'],
            },
            {
                prompt: 'Change the due date on job J-0003 to December 15 and assign it to Jamie.',
                needs: ['jobs.update'],
            },
            {
                prompt: 'Move job J-0004 to the next stage.',
                needs: ['jobs.update', 'job-stages.view'],
            },
            {
                prompt: 'Add a supplier called Coastal Metal Supply that sells 1/4in steel plate at $48 a sheet.',
                needs: ['business-partners.create', 'materials.view'],
            },
        ],
    },
    {
        title: 'Hand it a customer email',
        examples: [
            {
                prompt: "Here's an email from a customer: [paste it]. Find or add them as a customer and draft a quote for what they asked for.",
                needs: ['customers.view', 'customers.create', 'quotes.create', 'materials.view'],
            },
        ],
    },
];

const resourceNotes: Record<string, string> = {
    financials: 'Shows quote totals, costs, markup and job prices.',
    'business-partners': 'Shows your suppliers.',
};

const date = (value: string | null) =>
    value ? new Date(value).toLocaleDateString() : '-';

function CopyBlock({ label, text }: { label: string; text: string }) {
    const [copied, setCopied] = useState(false);

    return (
        <div>
            <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-gray-700">{label}</p>
                <button
                    type="button"
                    className="text-xs text-indigo-600 hover:underline"
                    onClick={() => {
                        navigator.clipboard?.writeText(text);
                        setCopied(true);
                        setTimeout(() => setCopied(false), 1500);
                    }}
                >
                    {copied ? 'Copied' : 'Copy'}
                </button>
            </div>
            <pre className="mt-1 overflow-x-auto rounded-md bg-gray-100 p-3 text-xs text-gray-800">
                {text}
            </pre>
        </div>
    );
}

function PromptExample({
    prompt,
    missing,
}: {
    prompt: string;
    /** What this needs that the connection hasn't been given (empty = ready). */
    missing: string[];
}) {
    const [copied, setCopied] = useState(false);
    const ready = missing.length === 0;

    return (
        <li
            className={`flex items-start justify-between gap-3 rounded-md border px-3 py-2 text-sm ${ready ? 'bg-white' : 'bg-gray-50 text-gray-500'}`}
        >
            <span>
                &ldquo;{prompt}&rdquo;
                {!ready && (
                    <span className="mt-1 block text-xs text-amber-700">
                        Not enabled yet - needs: {missing.join(', ')}
                    </span>
                )}
            </span>
            <button
                type="button"
                className="shrink-0 text-xs text-indigo-600 hover:underline"
                onClick={() => {
                    navigator.clipboard?.writeText(prompt);
                    setCopied(true);
                    setTimeout(() => setCopied(false), 1500);
                }}
            >
                {copied ? 'Copied' : 'Copy'}
            </button>
        </li>
    );
}

/**
 * "Connect your AI": lets the shop's own AI client (Claude Desktop/Code,
 * ChatGPT, a custom agent) read the shop's customers, quotes and jobs over
 * MCP. Read-only; access is chosen here and can be changed or revoked any
 * time. The token is shown once, right after it is created.
 */
export default function McpConnectionPanel({
    mcp,
    available,
}: {
    mcp: McpData;
    available: boolean;
}) {
    const [selected, setSelected] = useState<string[]>(mcp.permissions);
    const [changesOpen, setChangesOpen] = useState(
        mcp.permissions.some((p) => !p.endsWith('.view')),
    );
    const tokenForm = useForm({
        name: '',
        expires_in_days: '',
        // Off unless ticked: a new token can only read.
        allow_changes: false,
    });

    const has = (permission: string) => selected.includes(permission);

    // Allowing a change also allows seeing it (you can't change what you
    // can't see), and taking away the view takes the changes with it. The
    // server enforces the same, this just keeps the boxes honest.
    const toggle = (permission: string, on: boolean) =>
        setSelected((current) => {
            const [resource, action] = permission.split('.');
            let next = on
                ? [...new Set([...current, permission])]
                : current.filter((p) => p !== permission);

            if (on && action !== 'view') {
                next = [...new Set([...next, `${resource}.view`])];
            }
            if (!on && action === 'view') {
                next = next.filter((p) => !p.startsWith(`${resource}.`));
            }

            return next;
        });

    const changeable = Object.entries(mcp.grantable).filter(([, row]) =>
        row.actions.some((a) => a !== 'view'),
    );
    const changesGranted = selected.filter((p) => !p.endsWith('.view')).length;

    const dirty =
        selected.length !== mcp.permissions.length ||
        selected.some((p) => !mcp.permissions.includes(p));

    // What a permission is called to the owner: "create Customers".
    const permissionName = (permission: string) => {
        const [resource, action] = permission.split('.');
        return `${action} ${mcp.grantable[resource]?.label ?? resource}`;
    };
    const missingFor = (needs: string[]) =>
        needs
            .filter((permission) => !mcp.permissions.includes(permission))
            .map(permissionName);

    const token = mcp.new_token ?? 'YOUR_TOKEN';

    const claudeCode = `claude mcp add --transport http fab-shop ${mcp.url} \\\n  --header "Authorization: Bearer ${token}"`;
    const claudeDesktop = JSON.stringify(
        {
            mcpServers: {
                'fab-shop': {
                    command: 'npx',
                    args: [
                        'mcp-remote',
                        mcp.url,
                        '--header',
                        `Authorization: Bearer ${token}`,
                    ],
                },
            },
        },
        null,
        2,
    );

    const createToken = (e: React.FormEvent) => {
        e.preventDefault();
        tokenForm.post(route('shop.ai.agent.tokens.store'), {
            preserveScroll: true,
            onSuccess: () => tokenForm.reset(),
        });
    };

    const turnOff = () => {
        if (
            !confirm(
                'Turn off the AI connection? Every token stops working and is deleted.',
            )
        ) {
            return;
        }
        router.delete(route('shop.ai.agent.destroy'), { preserveScroll: true });
    };

    return (
        <div className="space-y-6 bg-white p-4 shadow-sm sm:rounded-lg sm:p-8">
            <div>
                <h3 className="text-sm font-semibold text-gray-900">
                    Connect your AI to your shop
                </h3>
                <p className="mt-1 text-sm text-gray-500">
                    Let your own AI (Claude, ChatGPT, or any tool that supports
                    MCP) look things up in your shop - find a customer, list
                    stale quotes, check what&apos;s on the board. It is
                    read-only: it cannot send, change or delete anything. You
                    choose what it can see, and you can cut it off at any time.
                </p>
                <div className="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                    <p className="font-semibold">How this stays safe</p>
                    <p className="mt-1">
                        An AI can be tricked by text it reads - a customer
                        email or a note can contain instructions aimed at it. It
                        is risky when one AI can (1) see your private data, (2)
                        read text from outside, and (3) send things out or
                        change records. So:
                    </p>
                    <ul className="mt-1 list-disc space-y-0.5 ps-5">
                        <li>
                            Tokens are <strong>read-only</strong> unless you
                            tick the box. Use a read-only token for any AI that
                            also browses the web or reads your email.
                        </li>
                        <li>
                            Quotes and customers the AI writes are{' '}
                            <strong>held for your review</strong> - a quote
                            can&apos;t be sent until you&apos;ve read it.
                        </li>
                        <li>
                            It can&apos;t put links, markup or hidden text into
                            a record, and it can&apos;t delete or send anything.
                        </li>
                        <li>
                            Changes are capped per hour, and everything it does
                            is listed under Recent AI activity.
                        </li>
                    </ul>
                </div>
                <p className="mt-2 text-xs text-gray-500">
                    What it reads is sent to whichever AI service you connect.
                    Notes and descriptions in your records were typed by staff
                    and customers, so treat what an AI says about them with the
                    same care as any email.
                </p>
            </div>

            {!mcp.enabled ? (
                <PrimaryButton
                    type="button"
                    disabled={!available}
                    onClick={() =>
                        router.post(
                            route('shop.ai.agent.store'),
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    Turn on AI connection
                </PrimaryButton>
            ) : (
                <fieldset disabled={!available} className="space-y-6">
                    <div>
                        <p className="text-sm font-medium text-gray-900">
                            What your AI can see
                        </p>
                        <div className="mt-2 grid gap-2 sm:grid-cols-2">
                            {Object.entries(mcp.grantable).map(
                                ([resource, row]) => (
                                    <label
                                        key={resource}
                                        className="flex items-start gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={has(`${resource}.view`)}
                                            onCheckedChange={(checked) =>
                                                toggle(
                                                    `${resource}.view`,
                                                    checked === true,
                                                )
                                            }
                                            className="mt-0.5"
                                        />
                                        <span>
                                            {row.label}
                                            {resourceNotes[resource] && (
                                                <span className="block text-xs text-gray-500">
                                                    {resourceNotes[resource]}
                                                </span>
                                            )}
                                        </span>
                                    </label>
                                ),
                            )}
                        </div>
                        <details
                            className="mt-4 rounded-md border"
                            open={changesOpen}
                            onToggle={(e) =>
                                setChangesOpen(
                                    (e.currentTarget as HTMLDetailsElement).open,
                                )
                            }
                        >
                            <summary className="cursor-pointer select-none px-3 py-2 text-sm font-medium text-gray-900">
                                What your AI can change
                                <span className="ms-2 text-xs font-normal text-gray-500">
                                    {changesGranted === 0
                                        ? 'Nothing - read-only'
                                        : `${changesGranted} change permission${changesGranted === 1 ? '' : 's'} on`}
                                </span>
                            </summary>
                            <div className="space-y-3 border-t px-3 py-3">
                                <p className="rounded-md bg-amber-50 p-3 text-xs dark:bg-amber-900">
                                    Letting your AI change things means it
                                    edits real records. What it reads - a
                                    customer&apos;s email, a note - can try to
                                    steer it, so turn on only what you need and
                                    check its work. Every change it makes is
                                    recorded in your history as made by the AI
                                    connection.
                                </p>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="text-xs text-gray-500">
                                            <tr>
                                                <th className="py-1 pr-4 font-medium">
                                                    Resource
                                                </th>
                                                <th className="px-3 py-1 text-center font-medium">
                                                    Create
                                                </th>
                                                <th className="px-3 py-1 text-center font-medium">
                                                    Update
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {changeable.map(
                                                ([resource, row]) => (
                                                    <tr
                                                        key={resource}
                                                        className="border-t"
                                                    >
                                                        <td className="py-2 pr-4">
                                                            {row.label}
                                                            {resource ===
                                                                'quotes' && (
                                                                <span className="block text-xs text-gray-500">
                                                                    Drafts only
                                                                    - never
                                                                    sent
                                                                </span>
                                                            )}
                                                            {resource ===
                                                                'jobs' && (
                                                                <span className="block text-xs text-gray-500">
                                                                    Due date, assignee and moving between stages - including Shipped and Invoiced, which mark the job shipped or invoiced
                                                                </span>
                                                            )}
                                                            {resource ===
                                                                'business-partners' && (
                                                                <span className="block text-xs text-gray-500">
                                                                    Includes
                                                                    supplier
                                                                    prices
                                                                </span>
                                                            )}
                                                        </td>
                                                        {(
                                                            ['create', 'update'] as const
                                                        ).map((action) => (
                                                            <td
                                                                key={action}
                                                                className="px-3 py-2 text-center"
                                                            >
                                                                {row.actions.includes(
                                                                    action,
                                                                ) ? (
                                                                    <Checkbox
                                                                        aria-label={`${action} ${row.label}`}
                                                                        checked={has(
                                                                            `${resource}.${action}`,
                                                                        )}
                                                                        onCheckedChange={(
                                                                            checked,
                                                                        ) =>
                                                                            toggle(
                                                                                `${resource}.${action}`,
                                                                                checked ===
                                                                                    true,
                                                                            )
                                                                        }
                                                                    />
                                                                ) : (
                                                                    <span className="text-gray-300">
                                                                        -
                                                                    </span>
                                                                )}
                                                            </td>
                                                        ))}
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                                <p className="text-xs text-gray-500">
                                    Never allowed, whatever you choose:
                                    deleting anything, sending or emailing
                                    quotes, marking quotes won or lost,
                                    cancelling jobs, and anything to do with your team,
                                    roles, billing, add-ons or shop settings.
                                </p>
                            </div>
                        </details>

                        <div className="mt-3">
                            <SecondaryButton
                                type="button"
                                disabled={!dirty}
                                onClick={() =>
                                    router.put(
                                        route('shop.ai.agent.update'),
                                        { permissions: selected },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Save access
                            </SecondaryButton>
                        </div>
                    </div>

                    {mcp.new_token && (
                        <div
                            role="status"
                            className="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                        >
                            <p className="font-semibold">
                                Copy your token now - it is shown only once.
                            </p>
                            <pre className="mt-2 overflow-x-auto rounded bg-white p-2 text-xs">
                                {mcp.new_token}
                            </pre>
                        </div>
                    )}

                    <form onSubmit={createToken} className="space-y-3">
                        <p className="text-sm font-medium text-gray-900">
                            Connect a new AI client
                        </p>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <div className="sm:col-span-2">
                                <InputLabel htmlFor="token_name" value="Name" />
                                <TextInput
                                    id="token_name"
                                    className="mt-1 block w-full"
                                    placeholder="e.g. Claude Desktop on the office laptop"
                                    value={tokenForm.data.name}
                                    onChange={(e) =>
                                        tokenForm.setData('name', e.target.value)
                                    }
                                />
                                <InputError
                                    message={tokenForm.errors.name}
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="token_days"
                                    value="Expires in (days)"
                                />
                                <TextInput
                                    id="token_days"
                                    type="number"
                                    min={1}
                                    max={365}
                                    className="mt-1 block w-full"
                                    placeholder={String(mcp.default_token_days)}
                                    value={tokenForm.data.expires_in_days}
                                    onChange={(e) =>
                                        tokenForm.setData(
                                            'expires_in_days',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={tokenForm.errors.expires_in_days}
                                    className="mt-1"
                                />
                            </div>
                        </div>
                        <label className="flex items-start gap-2 text-sm">
                            <Checkbox
                                checked={tokenForm.data.allow_changes}
                                onCheckedChange={(checked) =>
                                    tokenForm.setData(
                                        'allow_changes',
                                        checked === true,
                                    )
                                }
                                className="mt-0.5"
                            />
                            <span>
                                Allow this token to make changes
                                <span className="block text-xs text-gray-500">
                                    Without this the token can only read - even
                                    if you have allowed changes above. Give a
                                    changes-allowed token only to an AI you
                                    trust with nothing else to read, like your
                                    inbox or the web: that combination is what
                                    makes an AI easy to trick.
                                </span>
                            </span>
                        </label>
                        <PrimaryButton disabled={tokenForm.processing}>
                            Create token
                        </PrimaryButton>
                    </form>

                    {mcp.tokens.length > 0 && (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="text-xs text-gray-500">
                                    <tr>
                                        <th className="py-1 pr-4 font-medium">
                                            Name
                                        </th>
                                        <th className="py-1 pr-4 font-medium">
                                            Access
                                        </th>
                                        <th className="py-1 pr-4 font-medium">
                                            Last used
                                        </th>
                                        <th className="py-1 pr-4 font-medium">
                                            Expires
                                        </th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {mcp.tokens.map((t) => (
                                        <tr key={t.id} className="border-t">
                                            <td className="py-2 pr-4">{t.name}</td>
                                            <td className="py-2 pr-4">
                                                {t.can_change ? (
                                                    <span className="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-900">
                                                        Can make changes
                                                    </span>
                                                ) : (
                                                    <span className="text-xs text-gray-600">
                                                        Read-only
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2 pr-4">
                                                {t.last_used_at
                                                    ? date(t.last_used_at)
                                                    : 'Never'}
                                            </td>
                                            <td className="py-2 pr-4">
                                                {date(t.expires_at)}
                                            </td>
                                            <td className="py-2 text-right">
                                                <button
                                                    type="button"
                                                    className="text-red-600 hover:underline"
                                                    onClick={() =>
                                                        router.delete(
                                                            route(
                                                                'shop.ai.agent.tokens.destroy',
                                                                t.id,
                                                            ),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Revoke
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <details className="rounded-md border">
                        <summary className="cursor-pointer select-none px-3 py-2 text-sm font-medium text-gray-900">
                            Recent AI activity
                            <span className="ms-2 text-xs font-normal text-gray-500">
                                {mcp.refused_last_week > 0
                                    ? `${mcp.refused_last_week} attempt${mcp.refused_last_week === 1 ? '' : 's'} blocked this week`
                                    : 'Nothing blocked this week'}
                            </span>
                        </summary>
                        <div className="border-t px-3 py-3">
                            {mcp.recent_activity.length === 0 ? (
                                <p className="text-sm text-gray-500">
                                    Your AI hasn&apos;t changed anything yet.
                                </p>
                            ) : (
                                <ul className="divide-y text-sm">
                                    {mcp.recent_activity.map((entry) => (
                                        <li
                                            key={entry.id}
                                            className="flex justify-between gap-3 py-1.5"
                                        >
                                            <span>{entry.description}</span>
                                            <span className="shrink-0 text-xs text-gray-500">
                                                {new Date(
                                                    entry.created_at,
                                                ).toLocaleString()}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {mcp.refused_last_week > 0 && (
                                <p className="mt-2 text-xs text-amber-800">
                                    Blocked attempts are things like trying to
                                    write a link into a record, using a
                                    read-only token to make a change, or hitting
                                    the hourly limit. A few can be an honest
                                    mistake; a steady stream is worth looking
                                    into - revoke the token if in doubt.
                                </p>
                            )}
                        </div>
                    </details>

                    <div className="space-y-3">
                        <p className="text-sm font-medium text-gray-900">
                            How to connect
                        </p>
                        <p className="text-xs text-gray-500">
                            Server address:{' '}
                            <span className="font-mono">{mcp.url}</span>.
                            Examples - exact steps vary by AI client.
                        </p>
                        <CopyBlock label="Claude Code" text={claudeCode} />
                        <CopyBlock
                            label="Claude Desktop (claude_desktop_config.json)"
                            text={claudeDesktop}
                        />
                    </div>

                    <details className="rounded-md border">
                        <summary className="cursor-pointer select-none px-3 py-2 text-sm font-medium text-gray-900">
                            How to use it
                            <span className="ms-2 text-xs font-normal text-gray-500">
                                Example things to ask Claude
                            </span>
                        </summary>
                        <div className="space-y-4 border-t px-3 py-3">
                            <p className="text-sm text-gray-700">
                                Once it is connected, just talk to Claude in
                                plain language - it picks the right tools
                                itself. You do not need ids or special
                                commands; use names (&ldquo;Dana&rdquo;,
                                &ldquo;J-0003&rdquo;) and it looks them up.
                                Start by asking what it can do.
                            </p>

                            {exampleGroups.map((group) => (
                                <div key={group.title}>
                                    <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        {group.title}
                                    </p>
                                    <ul className="mt-1 space-y-1.5">
                                        {group.examples.map((example) => (
                                            <PromptExample
                                                key={example.prompt}
                                                prompt={example.prompt}
                                                missing={missingFor(
                                                    example.needs,
                                                )}
                                            />
                                        ))}
                                    </ul>
                                </div>
                            ))}

                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Good to know
                                </p>
                                <ul className="mt-1 list-disc space-y-1 ps-5 text-sm text-gray-700">
                                    <li>
                                        Quotes it makes are always{' '}
                                        <strong>drafts</strong>. Open them under
                                        Quotes, check the prices, and send them
                                        yourself.
                                    </li>
                                    <li>
                                        It takes costs from your catalog and
                                        suppliers. A line it could not match
                                        shows $0 for you to price.
                                    </li>
                                    <li>
                                        If Claude says it cannot do something,
                                        ask &ldquo;what can you do?&rdquo; - and
                                        change what it is allowed to do in the
                                        permissions above. Examples marked
                                        &ldquo;Not enabled yet&rdquo; need
                                        those boxes ticked first.
                                    </li>
                                    <li>
                                        It can never delete anything, send or
                                        email a quote, or touch your team,
                                        billing or settings.
                                    </li>
                                    <li>
                                        Every change it makes is recorded in
                                        your history as made by the AI
                                        connection.
                                    </li>
                                    <li>
                                        Not showing up in Claude? In Claude
                                        Code run <code>/mcp</code> to see
                                        whether fab-shop is connected. In Claude
                                        Desktop, restart it after editing the
                                        config file.
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </details>

                    <div>
                        <button
                            type="button"
                            onClick={turnOff}
                            className="text-sm text-red-600 hover:underline"
                        >
                            Turn off AI connection and delete all tokens
                        </button>
                    </div>
                </fieldset>
            )}
        </div>
    );
}
