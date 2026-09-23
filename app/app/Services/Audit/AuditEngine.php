<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Contracts\AuditCheck;
use App\Enums\AuditStatus;
use App\Models\PublicAudit;
use App\Services\Config\DefaultsRegistry;

/**
 * Runs the four checks against one audit row and writes what they find, as they
 * find it.
 *
 * STREAMING IS A PERSISTENCE DECISION, NOT A TRANSPORT ONE. `29` §6.2 specifies
 * a "streamed result … findings appear as computed" and a client that "polls
 * `GET /api/public/audit/{token}`". Polling can only stream what is already in
 * the row, so the row is written after every check rather than once at the end.
 * That costs three extra UPDATEs on a table with no contention and buys the
 * entire perceived-speed property the <20s budget is really about: the first
 * card appears as soon as Place Details answers, not after a stranger's web
 * server has finished deciding whether to reply.
 *
 * It also means a job that dies halfway leaves two real checks on the page
 * instead of nothing.
 *
 * NO CHECK CAN BREAK ANOTHER. Each is wrapped, and a throw becomes an
 * unavailable result for that check alone. The AuditCheck contract already says
 * implementations must not throw — this is the belt to that braces, and it earns
 * its place because the alternative is that one malformed page on one site takes
 * out the three checks that already succeeded and the score with them.
 */
final class AuditEngine
{
    /**
     * The perceived budget `29` §6.2 sets: "Under 20s perceived".
     *
     * Declared here as the single figure everything else is derived from and
     * tested against — PublicAuditJob's timeout, the fetch timeout, and the
     * Places client's own. A number that lives in three files drifts in two of
     * them.
     */
    public const int BUDGET_SECONDS = 20;

    /**
     * @param  list<AuditCheck>  $checks  In AuditCheckKey::inRunOrder().
     */
    public function __construct(
        private readonly DefaultsRegistry $defaults,
        private readonly AuditContextBuilder $contexts,
        private readonly array $checks,
    ) {}

    /**
     * Run every check against this audit, persisting as it goes.
     *
     * Returns the audit in its terminal state. Never throws for a data reason —
     * the context builder has already turned every network and budget failure
     * into a reason code, and an audit with no usable subject ends as `Failed`
     * with an empty findings list rather than as an exception on a marketing
     * page.
     */
    public function run(PublicAudit $audit): PublicAudit
    {
        $audit->forceFill(['status' => AuditStatus::Running])->save();

        $context = $this->contexts->build((string) $audit->place_id);

        if ($context->place !== null) {
            // Snapshotted rather than looked up again later: `public_audits` has
            // no tenant and no relationships, and the name is what makes a
            // 90-day-old shared link legible.
            //
            // The categories ride along because they are already in this
            // response — Place Details was billed once at the Atmosphere tier
            // and returns `primaryType` and `types` regardless. Slice H copies
            // them into the wizard, so a business that just told Google what it
            // does is not asked again thirty seconds later. Primary first,
            // because that is the one the wizard shows and the rest are context.
            $audit->forceFill(array_filter([
                'name_snapshot' => $context->place->displayName,
                'categories' => $context->place->categories(),
            ], static fn (mixed $value): bool => $value !== null && $value !== []))->save();
        }

        $results = [];

        foreach ($this->checks as $check) {
            $results[] = $this->runOne($check, $context);

            $this->persist($audit, $results, AuditStatus::Running);
        }

        $this->persist($audit, $results, $this->terminalStatus($results));

        return $audit;
    }

    // The category normalisation that used to live here is now
    // `PlaceSummary::categories()`. It moved because the wizard's confirmation
    // path acquired a second consumer of the same list — see that method for why
    // two copies of it would be a defect nobody would see.

    /**
     * @param  list<CheckResult>  $results
     */
    private function terminalStatus(array $results): AuditStatus
    {
        // An audit where nothing could be checked has no result to show and must
        // say so — AuditStatus::Failed exists for "the place did not resolve, or
        // the daily budget was exhausted". A partial audit is a Complete one;
        // the checks column carries which parts are missing and why.
        return AuditScore::checksRan($results) > 0
            ? AuditStatus::Complete
            : AuditStatus::Failed;
    }

    private function runOne(AuditCheck $check, AuditContext $context): CheckResult
    {
        try {
            return $check->run($context);
        } catch (\Throwable $e) {
            report($e);

            return CheckResult::unavailable($check->key(), 'check_error');
        }
    }

    /**
     * @param  list<CheckResult>  $results
     */
    private function persist(PublicAudit $audit, array $results, AuditStatus $status): void
    {
        $findings = [];

        foreach ($results as $result) {
            foreach ($result->findingsArray() as $finding) {
                $findings[] = $finding;
            }
        }

        $audit->forceFill([
            'status' => $status,
            'findings' => $findings,
            'checks' => array_map(
                static fn (CheckResult $result): array => [
                    'key' => $result->check->value,
                    'label' => $result->check->label(),
                    'ran' => $result->ran,
                    'reason' => $result->unavailableReason,
                ],
                $results,
            ),
            // Written on every pass so the dial moves as findings arrive rather
            // than snapping from nothing to a final number at the end.
            'score' => AuditScore::from($results),
        ])->save();
    }

    public function budgetSeconds(): int
    {
        return $this->defaults->int('audit.engine.budget_seconds');
    }
}
