import FormErrorSummary from '@/Components/FormErrorSummary';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout';
import { formatCalendarDay } from '@/lib/datetime';
import { formatCents, formatMoney } from '@/lib/money';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Archive,
    Check,
    Minus,
    Pencil,
    Plus,
    TriangleAlert,
} from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface PlanVersion {
    id: number;
    key: string;
    name: string;
    version: number;
    position: number;
    monthly_price_cents: number;
    extra_seat_price_cents: number | null;
    user_limit: number | null;
    features: string[];
    expires_on: string | null;
    show_expiry: boolean;
    published_at: string | null;
    archived_at: string | null;
    subscribers: number;
}

interface Tier extends PlanVersion {
    protected: boolean;
    status: 'offered' | 'expiring' | 'removed';
    history: PlanVersion[];
}

interface FeatureOption {
    key: string;
    label: string;
    shipped: boolean;
    discontinued: boolean;
}

const dollars = (cents: number | null) =>
    cents === null ? '' : String(cents / 100);

const toCents = (value: string) => Math.round(Number(value || 0) * 100);

const formatInstant = (value: string | null) =>
    value
        ? new Date(value).toLocaleDateString('en-US', { dateStyle: 'medium' })
        : '-';

const shops = (count: number) => `${count} shop${count === 1 ? '' : 's'}`;

function StatusBadge({ tier }: { tier: Tier }) {
    if (tier.status === 'removed') {
        return <Badge variant="danger">Archived</Badge>;
    }

    if (tier.status === 'expiring') {
        return (
            <Badge variant="warning">
                Ends {formatCalendarDay(tier.expires_on!)}
            </Badge>
        );
    }

    return <Badge variant="success">Public</Badge>;
}

/** One feature line, drawn the way the landing page's tier card draws it. */
function FeatureRow({
    feature,
    included,
}: {
    feature: FeatureOption;
    included: boolean;
}) {
    return (
        <li className="flex items-center gap-2 text-sm">
            {feature.shipped || feature.discontinued ? (
                included && !feature.discontinued ? (
                    <Check className="size-4 shrink-0 text-green-600" />
                ) : (
                    <Minus className="size-4 shrink-0 text-muted-foreground/50" />
                )
            ) : (
                <TriangleAlert className="size-4 shrink-0 text-amber-500" />
            )}
            <span
                className={
                    feature.shipped && !feature.discontinued
                        ? included
                            ? 'text-foreground'
                            : 'text-muted-foreground/60'
                        : 'text-muted-foreground'
                }
            >
                {feature.label}
                {feature.discontinued ? (
                    <span className="ms-1.5 text-xs text-red-600">
                        (discontinued)
                    </span>
                ) : (
                    !feature.shipped && (
                        <span className="ms-1.5 text-xs text-amber-600">
                            (coming soon)
                        </span>
                    )
                )}
            </span>
        </li>
    );
}

/**
 * The same card the marketing page draws for a tier, with the admin's
 * controls on top: edit (opens the dialog) and archive (asks first).
 */
