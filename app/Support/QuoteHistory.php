<?php

namespace App\Support;

use App\Enums\Feature;
use App\Models\Quote;
use App\Models\QuoteHistoryEntry;
use Illuminate\Support\Facades\Auth;

/**
 * Records and diffs a quote's change log. Everything here is a no-op unless
 * the current shop is entitled to Feature::QuoteHistory - an unentitled shop
 * never writes (or reads) the quote_history_entries table.
 */
final class QuoteHistory
{
    /** Line-item fields compared on save, with how each is labelled. */
    private const LINE_FIELDS = [
        'description' => 'description',
        'quantity' => 'quantity',
        'unit_cost' => 'unit cost',
        'markup_percent' => 'markup %',
        'override_total' => 'price override',
    ];

    /**
     * Pass the quote when there may be no signed-in user (a queued job): the
     * quote's own shop is checked instead.
     */
    public static function enabled(?Quote $quote = null): bool
    {
        $shop = Auth::user()?->currentShop ?? $quote?->shop;

        return $shop?->hasFeature(Feature::QuoteHistory) ?? false;
    }

    /**
     * The quote as it stands, in the shape diff() compares - take one before
     * a save and one after.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(Quote $quote): array
    {
        $quote->unsetRelation('lineItems')->unsetRelation('customer')->load('lineItems', 'customer');

        return [
            'customer' => $quote->customer?->name,
            'notes' => $quote->notes,
            'due_date' => $quote->due_date?->toDateString(),
            'lines' => $quote->lineItems->map(fn ($item) => [
                'type' => $item->type->value,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_cost' => (float) $item->unit_cost,
                'markup_percent' => (float) $item->markup_percent,
                'override_total' => $item->override_total !== null ? (float) $item->override_total : null,
            ])->all(),
        ];
    }

    /**
     * Record a save, if anything actually changed since the last one.
     *
     * @param  array<string, mixed>  $before
     */
    public static function recordSave(Quote $quote, array $before, string $summary = 'Saved changes'): void
    {
        $changes = self::diff($before, self::snapshot($quote));

        if ($changes !== []) {
            self::write($quote, 'updated', $summary, $changes);
        }
    }

    /**
     * @param  array<int, array{type: string, subject: string, from?: string|null, to?: string|null}>|null  $changes
     */
    public static function log(Quote $quote, string $event, string $summary, ?array $changes = null): void
    {
        if (self::enabled($quote)) {
            self::write($quote, $event, $summary, $changes);
        }
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<int, array{type: string, subject: string, from?: string|null, to?: string|null}>
     */
    public static function diff(array $before, array $after): array
    {
        $changes = [];

        foreach (['customer' => 'Customer', 'notes' => 'Notes', 'due_date' => 'Due date'] as $key => $label) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $changes[] = ['type' => 'changed', 'subject' => $label, 'from' => $before[$key] ?? null, 'to' => $after[$key] ?? null];
            }
        }

        // Lines have no stable identity (they're replaced on every save), so
        // pair them up: exact type+description matches first, then leftovers
        // of the same type in order (a renamed line), the rest are adds/removes.
        $old = $before['lines'] ?? [];
        $new = $after['lines'] ?? [];
        $pairs = [];

        foreach ($new as $ni => $line) {
            foreach ($old as $oi => $candidate) {
                if ($candidate['type'] === $line['type'] && $candidate['description'] === $line['description']) {
                    $pairs[] = [$oi, $ni];
                    unset($old[$oi], $new[$ni]);
                    break;
                }
            }
        }
        foreach ($new as $ni => $line) {
            foreach ($old as $oi => $candidate) {
                if ($candidate['type'] === $line['type']) {
                    $pairs[] = [$oi, $ni];
                    unset($old[$oi], $new[$ni]);
                    break;
                }
            }
        }

        foreach ($old as $line) {
            $changes[] = ['type' => 'removed', 'subject' => self::lineName($line), 'from' => self::describe($line)];
        }
        foreach ($new as $line) {
            $changes[] = ['type' => 'added', 'subject' => self::lineName($line), 'to' => self::describe($line)];
        }
        foreach ($pairs as [$oi, $ni]) {
            $was = $before['lines'][$oi];
            $now = $after['lines'][$ni];

            foreach (self::LINE_FIELDS as $field => $label) {
                if ($was[$field] !== $now[$field]) {
                    $changes[] = [
                        'type' => 'changed',
                        'subject' => self::lineName($was)." - {$label}",
                        'from' => self::format($was[$field]),
                        'to' => self::format($now[$field]),
                    ];
                }
            }
        }

        return $changes;
    }

    /**
     * The same change list with every mention of markup taken out, for a
     * viewer who isn't allowed to see it: markup edits are dropped and the
     * "+ N% markup" tail of an added/removed line's summary is cut.
     *
     * @param  array<int, array<string, mixed>>  $changes
     * @return array<int, array<string, mixed>>
     */
    public static function withoutMarkup(array $changes): array
    {
        $strip = fn (mixed $text) => is_string($text) ? preg_replace('/ \+ [\d.]+% markup/', '', $text) : $text;

        return collect($changes)
            ->reject(fn (array $change) => str_ends_with((string) ($change['subject'] ?? ''), ' - '.self::LINE_FIELDS['markup_percent']))
            ->map(fn (array $change) => [
                ...$change,
                ...(array_key_exists('from', $change) ? ['from' => $strip($change['from'])] : []),
                ...(array_key_exists('to', $change) ? ['to' => $strip($change['to'])] : []),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $changes
     */
    private static function write(Quote $quote, string $event, string $summary, ?array $changes): void
    {
        // shop_id set explicitly: a queued job has no signed-in user for the
        // tenant trait to take it from.
        (new QuoteHistoryEntry)->forceFill([
            'shop_id' => $quote->shop_id,
            'quote_id' => $quote->id,
            'user_id' => Auth::id(),
            'event' => $event,
            'summary' => $summary,
            'changes' => $changes,
        ])->save();
    }

    /** @param  array<string, mixed>  $line */
    private static function lineName(array $line): string
    {
        return ucfirst($line['type']).' line "'.($line['description'] ?: 'untitled').'"';
    }

    /** @param  array<string, mixed>  $line */
    private static function describe(array $line): string
    {
        $text = self::format($line['quantity']).' x $'.number_format($line['unit_cost'], 2).' + '.self::format($line['markup_percent']).'% markup';

        return $line['override_total'] !== null ? $text.', price override $'.number_format($line['override_total'], 2) : $text;
    }

    private static function format(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'none';
        }

        return is_float($value) ? rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') : (string) $value;
    }
}
