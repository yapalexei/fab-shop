<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Enums\JobStageRole;
use App\Enums\QuoteStatus;
use App\Models\BusinessPartner;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Job;
use App\Models\JobStage;
use App\Models\Material;
use App\Models\Quote;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    private const REVENUE_TREND_MONTHS = 6;

    public function __invoke(): Response
    {
        $shop = Auth::user()->currentShop;
        $canSeeFinancials = Gate::allows('financials.view');

        // Totals are derived from quote line items rather than stored, so
        // aggregation happens here in PHP - fine at the 1-8 person shop
        // scale this is built for, where job counts stay in the hundreds.
        $jobs = Job::query()->with('customer:id,name', 'quote.lineItems', 'jobStage')->get();

        $openQuotes = Quote::query()
            ->with('customer:id,name', 'lineItems')
            ->where('status', QuoteStatus::Sent)
            ->whereNull('archived_at')
            ->get();

        return Inertia::render('Dashboard', [
            'counts' => $this->counts($jobs, $openQuotes),
            // Omitted entirely (not just hidden in the UI) for anyone without
            // the permission - Inertia props are readable in page source.
            'financials' => $canSeeFinancials ? $this->financials($shop, $jobs, $openQuotes) : null,
            'pipeline' => $this->pipeline($jobs),
            'attention' => $this->attention($shop, $jobs, $openQuotes, $canSeeFinancials),
            'recentActivity' => $this->recentActivity(),
            'gettingStarted' => $this->gettingStarted($shop),
        ]);
    }

    /**
     * Suggested next steps for a shop that's still setting up: starting a
     * quote (primary) plus filling the lists the quote builder prices from.
     * Only steps this user can actually act on are returned - the shop's
     * plan must include the feature and they must hold the `create`
     * permission - and null once nothing is left to suggest, so the banner
     * disappears on its own rather than needing a dismiss flag.
     *
     * @return array<int, array{key: string, title: string, description: string, href: string, list_href: string, done: bool, primary: bool}>|null
     */
    private function gettingStarted(Shop $shop): ?array
    {
        $candidates = [
            ['quotes', 'Start a quote', 'Build your first quote and see how pricing comes together.', 'quotes.create', Feature::QuoteBuilder, Quote::class, true],
            ['customers', 'Add your customers', 'Quotes and jobs are tied to a customer.', 'customers.create', Feature::Customers, Customer::class, false],
            ['materials', 'Add your materials', 'Material costs feed straight into quote totals.', 'materials.create', Feature::QuoteBuilder, Material::class, false],
            ['equipment', 'Add your equipment', 'Machine rates let quotes price shop time accurately.', 'equipment.create', Feature::QuoteBuilder, Equipment::class, false],
            ['business-partners', 'Add your business partners', 'Suppliers and subcontractors you buy from or send work to.', 'business-partners.create', Feature::QuoteBuilder, BusinessPartner::class, false],
        ];

        $steps = collect($candidates)
            ->filter(fn (array $c) => $shop->hasFeature($c[4]) && Gate::allows($c[3]))
            ->map(fn (array $c) => [
                'key' => $c[0],
                'title' => $c[1],
                'description' => $c[2],
                'href' => route($c[0].'.create'),
                'list_href' => route($c[0].'.index'),
                'done' => $c[5]::query()->exists(),
                'primary' => $c[6],
            ])
            ->values();

        return $steps->contains('done', false) ? $steps->all() : null;
    }

    /**
     * @param  Collection<int, Job>  $jobs
     * @param  Collection<int, Quote>  $openQuotes
     * @return array<string, int>
     */
    private function counts(Collection $jobs, Collection $openQuotes): array
    {
        return [
            'work_in_progress' => $this->workInProgress($jobs)->count(),
            'ready_to_invoice' => $this->readyToInvoice($jobs)->count(),
            'open_quotes' => $openQuotes->count(),
        ];
    }

    /**
     * Money figures, all bucketed by the shop's own calendar - "this month"
     * starts at midnight on the 1st where the shop is, not in UTC.
     *
     * @param  Collection<int, Job>  $jobs
     * @param  Collection<int, Quote>  $openQuotes
     * @return array<string, mixed>
     */
    private function financials(Shop $shop, Collection $jobs, Collection $openQuotes): array
    {
        $invoiced = $jobs->filter(fn (Job $job) => ! $job->isCancelled()
            && $job->jobStage->role === JobStageRole::Invoiced
            && $job->invoiced_at !== null);
        $thisMonth = $shop->localNow()->startOfMonth();

        $decided = Quote::query()
            ->whereIn('status', [QuoteStatus::Won, QuoteStatus::Lost])
            ->where('sent_at', '>=', now()->subDays($shop->winRateWindowDays()))
            ->pluck('status');

        return [
            'invoiced_this_month' => $this->sumTotals($this->invoicedDuring($invoiced, $thisMonth)),
            'invoiced_last_month' => $this->sumTotals($this->invoicedDuring($invoiced, $thisMonth->subMonthNoOverflow())),
            'work_in_progress' => $this->sumTotals($this->workInProgress($jobs)),
            'ready_to_invoice' => $this->sumTotals($this->readyToInvoice($jobs)),
            'open_quotes' => $this->sumTotals($openQuotes),
            'win_rate' => $decided->isEmpty()
                ? null
                : round($decided->filter(fn (QuoteStatus $status) => $status === QuoteStatus::Won)->count() / $decided->count() * 100),
            'win_rate_window_days' => $shop->winRateWindowDays(),
            'revenue_trend' => $this->revenueTrend($invoiced, $thisMonth),
        ];
    }

    /**
     * Invoiced revenue per calendar month, oldest first, including months
     * with nothing invoiced so the chart has no gaps.
     *
     * @param  Collection<int, Job>  $invoiced
     * @return array<int, array{month: string, label: string, total: float}>
     */
    private function revenueTrend(Collection $invoiced, CarbonImmutable $thisMonth): array
    {
        return collect(range(self::REVENUE_TREND_MONTHS - 1, 0))
            ->map(function (int $monthsAgo) use ($invoiced, $thisMonth) {
                $month = $thisMonth->subMonthsNoOverflow($monthsAgo);

                return [
                    'month' => $month->format('Y-m'),
                    'label' => $month->format('M'),
                    'total' => $this->sumTotals($this->invoicedDuring($invoiced, $month)),
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, Job>  $invoiced
     * @return Collection<int, Job>
     */
    private function invoicedDuring(Collection $invoiced, CarbonImmutable $monthStart): Collection
    {
        $monthEnd = $monthStart->addMonthNoOverflow();

        return $invoiced->filter(fn (Job $job) => $job->invoiced_at->gte($monthStart) && $job->invoiced_at->lt($monthEnd));
    }

    /**
     * Jobs still being worked on - not cancelled, and not yet shipped
     * (Shipped/Invoiced are finished production business even if the
     * invoice hasn't gone out yet).
     *
     * @param  Collection<int, Job>  $jobs
     * @return Collection<int, Job>
     */
    private function workInProgress(Collection $jobs): Collection
    {
        return $jobs->filter(fn (Job $job) => ! $job->isCancelled()
            && ! in_array($job->jobStage->role, [JobStageRole::Shipped, JobStageRole::Invoiced], true));
    }

    /**
     * @param  Collection<int, Job>  $jobs
     * @return Collection<int, Job>
     */
    private function readyToInvoice(Collection $jobs): Collection
    {
        return $jobs->filter(fn (Job $job) => ! $job->isCancelled() && $job->jobStage->role === JobStageRole::Shipped);
    }

    /**
     * Job counts per shop-configured stage - Invoiced (finished business)
     * and cancelled jobs are left off, since neither is work to keep an eye
     * on.
     *
     * @param  Collection<int, Job>  $jobs
     * @return array<int, array{status: string, label: string, count: int}>
     */
    private function pipeline(Collection $jobs): array
    {
        return JobStage::query()->orderBy('position')->get()
            ->reject(fn (JobStage $stage) => $stage->role === JobStageRole::Invoiced)
            ->map(fn (JobStage $stage) => [
                'status' => (string) $stage->id,
                'label' => $stage->name,
                'count' => $jobs->filter(fn (Job $job) => ! $job->isCancelled() && $job->job_stage_id === $stage->id)->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * Things someone should act on: shipped work nobody has billed yet,
     * invoices that failed to reach QuickBooks, and quotes waiting on the
     * customer long enough (per the shop's own threshold) to deserve a
     * follow-up. Amounts are left off for anyone who can't see financials.
     *
     * @param  Collection<int, Job>  $jobs
     * @param  Collection<int, Quote>  $openQuotes
     * @return array<int, array<string, mixed>>
     */
    private function attention(Shop $shop, Collection $jobs, Collection $openQuotes, bool $canSeeFinancials): array
    {
        $readyToInvoice = $this->readyToInvoice($jobs)->map(fn (Job $job) => [
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
            ->filter(fn (Quote $quote) => $quote->sent_at?->lte(now()->subDays($shop->staleQuoteDays())))
            ->sortBy('sent_at')
            ->map(fn (Quote $quote) => [
                'kind' => 'stale_quote',
                'title' => "Quote for {$quote->customer->name} - no reply in ".(int) $quote->sent_at->diffInDays(now()).' days',
                'subtitle' => 'Sent '.$quote->sent_at->copy()->setTimezone($shop->localTimezone())->format('M j').' - worth a follow-up call',
                'amount' => $quote->total,
                'href' => route('quotes.edit', $quote),
            ]);

        return $syncErrors->concat($readyToInvoice)->concat($staleQuotes)
            ->map(fn (array $item) => $canSeeFinancials ? $item : [...$item, 'amount' => null])
            ->values()
            ->all();
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
