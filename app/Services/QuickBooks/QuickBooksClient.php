<?php

namespace App\Services\QuickBooks;

use App\Models\Customer;
use App\Models\Job;
use App\Models\QuickBooksConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Thin wrapper over Intuit's OAuth2 + Accounting REST API, built directly on
 * Laravel's HTTP client rather than the official quickbooks/v3-php-sdk - it
 * has its own HTTP internals that bypass Http::fake(), which would make the
 * acceptance cases (token expiry, dedupe, retry-on-failure) much harder to
 * cover with tests instead of a real sandbox company.
 */
class QuickBooksClient
{
    private const AUTHORIZE_URL = 'https://appcenter.intuit.com/connect/oauth2';

    private const TOKEN_URL = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';

    private const REVOKE_URL = 'https://developer.api.intuit.com/v2/oauth2/tokens/revoke';

    private const SCOPE = 'com.intuit.quickbooks.accounting';

    public function authorizationUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => config('services.quickbooks.client_id'),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'redirect_uri' => config('services.quickbooks.redirect_uri'),
            'state' => $state,
        ]);
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, x_refresh_token_expires_in: int}
     */
    public function exchangeCodeForTokens(string $code): array
    {
        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('services.quickbooks.redirect_uri'),
        ]);
    }

    public function refreshTokens(QuickBooksConnection $connection): void
    {
        $response = $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
        ]);

        $connection->update([
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'],
            'expires_at' => now()->addSeconds($response['expires_in']),
            'refresh_token_expires_at' => now()->addSeconds($response['x_refresh_token_expires_in']),
        ]);
    }

    /**
     * Refreshes and returns an up-to-date connection if the stored token has
     * expired - called before every API request so a sync mid-way through a
     * long-lived token's life never has to think about expiry itself.
     */
    public function ensureFreshToken(QuickBooksConnection $connection): QuickBooksConnection
    {
        if ($connection->isExpired()) {
            $this->refreshTokens($connection);

            return $connection->fresh();
        }

        return $connection;
    }

    /**
     * Best-effort - the connection is deleted locally regardless of whether
     * this succeeds (see QuickBooksConnectionController::destroy()). A
     * disconnect click shouldn't hang or fail because Intuit's revoke
     * endpoint is slow or already considers the token invalid.
     */
    public function revoke(QuickBooksConnection $connection): void
    {
        try {
            Http::asForm()
                ->withBasicAuth(config('services.quickbooks.client_id'), config('services.quickbooks.client_secret'))
                ->timeout(5)
                ->post(self::REVOKE_URL, ['token' => $connection->refresh_token]);
        } catch (Throwable) {
            // Local disconnect proceeds either way - see docblock above.
        }
    }

    /**
     * Dedupe check - a customer already in QuickBooks should be linked, not
     * duplicated. Returns the matching QBO Customer resource, or null.
     *
     * @return array<string, mixed>|null
     */
    public function findCustomerByEmail(QuickBooksConnection $connection, string $email): ?array
    {
        $escaped = addslashes($email);

        $response = $this->apiRequest($connection, 'GET', 'query', [
            'query' => "select * from Customer where PrimaryEmailAddr = '{$escaped}'",
        ]);

        return $response['QueryResponse']['Customer'][0] ?? null;
    }

    /**
     * @return array<string, mixed> the created QBO Customer resource
     */
    public function createCustomer(QuickBooksConnection $connection, Customer $customer): array
    {
        $response = $this->apiRequest($connection, 'POST', 'customer', array_filter([
            'DisplayName' => $customer->name,
            'PrimaryEmailAddr' => $customer->email ? ['Address' => $customer->email] : null,
            'PrimaryPhone' => $customer->phone ? ['FreeFormNumber' => $customer->phone] : null,
        ]));

        return $response['Customer'];
    }

    /**
     * @return array<string, mixed> the created QBO Invoice resource
     */
    public function createInvoice(QuickBooksConnection $connection, Job $job, string $quickbooksCustomerId): array
    {
        $lines = $job->quote->lineItems->map(fn ($item) => [
            'DetailType' => 'SalesItemLineDetail',
            'Amount' => (float) $item->total,
            'Description' => $item->description,
            'SalesItemLineDetail' => [
                'Qty' => (float) $item->quantity,
                'UnitPrice' => (float) $item->unit_cost,
            ],
        ])->values()->all();

        $response = $this->apiRequest($connection, 'POST', 'invoice', [
            'CustomerRef' => ['value' => $quickbooksCustomerId],
            // The invoice date as the shop's own calendar has it - a job
            // invoiced at 7pm Pacific is still that day, not "tomorrow" UTC.
            'TxnDate' => ($job->invoiced_at ?? now())->copy()->setTimezone($job->shop->localTimezone())->toDateString(),
            'Line' => $lines,
        ]);

        return $response['Invoice'];
    }

    /**
     * @param  array<string, string>  $payload
     * @return array<string, mixed>
     */
    private function tokenRequest(array $payload): array
    {
        $response = Http::asForm()
            ->withBasicAuth(config('services.quickbooks.client_id'), config('services.quickbooks.client_secret'))
            ->acceptJson()
            ->timeout(10)
            ->post(self::TOKEN_URL, $payload);

        if ($response->failed()) {
            throw new RuntimeException('QuickBooks token request failed: '.$response->body());
        }

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function apiRequest(QuickBooksConnection $connection, string $method, string $endpoint, array $payload = []): array
    {
        $url = $this->apiBaseUrl()."/v3/company/{$connection->realm_id}/{$endpoint}";

        $request = Http::withToken($connection->access_token)->acceptJson()->timeout(15);

        $response = $method === 'GET' ? $request->get($url, $payload) : $request->post($url, $payload);

        if ($response->failed()) {
            throw new RuntimeException("QuickBooks API request to {$endpoint} failed ({$response->status()}): {$response->body()}");
        }

        return $response->json();
    }

    private function apiBaseUrl(): string
    {
        return config('services.quickbooks.environment') === 'production'
            ? 'https://quickbooks.api.intuit.com'
            : 'https://sandbox-quickbooks.api.intuit.com';
    }
}