function TierCard({
    tier,
    allFeatures,
    onEdit,
    onArchive,
}: {
    tier: Tier;
    allFeatures: FeatureOption[];
    onEdit: () => void;
    onArchive: () => void;
}) {
    return (
        <Card
            className={`flex h-full flex-col p-6 ${tier.status === 'removed' ? 'opacity-70' : ''}`}
        >
            <div className="flex items-start justify-between gap-2">
                <h3 className="text-lg font-semibold">{tier.name}</h3>
                <div className="-me-2 -mt-1 flex shrink-0 gap-0.5">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        aria-label={`Edit ${tier.name}`}
                        title="Edit this tier"
                        onClick={onEdit}
                    >
                        <Pencil />
                    </Button>
                    {tier.protected ? null : (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            aria-label={`Archive ${tier.name}`}
                            title={
                                tier.status === 'removed'
                                    ? 'Already archived'
                                    : 'Archive (take off the public list)'
                            }
                            disabled={tier.status === 'removed'}
                            className="text-destructive hover:text-destructive"
                            onClick={onArchive}
                        >
                            <Archive />
                        </Button>
                    )}
                </div>
            </div>

            <p className="mt-1 text-sm text-muted-foreground">
                {tier.user_limit === null
                    ? 'Unlimited users'
                    : `Up to ${tier.user_limit} user${tier.user_limit === 1 ? '' : 's'}`}
            </p>
            {tier.show_expiry && tier.expires_on && (
                <p className="mt-1 text-xs font-medium text-amber-700">
                    Available until {formatCalendarDay(tier.expires_on)}
                </p>
            )}

            <div className="mt-3">
                <span className="text-3xl font-bold tracking-tight">
                    {formatCents(tier.monthly_price_cents)}
                </span>
                <span className="text-sm text-muted-foreground">/mo</span>
                {tier.extra_seat_price_cents !== null && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        Extra seats {formatCents(tier.extra_seat_price_cents)}
                        /mo each
                    </p>
                )}
            </div>

            <ul className="mt-5 flex-1 space-y-2 border-t pt-5">
                {allFeatures.map((feature) => (
                    <FeatureRow
                        key={feature.key}
                        feature={feature}
                        included={tier.features.includes(feature.key)}
                    />
                ))}
            </ul>

            <div className="mt-5 space-y-2 border-t pt-4 text-xs text-muted-foreground">
                <div className="flex flex-wrap items-center gap-1.5">
                    <Badge variant="outline">v{tier.version}</Badge>
                    <StatusBadge tier={tier} />
                </div>
                <p>
                    {shops(tier.subscribers)} on this version
                    {tier.history.length > 0 &&
                        ` · ${tier.history.length} earlier version${tier.history.length === 1 ? '' : 's'}`}
                </p>
            </div>
        </Card>
    );
}

/**
 * The form for one tier, used both to edit an existing tier and to add a new
 * one. Money is typed in dollars and sent in cents.
 */
/** YYYY-MM-DD for `days` from today, in the admin's own calendar. */
const inDays = (days: number) => {
    const day = new Date();
    day.setDate(day.getDate() + days);

    return [
        day.getFullYear(),
        String(day.getMonth() + 1).padStart(2, '0'),
        String(day.getDate()).padStart(2, '0'),
    ].join('-');
};

