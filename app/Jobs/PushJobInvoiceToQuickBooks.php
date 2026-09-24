<?php

namespace App\Jobs;

use App\Models\Job;
use App\Services\QuickBooks\QuickBooksClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Pushes a job's invoice to QuickBooks once it reaches the Invoiced status.
 * Dedupes the customer first (search by email before creating a new one),
 * then creates the invoice from the job's quote line items. A no-op if the
 * shop hasn't connected QuickBooks.
 *
 * Failures are recorded on the job but rethrown so the queue's own
 * retry/backoff takes over - "a push that fails and needs retry rather than
 * silently dropping the invoice" is an explicit acceptance case, not
 * something to swallow here.
 */
class PushJobInvoiceToQuickBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    // Named $fabricationJob, not $job - InteractsWithQueue already declares
    // a $job property (the underlying queue job wrapper) and PHP won't
    // allow redeclaring it with an incompatible type.
    public function __construct(public Job $fabricationJob) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 3600];
    }

    public function handle(QuickBooksClient $client): void
    {
        $connection = $this->fabricationJob->shop->quickBooksConnection;

        if (! $connection) {
            return;
        }

        try {
            $connection = $client->ensureFreshToken($connection);
            $customer = $this->fabricationJob->customer;

            if (! $customer->quickbooks_customer_id) {
                $existing = $customer->email
                    ? $client->findCustomerByEmail($connection, $customer->email)
                    : null;

                $customer->quickbooks_customer_id = $existing['Id']
                    ?? $client->createCustomer($connection, $customer)['Id'];
                $customer->save();
            }

            $invoice = $client->createInvoice($connection, $this->fabricationJob, $customer->quickbooks_customer_id);

            $this->fabricationJob->quickbooks_invoice_id = $invoice['Id'];
            $this->fabricationJob->quickbooks_synced_at = now();
            $this->fabricationJob->quickbooks_sync_error = null;
            $this->fabricationJob->save();
        } catch (Throwable $e) {
            $this->fabricationJob->quickbooks_sync_error = $e->getMessage();
            $this->fabricationJob->save();

            throw $e;
        }
    }
}
