<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\AuditCheckKey;

/**
 * What one check produced — including the case where it produced nothing and
 * says so.
 *
 * THIS CLASS EXISTS FOR ITS SECOND CONSTRUCTOR. `BUILD-PLAN` §2.5.3 makes it a
 * test for slice E that "a check that cannot run says so rather than
 * fabricating", and `40` §6.4 makes the same demand of every degraded source:
 * never render stale or absent data as fresh. A check that returns an empty
 * findings list has said "we looked and everything is fine", which is a
 * different and much worse sentence than "we could not look".
 *
 * The two are indistinguishable if a check can only return findings — which is
 * why `unavailable()` is a first-class outcome carrying its own reason, and why
 * AuditScore excludes an unavailable check from the denominator rather than
 * scoring it as zero. Scoring it as zero would punish a business for our
 * budget being exhausted.
 */
final readonly class CheckResult
{
    /**
     * @param  list<Finding>  $findings
     * @param  ?string  $unavailableReason
     *                                      A short code, never a sentence — slice G maps it to
     *                                      copy. Codes travel into logs; sentences change.
     */
    private function __construct(
        public AuditCheckKey $check,
        public bool $ran,
        public array $findings = [],
        public ?string $unavailableReason = null,
    ) {}

    /**
     * @param  list<Finding>  $findings
     */
    public static function ran(AuditCheckKey $check, array $findings): self
    {
        return new self($check, true, $findings);
    }

    /**
     * The check could not run. The visitor is told which check and why, and the
     * score is computed as though this check was never offered.
     */
    public static function unavailable(AuditCheckKey $check, string $reason): self
    {
        return new self($check, false, [], $reason);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findingsArray(): array
    {
        return array_map(
            static fn (Finding $finding): array => $finding->toArray(),
            $this->findings,
        );
    }

    public function problemCount(): int
    {
        return count(array_filter(
            $this->findings,
            static fn (Finding $finding): bool => $finding->severity->isProblem(),
        ));
    }
}
