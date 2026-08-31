<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Enums\OauthProvider;
use App\Exceptions\ProviderRequestFailed;
use App\Models\Business;
use App\Services\Oauth\TokenService;
use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * What every provider client shares: a token, a logged call, a classified
 * failure.
 *
 * FOUND-03 requires all three provider clients to route through TokenService,
 * and this is where that is enforced rather than remembered. A subclass never
 * touches a credential — it asks for an authorized request and gets one.
 *
 * Every call goes through Laravel's HTTP client, which is what makes
 * Http::fake() and Http::preventStrayRequests() effective. A client that built
 * its own Guzzle instance would be invisible to both, and a test run could reach
 * a live endpoint.
 *
 * NO SYNCHRONOUS RETRY. One attempt, then a classified ProviderRequestFailed.
 * Retrying belongs to the job, with backoff and jitter, so a provider outage
 * never holds a worker open or turns into a thundering herd.
 */
abstract class ProviderClient
{
    public function __construct(
        protected readonly TokenService $tokens,
    ) {}

    abstract protected function provider(): OauthProvider;

    /**
     * A request already carrying a valid bearer token for this business.
     *
     * The token is fetched per call rather than cached on the instance: it may
     * be refreshed underneath us between two calls, and a stale instance
     * property would send the old one.
     */
    protected function authorized(Business $business): PendingRequest
    {
        return Http::withToken($this->tokens->getValidToken($business, $this->provider()))
            ->timeout((int) config('oauth.timeout', 10))
            ->acceptJson();
    }

    /**
     * Perform a call, log its shape, and turn any failure into our own type.
     *
     * ⚠️ **THE CONNECTION CATCH DELIBERATELY DOES NOT RECORD HEALTH, AND UNTIL
     * 2026-08-21 THAT WAS TRUE HERE AND ARGUED NOWHERE** (7261). A
     * `ConnectionException` means the request never became a response, so the
     * vendor said nothing and no column of `provider_health` — every one of
     * which is a sentence about the vendor's side — has a true value to take.
     * The `VendorLog` failure entry is the whole record, and it names our call
     * rather than the tenant's grant.
     *
     * ⛔ **`GoogleSearchConsoleClient::send()`, THE ONE CLIENT ON THIS PATH THAT
     * DOES NOT EXTEND THIS CLASS, DID THE OPPOSITE** — `recordHealth(Gsc,
     * 'error', 'connection')` inside its connection catch, so our own DNS
     * failure marked a tenant's OAuth connection unhealthy. It was the only site
     * of that shape in the application and it now matches this method.
     * `tests/Feature/Architecture/VisibilityTest.php`'s *"a failure that never
     * reached the vendor is never recorded as a fact about the tenant"* fails
     * the build on the next one, in either file.
     *
     * ⚠️ **AND THE RESPONSE BRANCH BELOW WAS WRONG IN BOTH CLIENTS AT ONCE,
     * WHICH IS WHY IT SURVIVED THAT REVIEW** (7400). A vendor 5xx was written
     * as `token_status = 'error'` against the tenant's grant here too — the
     * defect 7261 removed from the *transport* case, one HTTP status later.
     * Being identical in both files made it read as considered rather than as
     * unargued, while `TokenService::refresh()` had treated the same 503 as
     * blameless from the day it was written. **Consistency between two callers
     * is not an argument; it is what an unexamined copy looks like.** The
     * status is now {@see TokenService::recordCallFailure()}'s to decide.
     *
     * @param  callable(): Response  $call
     *
     * @throws ProviderRequestFailed
     */
    protected function send(
        Business $business,
        string $method,
        string $url,
        callable $call,
    ): Response {
        try {
            $response = VendorLog::timed(
                $this->provider()->value,
                $method,
                $url,
                $call,
                $business->getKey(),
            );
        } catch (ConnectionException $e) {
            VendorLog::failure(
                $this->provider()->value,
                $method,
                $url,
                $e::class,
                $business->getKey(),
            );

            throw ProviderRequestFailed::unreachable($this->provider(), 'connection');
        }

        if ($response->failed()) {
            $failure = ProviderRequestFailed::from($this->provider(), $response);

            // ⛔ **THIS READ `$failure->quota ? 'quota_exhausted' : 'error'`, SO
            // A VENDOR'S 5xx WAS FILED AS `token_status = 'error'` AGAINST THE
            // TENANT'S GRANT** (7400) — the same sentence for a 503 as for a
            // 403. Health carries our own short reason code, never the vendor's
            // body, and the status now comes from the one place that decides
            // what a failed answer says about a grant. `GoogleSearchConsoleClient`
            // makes the identical call; that they used to compute it separately
            // is how they came to disagree with `TokenService::refresh()` and
            // agree with each other by accident.
            $this->tokens->recordCallFailure(
                $this->provider(),
                $failure->status,
                $failure->quota,
                $failure->reason,
            );

            throw $failure;
        }

        $this->tokens->recordHealth($this->provider(), 'active', null, now());

        return $response;
    }
}
