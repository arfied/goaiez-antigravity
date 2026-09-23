<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\AuditStatus;
use App\Jobs\PublicAuditJob;
use App\Models\PublicAudit;
use App\Services\Config\DefaultsRegistry;
use App\Services\Places\PlaceResolver;
use Illuminate\Support\Carbon;

/**
 * Turns "audit this business" into a row and a queued job — or into a question.
 *
 * The whole of `POST /api/public/audit` that is not HTTP. It sits between the
 * resolver (which knows how to turn a name into place ids) and the job (which
 * knows how to spend money on one), and it owns the two decisions neither of
 * them can make: whether the visitor has told us enough to start, and whether we
 * should start at all given we may already know the answer.
 */
final class PublicAuditStarter
{
    /**
     * How recently an audit must have completed for a new request against the
     * same place to reuse it.
     *
     * Deliberately the same 24 hours as the Places details cache. The two are
     * one decision wearing two hats: within the cache window a fresh audit would
     * re-derive the same findings from the same cached place data, so it would
     * produce an identical result and differ only in having burned a Nearby
     * Search to do it.
     */
    public const int REUSE_WINDOW_SECONDS = 86400;

    public function reuseWindowSeconds(): int
    {
        return $this->defaults->int('audit.public.reuse_window_seconds');
    }

    public function __construct(
        private readonly PlaceResolver $resolver,
        private readonly DefaultsRegistry $defaults,
    ) {}

    /**
     * Start an audit from a typed name or from a place the visitor has chosen.
     *
     * $placeId short-circuits resolution entirely, which is what makes the
     * confirm step free: the first request paid for the Text Search that
     * produced the candidates, and coming back with a choice must not pay for it
     * again.
     */
    public function start(?string $placeQuery, ?string $placeId, ?string $ipHash): AuditStartOutcome
    {
        if ($placeId === null) {
            $outcome = $this->resolver->resolveName((string) $placeQuery);

            if ($outcome->isAmbiguous()) {
                return AuditStartOutcome::ambiguous($outcome->candidates);
            }

            $candidate = $outcome->candidate();

            if ($candidate === null) {
                return AuditStartOutcome::unavailable($outcome->reason ?? 'nothing_found');
            }

            $placeId = $candidate->placeId;
        }

        $recent = $this->recentCompleted($placeId);

        return AuditStartOutcome::started(
            $recent instanceof PublicAudit
                ? $this->copyOf($recent, $ipHash)
                : $this->queue($placeId, $ipHash),
        );
    }

    /**
     * A completed audit of this place from within the reuse window.
     *
     * Completed only. A `Running` audit is a job in flight whose findings are
     * half-written, and copying one would hand a visitor a partial result that
     * never finishes filling in — the polling client would sit on a terminal
     * status with three of four checks missing.
     */
    private function recentCompleted(string $placeId): ?PublicAudit
    {
        return PublicAudit::query()
            ->live()
            ->where('place_id', $placeId)
            ->where('status', AuditStatus::Complete)
            ->where('created_at', '>', Carbon::now()->subSeconds($this->reuseWindowSeconds()))
            ->latest('created_at')
            ->first();
    }

    /**
     * Copy a recent result into a new row rather than handing back its token.
     *
     * Returning the original token would be one row cheaper and is the obvious
     * implementation. It is rejected for two reasons that both only appear
     * later: two strangers would share one URL, so the second visitor's link
     * would expire on the first visitor's 90-day clock and could be pruned out
     * from under them; and the row's `ip_hash` would keep naming whoever
     * happened to ask first, which quietly corrupts the abuse counter that
     * decides who gets a captcha.
     *
     * A copy costs one row of duplicated public information about a public
     * listing, and **no Places spend at all** — which is the part that mattered.
     */
    private function copyOf(PublicAudit $source, ?string $ipHash): PublicAudit
    {
        $audit = new PublicAudit;

        $audit->token = PublicAudit::newToken();

        $audit->forceFill([
            'place_id' => $source->place_id,
            'name_snapshot' => $source->name_snapshot,
            'status' => AuditStatus::Complete,
            'score' => $source->score,
            'findings' => $source->findings,
            'checks' => $source->checks,
            // This visitor's hash, never the original's — see the docblock.
            'ip_hash' => $ipHash,
            // A full window from now, for the same reason.
            'expires_at' => PublicAudit::expiryFromNow(),
        ])->save();

        return $audit;
    }

    private function queue(string $placeId, ?string $ipHash): PublicAudit
    {
        $audit = new PublicAudit;

        $audit->token = PublicAudit::newToken();

        $audit->forceFill([
            'place_id' => $placeId,
            'status' => AuditStatus::Queued,
            'findings' => [],
            'checks' => [],
            'ip_hash' => $ipHash,
            'expires_at' => PublicAudit::expiryFromNow(),
        ])->save();

        PublicAuditJob::dispatch($audit->token);

        return $audit;
    }
}
