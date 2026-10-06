<?php

namespace App\Http\Controllers;

use App\Enums\JobStageRole;
use App\Enums\QuoteStatus;
use App\Events\JobBoardUpdated;
use App\Jobs\GenerateAndSendQuotePdf;
use App\Models\Job;
use App\Models\JobStage;
use App\Models\Quote;
use App\Support\QuoteHistory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Drives the quote lifecycle (draft -> sent -> won/lost -> archived, plus
 * won -> converted to a job) - kept separate from QuoteController's CRUD so
 * each state transition is its own small, guarded action rather than a
 * generic "update anything" endpoint.
 */
class QuoteWorkflowController extends Controller
{
    /** Recorded as the reason when a quote is marked sent while emailing is off. */
    private const EMAILING_OFF_REASON = 'Quote emailing is turned off for this shop';

    public function send(Quote $quote): RedirectResponse
    {
        $this->guardEmailingEnabled();
        $this->guard($quote->status === QuoteStatus::Draft && ! $quote->is_archived, 'This quote has already been sent.');
        $this->guard($quote->lineItems->isNotEmpty(), 'A quote needs at least one line item before it can be sent.');
        $this->guard(filled($quote->customer->email), 'This customer has no email address on file.');

        $quote->update(['status' => QuoteStatus::Sent, 'sent_at' => now(), 'email_failed_at' => null]);

        GenerateAndSendQuotePdf::dispatch($quote);
        QuoteHistory::log($quote, 'sent', "Emailed to {$quote->customer->email}");

        return back()->with('status', 'Quote sent to customer.');
    }

    /**
     * Mark a draft as sent to the customer without emailing it - they may
     * have agreed verbally, or been handed a copy. The reason is required so
     * the quote's history still explains why no email went out.
     */
    public function skipSend(Request $request, Quote $quote): RedirectResponse
    {
        $this->guard($quote->status === QuoteStatus::Draft && ! $quote->is_archived, 'This quote has already been sent.');
        $this->guard($quote->lineItems->isNotEmpty(), 'A quote needs at least one line item before it can be marked as sent.');

        // With emailing off there is nothing to explain - not emailing is
        // simply how this shop works - so no reason is asked for.
        $reason = Auth::user()->currentShop->quoteEmailingEnabled()
            ? trim($request->validate(['reason' => ['required', 'string', 'max:255']])['reason'])
            : self::EMAILING_OFF_REASON;

        $quote->update([
            'status' => QuoteStatus::Sent,
            'sent_at' => now(),
            'send_skipped_reason' => $reason,
        ]);
        QuoteHistory::log($quote, 'send_skipped', "Marked as sent without emailing - {$reason}");

        return back()->with('status', 'Quote marked as sent without emailing the customer.');
    }

    /**
     * Email a sent quote again (the first one may never have arrived), or
     * email one whose sending was skipped. Only while the customer's answer
     * is still pending - once won, lost or archived the quote is closed out.
     */
    public function resend(Quote $quote): RedirectResponse
    {
        $this->guardEmailingEnabled();
        $this->guard($quote->status === QuoteStatus::Sent && ! $quote->is_archived, 'Only a sent quote that is still awaiting an answer can be emailed.');
        $this->guard(filled($quote->customer->email), 'This customer has no email address on file.');

        // emailed_at is left alone: it still records the earlier send until the
        // new one goes out and replaces it.
        $quote->update(['send_skipped_reason' => null, 'email_failed_at' => null]);

        GenerateAndSendQuotePdf::dispatch($quote);
        QuoteHistory::log($quote, 'resent', "Emailed again to {$quote->customer->email}");

        return back()->with('status', 'Quote emailed to the customer.');
    }

    /**
     * A fresh draft with the same customer, notes, due date and line items -
     * nothing about the original's lifecycle (status, sending, history) or
     * its attachments comes along.
     */
    public function duplicate(Quote $quote): RedirectResponse
    {
        $copy = DB::transaction(function () use ($quote): Quote {
            $copy = Quote::create([
                'customer_id' => $quote->customer_id,
                'notes' => $quote->notes,
                'due_date' => $quote->due_date,
                'status' => QuoteStatus::Draft,
            ]);

            $copy->lineItems()->saveMany($quote->lineItems->map->replicate());

            return $copy;
        });

        QuoteHistory::log($copy, 'created', "Duplicated from quote #{$quote->id}");
        QuoteHistory::log($quote, 'duplicated', "Duplicated as quote #{$copy->id}");

        return redirect()->route('quotes.edit', $copy)->with('status', 'Quote duplicated as a new draft.');
    }