function TierEditor({
    tier,
    allFeatures,
    noticeDays,
    onDone,
}: {
    tier: Tier | null;
    allFeatures: FeatureOption[];
    noticeDays: number;
    onDone: () => void;
}) {
    const { data, setData, post, patch, processing, errors, transform } =
        useForm({
            name: tier?.name ?? '',
            price: dollars(tier?.monthly_price_cents ?? 0),
            seats:
                tier === null
                    ? '1'
                    : tier.user_limit === null
                      ? ''
                      : String(tier.user_limit),
            extra_seat_price: dollars(tier?.extra_seat_price_cents ?? null),
            features: tier?.features ?? ([] as string[]),
            position: tier ? String(tier.position) : '',
            expires_on: tier?.expires_on ?? '',
            show_expiry: tier?.show_expiry ?? false,
            // Only used when this edit raises the price.
            apply_to_subscribers: true,
            effective_on: inDays(noticeDays),
        });

    // Raising the price of a tier that has shops on it is the one change
    // that needs a decision (and, by the Terms, advance notice).
    const raisesPrice =
        tier !== null && toCents(data.price) > tier.monthly_price_cents;
    const lowersPrice =
        tier !== null && toCents(data.price) < tier.monthly_price_cents;
    // Everyone on the tier, whichever version they signed up for.
    const onTier = tier
        ? tier.subscribers +
          tier.history.reduce((sum, v) => sum + v.subscribers, 0)
        : 0;

    transform((form) => ({
        name: form.name,
        monthly_price_cents: toCents(form.price),
        user_limit: form.seats === '' ? null : Number(form.seats),
        extra_seat_price_cents:
            form.extra_seat_price === ''
                ? null
                : toCents(form.extra_seat_price),
        features: form.features,
        position: form.position === '' ? null : Number(form.position),
        expires_on: form.expires_on === '' ? null : form.expires_on,
        show_expiry: form.expires_on === '' ? false : form.show_expiry,
        apply_to_subscribers: raisesPrice && form.apply_to_subscribers,
        subscribers_effective_on:
            raisesPrice && form.apply_to_subscribers && form.effective_on !== ''
                ? form.effective_on
                : null,
    }));

    const toggleFeature = (key: string, checked: boolean) =>
        setData(
            'features',
            checked
                ? [...data.features, key]
                : data.features.filter((f) => f !== key),
        );

    // Mirrors Plan::applyEdit(): these are the things a subscriber bought.
    const publishesNewVersion =
        tier !== null &&
        (data.name !== tier.name ||
            toCents(data.price) !== tier.monthly_price_cents ||
            (data.seats === '' ? null : Number(data.seats)) !==
                tier.user_limit ||
            (data.extra_seat_price === ''
                ? null
                : toCents(data.extra_seat_price)) !==
                tier.extra_seat_price_cents ||
            [...data.features].sort().join() !==
                [...tier.features].sort().join());

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onDone };

        if (tier) {
            patch(route('platform-admin.plans.update', tier.id), options);
        } else {
            post(route('platform-admin.plans.store'), options);
        }
    };

    // The server names fields differently from the form (cents, not dollars).
    const serverErrors = errors as Record<string, string>;
    const id = `tier-${tier?.id ?? 'new'}`;

    return (
        <form onSubmit={submit} className="space-y-5">
            <FormErrorSummary errors={serverErrors} />

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="sm:col-span-2">
                    <InputLabel htmlFor={`${id}-name`} value="Name" />
                    <TextInput
                        id={`${id}-name`}
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />
                    <InputError message={errors.name} className="mt-1" />
                </div>
                <div>
                    <InputLabel
                        htmlFor={`${id}-price`}
                        value="Price ($ / month)"
                    />
                    <TextInput
                        id={`${id}-price`}
                        type="number"
                        step="0.01"
                        min="0"
                        className="mt-1 block w-full"
                        value={data.price}
                        onChange={(e) => setData('price', e.target.value)}
                        required
                    />
                    <InputError
                        message={serverErrors.monthly_price_cents}
                        className="mt-1"
                    />
                </div>
                <div>
                    <InputLabel
                        htmlFor={`${id}-seats`}
                        value="Seats included"
                    />
                    <TextInput
                        id={`${id}-seats`}
                        type="number"
                        min="1"
                        placeholder="unlimited"
                        className="mt-1 block w-full"
                        value={data.seats}
                        onChange={(e) => setData('seats', e.target.value)}
                    />
                    <InputError
                        message={serverErrors.user_limit}
                        className="mt-1"
                    />
                </div>
                <div>
                    <InputLabel
                        htmlFor={`${id}-extra`}
                        value="Extra seat ($ / month)"
                    />
                    <TextInput
                        id={`${id}-extra`}
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="not sold"
                        className="mt-1 block w-full"
                        value={data.extra_seat_price}
                        onChange={(e) =>
                            setData('extra_seat_price', e.target.value)
                        }
                    />
                    <InputError
                        message={serverErrors.extra_seat_price_cents}
                        className="mt-1"
                    />
                </div>
                <div>
                    <InputLabel htmlFor={`${id}-position`} value="Order" />
                    <TextInput
                        id={`${id}-position`}
                        type="number"
                        min="0"
                        placeholder="last"
                        className="mt-1 block w-full"
                        value={data.position}
                        onChange={(e) => setData('position', e.target.value)}
                    />
                    <p className="mt-1 text-xs text-gray-500">
                        Lowest first. Also decides what counts as an upgrade.
                    </p>
                    <InputError message={errors.position} className="mt-1" />
                </div>
                <div>
                    <InputLabel
                        htmlFor={`${id}-expires`}
                        value="Leaves the public list on"
                    />
                    <TextInput
                        id={`${id}-expires`}
                        type="date"
                        className="mt-1 block w-full"
                        value={data.expires_on}
                        disabled={tier?.protected}
                        onChange={(e) => setData('expires_on', e.target.value)}
                    />
                    <InputError message={errors.expires_on} className="mt-1" />
                </div>
                <div className="flex items-end pb-2">
                    <label className="flex items-center gap-2 text-sm text-gray-700">
                        <Checkbox
                            checked={data.show_expiry && data.expires_on !== ''}
                            disabled={data.expires_on === ''}
                            onCheckedChange={(checked) =>
                                setData('show_expiry', checked === true)
                            }
                        />
                        Show this date publicly
                    </label>
                </div>
            </div>

            <fieldset>
                <legend className="text-sm font-medium text-gray-700">
                    Features included
                </legend>
                <div className="mt-2 grid gap-2 sm:grid-cols-2">
                    {allFeatures.map((feature) => (
                        <label
                            key={feature.key}
                            className="flex items-center gap-2 text-sm text-gray-700"
                        >
                            <Checkbox
                                checked={
                                    !feature.discontinued &&
                                    data.features.includes(feature.key)
                                }
                                disabled={feature.discontinued}
                                onCheckedChange={(checked) =>
                                    toggleFeature(feature.key, checked === true)
                                }
                            />
                            <span>{feature.label}</span>
                            {feature.discontinued ? (
                                <Badge variant="danger">Discontinued</Badge>
                            ) : (
                                !feature.shipped && (
                                    <Badge variant="warning">Coming soon</Badge>
                                )
                            )}
                        </label>
                    ))}
                </div>
            </fieldset>

            {raisesPrice && tier && (
                <fieldset className="space-y-3 rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                    <legend className="px-1 font-medium">
                        This raises the price. What about the{' '}
                        {shops(onTier)} already on this tier?
                    </legend>

                    <label className="flex items-start gap-2">
                        <input
                            type="radio"
                            name={`${id}-apply`}
                            className="mt-1"
                            checked={data.apply_to_subscribers}
                            onChange={() => setData('apply_to_subscribers', true)}
                        />
                        <span>
                            Move them to the new price on
                            <TextInput
                                type="date"
                                aria-label="Date the new price reaches existing shops"
                                className="mx-2 inline-block w-44"
                                min={inDays(0)}
                                value={data.effective_on}
                                disabled={!data.apply_to_subscribers}
                                onChange={(e) =>
                                    setData('effective_on', e.target.value)
                                }
                            />
                            <span className="block text-xs">
                                Their owners are emailed when you save, with
                                this date. Clear the date to apply it
                                immediately.
                            </span>
                        </span>
                    </label>

                    <label className="flex items-start gap-2">
                        <input
                            type="radio"
                            name={`${id}-apply`}
                            className="mt-1"
                            checked={!data.apply_to_subscribers}
                            onChange={() => setData('apply_to_subscribers', false)}
                        />
                        <span>
                            Let them keep the price they signed up for. Only
                            new customers pay the new price.
                        </span>
                    </label>

                    {data.apply_to_subscribers &&
                        (data.effective_on === '' ||
                            data.effective_on < inDays(noticeDays)) && (
                            <p className="rounded bg-amber-100 p-2 text-xs font-medium">
                                {data.effective_on === ''
                                    ? 'Applying it immediately gives no advance notice. '
                                    : `That is less than ${noticeDays} days from today. `}
                                The Terms of Service promise at least{' '}
                                {noticeDays} days' notice of a price increase.
                            </p>
                        )}
                </fieldset>
            )}

            <p className="text-xs text-gray-500">
                {tier === null
                    ? 'Adds a new tier to the public list.'
                    : publishesNewVersion
                      ? `Saving publishes version ${tier.version + 1}. Shops already on this tier keep every feature they have and gain any you add.${
                            lowersPrice
                                ? ' The lower price reaches them straight away and they are emailed.'
                                : raisesPrice
                                  ? ''
                                  : ' Their price does not change.'
                        }`
                      : 'Nothing a subscriber bought has changed, so saving updates this version in place.'}
            </p>

            <div className="flex justify-end gap-2">
                <SecondaryButton type="button" onClick={onDone}>
                    Cancel
                </SecondaryButton>
                <PrimaryButton disabled={processing}>
                    {tier ? 'Save' : 'Add tier'}
                </PrimaryButton>
            </div>
        </form>
    );
}

