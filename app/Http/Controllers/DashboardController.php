<?php

namespace App\Http\Controllers;

use App\Enums\JobStatus;
use App\Enums\QuoteStatus;
use App\Models\Job;
use App\Models\Quote;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    /**
     * Quotes sent longer ago than this without a won/lost decision get
     * flagged for a follow-up call.
     */
    private const STALE_QUOTE_DAYS = 7;

    private const WIN_RATE_WINDOW_DAYS = 90;

    private const REVENUE_TREND_MONTHS = 6;

    public function __invoke(): Response
    {
        // Totals are derived from quote line items rather than stored, so
        // aggregation happens here in PHP - fine at the 1-8 person shop
        // scale this is built for, where job counts stay in the hundreds.
        $jobs = Job::query()->with('customer:id,name', 'quote.lineItems')->get();

        $openQuotes = Quote::query()
            ->with('customer:id,name', 'lineItems')
            ->where('status', QuoteStatus::Sent)
            ->whereNull('archived_at')
            ->get();

        return Inertia::render('Dashboard', [
            'kpis' => $this->kpis($jobs, $openQuotes),
            'revenueTrend' => $this->revenueTrend($jobs),
            'pipeline' => $this->pipeline($jobs),
            'attention' => $this->attention($jobs, $openQuotes),
            'recentActivity' => $this->recentActivity(),
        ]);
    }

    /**
     * @param  Collection<int, Job>  $jobs
     * @param  Collection<int, Quote>  $openQuotes
     * @return array<string, mixed>
     */
    private function kpis(Collection $jobs, Collection $openQuotes): array
    {
        $invoiced = $jobs->where('status', JobStatus::Invoiced);
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth();

        $wip = $jobs->filter(fn (Job $job) => $job->status->order() !== null
            && $job->status->order() <= JobStatus::Finishing->order());
        $readyToInvoice = $jobs->where('status', JobStatus::Shipped);

        $decided = Quote::query()
            ->whereIn('status', [QuoteStatus::Won, QuoteStatus::Lost])
            ->where('sent_at', '>=', now()->subDays(self::WIN_RATE_WINDOW_DAYS))
            ->pluck('status');

        return [
            'invoiced_this_month' => $this->sumTotals($invoiced->filter(fn (Job $job) => $job->invoiced_at?->gte($thisMonth))),
            'invoiced_last_month' => $this->sumTotals($invoiced->filter(fn (Job $job) => $job->invoiced_at?->gte($lastMonth) && $job->invoiced_at->lt($thisMonth))),
            'work_in_progress' => ['count' => $wip->count(), 'value' => $this->sumTotals($wip)],
            'ready_to_invoice' => ['count' => $readyToInvoice->count(), 'value' => $this->sumTotals($readyToInvoice)],
            'open_quotes' => ['count' => $openQuotes->count(), 'value' => $this->sumTotals($openQuotes)],
            'win_rate' => $decided->isEmpty()
                ? null
                : round($decided->filter(fn (QuoteStatus $status) => $status === QuoteStatus::Won)->count() / $decided->count() * 100),
            'win_rate_window_days' => self::WIN_RATE_WINDOW_DAYS,
        ];
    }

    /**
     * Invoiced revenue per calendar month, oldest first, including months
     * with nothing invoiced so the chart has no gaps.
     *
     * @param  Collection<int, Job>  $jobs
     * @return array<int, array{month: string, label: string, total: float}>
     */
    private function revenueTrend(Collection $jobs): array
    {
        $invoiced = $jobs->where('status', JobStatus::Invoiced)->whereNotNull('invoiced_at');

        return collect(range(self::REVENUE_TREND_MONTHS - 1, 0))
            ->map(function (int $monthsAgo) use ($invoiced) {
                $month = now()->subMonthsNoOverflow($monthsAgo)->startOfMonth();

                return [
                    'month' => $month->format('Y-m'),
                    'label' => $month->format('M'),
                    'total' => $this->sumTotals($invoiced->filter(fn (Job $job) => $job->invoiced_at->isSameMonth($month))),
                ];
            })
            ->all();
    }

    /**
     * Job counts per traveler station - Invoiced and Cancelled are left
     * off since they're finished business, not work to keep an eye on.
     *
     * @param  Collection<int, Job>  $jobs
     * @return array<int, array{status: string, label: string, count: int}>
     */
    private function pipeline(Collection $jobs): array
    {
        return collect(JobStatus::cases())
            ->reject(fn (JobStatus $status) => in_array($status, [JobStatus::Invoiced, JobStatus::Cancelled], true))
            ->map(fn (JobStatus $status) => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => $jobs->where('status', $status)->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * Things someone should act on: shipped work nobody has billed yet,
     * invoices that failed to reach QuickBooks, and quotes waiting on the
     * customer long enough to deserve a follow-up.
     *
     * @param  Collection<int, Job>  $jobs
     * @param  Collection<int, Quote>  $openQuotes
     * @return array<int, array<string, mixed>>
     */
    private function attention(Collection $jobs, Collection $openQuotes): array
    {
        $readyToInvoice = $jobs->where('status', JobStatus::Shipped)->map(fn (Job $job) => [
            'kind' => 'ready_to_invoice',
            'title' => "{$job->job_number} shipped - ready to invoice",
            'subtitle' => $job->customer->name,
            'amount' => $job->total,
            'href' => route('jobs.show', $job),
        ]);

        $syncErrors = $jobs->whereNotNull('quickbooks_sync_error')->map(fn (Job $job) => [
            'kind' => 'sync_error',
            'title' => "{$job->job_number} didn't sync to QuickBooks",
            'subtitle' => $job->quickbooks_sync_error,
            'amount' => $job->total,
            'href' => route('jobs.show', $job),
        ]);

        $staleQuotes = $openQuotes
            ->filter(fn (Quote $quote) => $quote->sent_at?->lte(now()->subDays(self::STALE_QUOTE_DAYS)))
            ->sortBy('sent_at')
            ->map(fn (Quote $quote) => [
                'kind' => 'stale_quote',
                'title' => "Quote for {$quote->customer->name} - no reply in ".(int) $quote->sent_at->diffInDays(now()).' days',
                'subtitle' => 'Sent '.$quote->sent_at->format('M j').' - worth a follow-up call',
                'amount' => $quote->total,
                'href' => route('quotes.edit', $quote),
            ]);

        return $syncErrors->concat($readyToInvoice)->concat($staleQuotes)->values()->all();
    }

    /**
     * The activity log table isn't tenant-scoped itself, so entries are
     * limited to subjects that are jobs visible through Job's own global
     * scope - never a hand-written shop_id filter.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(): array
    {
        return Activity::query()
            ->with('causer', 'subject')
            ->where('subject_type', (new Job)->getMorphClass())
            ->whereIn('subject_id', Job::query()->select('id'))
            ->latest()
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'description' => $activity->description,
                'causer' => $activity->causer?->name,
                'job_number' => $activity->subject?->job_number,
                'href' => $activity->subject ? route('jobs.show', $activity->subject) : null,
                'created_at' => $activity->created_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, Job|Quote>  $items
     */
    private function sumTotals(Collection $items): float
    {
        return round($items->sum(fn (Job|Quote $item) => $item->total), 2);
    }
}