    public function markWon(Quote $quote): RedirectResponse
    {
        $this->guard($quote->status === QuoteStatus::Sent, 'Only a sent quote can be marked won.');

        $quote->update(['status' => QuoteStatus::Won]);
        QuoteHistory::log($quote, 'won', 'Marked won');

        return back()->with('status', 'Quote marked won.');
    }

    public function markLost(Quote $quote): RedirectResponse
    {
        $this->guard($quote->status === QuoteStatus::Sent, 'Only a sent quote can be marked lost.');

        $quote->update(['status' => QuoteStatus::Lost]);
        QuoteHistory::log($quote, 'lost', 'Marked lost');

        return back()->with('status', 'Quote marked lost.');
    }

    public function archive(Quote $quote): RedirectResponse
    {
        $this->guard($quote->status === QuoteStatus::Lost, 'Only a lost quote can be archived.');

        $quote->update(['archived_at' => now()]);
        QuoteHistory::log($quote, 'archived', 'Archived');

        return redirect()->route('quotes.index')->with('status', 'Quote archived.');
    }

    public function reopen(Quote $quote): RedirectResponse
    {
        $this->guard($quote->is_archived, 'This quote is not archived.');

        $quote->update([
            'archived_at' => null,
            'status' => QuoteStatus::Draft,
            'emailed_at' => null,
            'send_skipped_reason' => null,
        ]);
        QuoteHistory::log($quote, 'reopened', 'Reopened as a draft');

        return redirect()->route('quotes.edit', $quote)->with('status', 'Quote reopened as a draft.');
    }

    public function convert(Quote $quote): RedirectResponse
    {
        $this->guard($quote->status === QuoteStatus::Won, 'Only a won quote can be converted to a job.');
        $this->guard(! $quote->job()->exists(), 'This quote has already been converted to a job.');

        $notStarted = JobStage::query()->where('role', JobStageRole::NotStarted)->firstOrFail();

        $job = DB::transaction(function () use ($quote, $notStarted): Job {
            $job = $quote->job()->create([
                'customer_id' => $quote->customer_id,
                'job_number' => Job::nextNumber(),
                'job_stage_id' => $notStarted->id,
                'due_date' => $quote->due_date,
            ]);

            // Copies, not moves: the quote keeps its own files as the
            // record of what was quoted against.
            foreach ($quote->attachments() as $attachment) {
                $attachment->copy($job, 'attachments');
            }

            return $job;
        });

        // The job's own history starts with where it came from and any due
        // date it inherited, so that date isn't unexplained later.
        activity()->performedOn($job)->causedBy(Auth::user())->log("Created from quote #{$quote->id}");

        if ($job->due_date !== null) {
            activity()->performedOn($job)->causedBy(Auth::user())
                ->log('Due date set to '.$job->due_date->format('M j, Y').' (from the quote)');
        }

        QuoteHistory::log($quote, 'converted', "Converted to job {$job->job_number}");

        // A converting user is redirected to the job itself, not the board -
        // this is what tells anyone else already watching the board (a
        // kiosk display, another tab) that a new job just landed, the same
        // bell/border-flash signal a stage move or reassignment triggers.
        broadcast(new JobBoardUpdated($job->shop_id))->toOthers();

        return redirect()->route('jobs.show', $job)->with('status', 'Job created.');
    }

    /**
     * The PDF the customer would get, rendered now from the quote as last
     * saved and shown inline - nothing is stored or emailed, so it works at
     * any stage (including before the quote has ever been sent).
     */
    public function previewPdf(Quote $quote): Response
    {
        return Pdf::loadView('quotes.pdf', ['quote' => $quote])
            ->stream("quote-{$quote->id}-preview.pdf");
    }

    public function downloadPdf(Request $request, Quote $quote): StreamedResponse
    {
        abort_unless($quote->pdf(), 404);

        return $quote->pdf()->toInlineResponse($request);
    }

    /**
     * @throws ValidationException
     */
    private function guardEmailingEnabled(): void
    {
        $this->guard(Auth::user()->currentShop->quoteEmailingEnabled(), 'Emailing quotes is turned off for this shop. You can turn it on in Shop settings.');
    }

    /**
     * @throws ValidationException
     */
    private function guard(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }
}
