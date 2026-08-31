<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AutopilotActionType;
use App\Enums\ConnectionStatus;
use App\Exceptions\ProviderNotConnected;
use App\Exceptions\TokenRefreshFailed;
use App\Models\OauthConnection;
use App\Services\Oauth\TokenService;

/**
 * Keep one business's connections alive, without anyone asking.
 *
 * FOUND-03: "Build RefreshOauthTokensJob (scheduled, continuous) updating
 * provider_health."
 *
 * Per business rather than platform-wide, for two reasons that happen to point
 * the same way. The tenant boundary wants it: a job is scoped to one tenant and
 * AutopilotJob establishes exactly one. And blast radius wants it: one business's
 * dead Google grant must not stop another business's Microsoft refresh, which is
 * what a single sweeping job would do the first time it threw.
 *
 * ONE PROVIDER'S FAILURE DOES NOT ABORT THE OTHERS. Each connection is refreshed
 * in its own try. A permanent failure has already been recorded and prompted by
 * the vault by the time it reaches here, so there is nothing left to do but move
 * on. A transient failure is collected and rethrown at the end, so the queue
 * retries the whole job with backoff — after the healthy providers have already
 * been refreshed, which makes the retry cheap.
 *
 * IDEMPOTENT BY CONSTRUCTION rather than by key. Running twice refreshes twice
 * at worst; the second run finds tokens that are no longer due and does nothing.
 * That is why idempotencyKey() stays null — a key here would suppress the
 * *next* cycle, which is exactly the work this job exists to do.
 *
 * @see docs/17-TIER1-EXECUTABLE-TICKETS.md FOUND-03
 */
final class RefreshOauthTokensJob extends AutopilotJob
{
    public function automationKey(): string
    {
        return 'oauth.refresh_tokens';
    }

    /**
     * Nothing owner-visible happens when a token quietly renews. The Reconnect
     * prompt is the feed entry, and the vault raises that itself.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        $vault = app(TokenService::class);

        $refreshed = 0;
        $reconnectNeeded = 0;
        $transient = null;

        $due = OauthConnection::query()
            ->where('status', ConnectionStatus::Active)
            ->get()
            ->filter(fn (OauthConnection $connection): bool => $vault->isDue($connection));

        foreach ($due as $connection) {
            try {
                $vault->refresh($connection);
                $refreshed++;
            } catch (ProviderNotConnected) {
                // Permanent. markUnusable() has already flipped the status,
                // written provider_health and raised the Reconnect prompt.
                $reconnectNeeded++;
            } catch (TokenRefreshFailed $e) {
                // Transient — provider unreachable or 5xx. Health is recorded;
                // keep going and let the queue bring us back.
                $transient = $e;
            }
        }

        if ($transient !== null) {
            throw $transient;
        }

        return [
            'considered' => $due->count(),
            'refreshed' => $refreshed,
            'reconnect_needed' => $reconnectNeeded,
        ];
    }

    /**
     * There is no handoff for this one, and saying so is the honest answer.
     *
     * `29` §2 rule 44 requires both paths on every automation because the review
     * engine must run with zero GBP API access. This job is not part of that
     * engine — it is the plumbing that makes provider access work at all. With
     * no provider reachable there is no token to renew and nothing a human could
     * do by hand instead; the owner-facing path is the Reconnect prompt, which
     * the vault raises directly.
     *
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return ['skipped' => 'no provider access'];
    }
}