function History({
    versions,
    featureLabels,
}: {
    versions: PlanVersion[];
    featureLabels: Record<string, string>;
}) {
    const remaining = versions.reduce((sum, v) => sum + v.subscribers, 0);

    return (
        <section className="border-t pt-5">
            <h3 className="text-sm font-semibold text-gray-900">History</h3>
            <p className="mt-1 text-xs text-gray-500">
                {versions.length === 0
                    ? 'No earlier versions - this tier has not been changed since it was added.'
                    : `${versions.length} earlier version${versions.length === 1 ? '' : 's'}; ${shops(remaining)} still on them.`}
            </p>

            {versions.length > 0 && (
                <div className="mt-3 overflow-x-auto rounded-md border border-gray-200">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-wider text-gray-500">
                                <th className="px-3 py-2">Version</th>
                                <th className="px-3 py-2">In effect</th>
                                <th className="px-3 py-2">Price</th>
                                <th className="px-3 py-2">Seats</th>
                                <th className="px-3 py-2">Extra seat</th>
                                <th className="px-3 py-2">Features</th>
                                <th className="px-3 py-2 text-right">
                                    Shops still on it
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {versions.map((v) => (
                                <tr key={v.id} className="align-top">
                                    <td className="whitespace-nowrap px-3 py-2 font-medium text-gray-900">
                                        v{v.version} · {v.name}
                                    </td>
                                    <td className="whitespace-nowrap px-3 py-2 text-gray-600">
                                        {formatInstant(v.published_at)} –{' '}
                                        {formatInstant(v.archived_at)}
                                    </td>
                                    <td className="whitespace-nowrap px-3 py-2">
                                        {formatMoney(v.monthly_price_cents)}/mo
                                    </td>
                                    <td className="whitespace-nowrap px-3 py-2">
                                        {v.user_limit ?? 'unlimited'}
                                    </td>
                                    <td className="whitespace-nowrap px-3 py-2">
                                        {v.extra_seat_price_cents === null
                                            ? 'not sold'
                                            : `${formatMoney(v.extra_seat_price_cents)}/mo`}
                                    </td>
                                    <td className="min-w-48 px-3 py-2 text-gray-600">
                                        {v.features.length === 0
                                            ? 'none'
                                            : v.features
                                                  .map(
                                                      (key) =>
                                                          featureLabels[key] ??
                                                          key,
                                                  )
                                                  .join(', ')}
                                    </td>
                                    <td
                                        className={`px-3 py-2 text-right ${v.subscribers > 0 ? 'font-semibold text-gray-900' : 'text-gray-400'}`}
                                    >
                                        {v.subscribers}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

export default function Plans({
    tiers,
    allFeatures,
    priceNoticeDays,
}: PageProps<{
    tiers: Tier[];
    allFeatures: FeatureOption[];
    priceNoticeDays: number;
}>) {
    // Held as an id (not the object) so the dialog always shows the freshest
    // props; 'new' is the add form.
    const [editing, setEditing] = useState<number | 'new' | null>(null);
    const [archivingId, setArchivingId] = useState<number | null>(null);
    const [archiving, setArchiving] = useState(false);

    const editingTier =
        typeof editing === 'number'
            ? (tiers.find((t) => t.id === editing) ?? null)
            : null;
    const archivingTier = tiers.find((t) => t.id === archivingId) ?? null;
    const featureLabels = Object.fromEntries(
        allFeatures.map((f) => [f.key, f.label]),
    );

    const confirmArchive = () => {
        if (!archivingTier) return;

        router.post(
            route('platform-admin.plans.retire', archivingTier.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setArchiving(true),
                onFinish: () => {
                    setArchiving(false);
                    setArchivingId(null);
                },
            },
        );
    };

    return (
        <PlatformAdminLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Plan tiers
                    </h2>
                    <PageHelp>
                        <p>
                            Add tiers, set what each costs, how many seats it
                            includes and which features it bundles. The
                            marketing page, onboarding, the shop's Plan page and
                            the app's own feature gating all read this live.
                        </p>
                        <p>
                            Tiers are never deleted or overwritten. Changing a
                            tier's name, price, seats or features publishes a
                            new version, and the old one stays in that tier's
                            history with a count of the shops still on it. Shops
                            already on the tier keep every feature they have and
                            gain any you add. If you lower the price, it
                            applies to them straight away and they are emailed.
                            If you raise it, you choose whether it reaches
                            them and from which day (the form proposes{' '}
                            {priceNoticeDays} days out; clear the date to apply
                            it immediately) - they are emailed when you save,
                            ahead of the date.
                        </p>
                        <p>
                            Archiving takes a tier off signup and upgrade
                            choices straight away; shops already on it keep it.
                            To do it on a future date instead, edit the tier and
                            set "Leaves the public list on" (the optional "show
                            publicly" box advertises the date). The Free tier is
                            where shops land when a trial ends, so it can't be
                            archived.
                        </p>
                        <p>
                            A single shop's plan or seats can still be
                            overridden from the shop directory.
                        </p>
                    </PageHelp>
                </div>
            }
        >
            <Head title="Plan tiers" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 items-stretch gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        {tiers.map((tier) => (
                            <TierCard
                                key={tier.id}
                                tier={tier}
                                allFeatures={allFeatures}
                                onEdit={() => setEditing(tier.id)}
                                onArchive={() => setArchivingId(tier.id)}
                            />
                        ))}

                        <button
                            type="button"
                            onClick={() => setEditing('new')}
                            aria-label="Add a tier"
                            className="flex min-h-48 flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-border p-6 text-muted-foreground transition-colors hover:border-primary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            <Plus className="size-8" />
                            <span className="text-sm font-medium">
                                Add a tier
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <Dialog
                open={editing !== null && (editing === 'new' || !!editingTier)}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent
                    aria-describedby={undefined}
                    className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-3xl"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {editingTier
                                ? `Edit ${editingTier.name}`
                                : 'Add a tier'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingTier
                                ? `Version ${editingTier.version} · ${shops(editingTier.subscribers)} on it`
                                : 'It appears on the public list as soon as you add it.'}
                        </DialogDescription>
                    </DialogHeader>

                    {/* Re-keyed so each open (or a freshly published version)
                        starts from the saved values. */}
                    <TierEditor
                        key={
                            editingTier
                                ? `${editingTier.id}-${editingTier.version}`
                                : 'new'
                        }
                        tier={editingTier}
                        allFeatures={allFeatures}
                        noticeDays={priceNoticeDays}
                        onDone={() => setEditing(null)}
                    />

                    {editingTier && (
                        <History
                            versions={editingTier.history}
                            featureLabels={featureLabels}
                        />
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={archivingTier !== null}
                onOpenChange={(open) => !open && setArchivingId(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Archive {archivingTier?.name}?
                        </DialogTitle>
                        <DialogDescription>
                            It comes off the public list now, so nobody can pick
                            it at signup or when upgrading.{' '}
                            {archivingTier && archivingTier.subscribers > 0
                                ? `The ${shops(archivingTier.subscribers)} on it keep it, and its history is kept.`
                                : 'No shops are on it, and its history is kept.'}{' '}
                            You can bring it back by editing it and clearing
                            "Leaves the public list on".
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <SecondaryButton
                            type="button"
                            onClick={() => setArchivingId(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={archiving}
                            onClick={confirmArchive}
                        >
                            Archive tier
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </PlatformAdminLayout>
    );
}
