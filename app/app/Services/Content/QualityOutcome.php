<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\ContentQualityFailure;
use App\Services\Reviews\ModerationVerdict;

/**
 * What the quality gate decided, including the case where it decided nothing.
 *
 * ⛔ **THREE STATES, NOT TWO**, and the third is decision 347 transplanted:
 *
 *   cleared       every check ran and the page may go on            passed = true
 *   refused       a check refused it — {@see self::$failures} says which  passed = false
 *   undetermined  no verdict exists — {@see self::$unavailableReason}     passed = null
 *
 * A classifier declining to read the copy, or a provider that could not be
 * reached, produces the third. Collapsing it into "refused" tells a tenant their
 * writing broke a rule it did not break; collapsing it into "cleared" publishes
 * unmoderated text under their name during a vendor outage.
 * {@see ModerationVerdict} is the same shape one level
 * down, and 346 is where the argument was had.
 *
 * ⚠️ **BOTH OF THE LAST TWO HOLD THE PAGE.** The difference is what an owner is
 * told and whether anybody should go and look at a vendor — not whether the page
 * ships.
 */
final readonly class QualityOutcome
{
    /**
     * @param  list<ContentQualityFailure>  $failures
     */
    private function __construct(
        public ?bool $passed,
        public int $uniquenessScore,
        public int $firstPartyDataCount,
        public ?int $readingGrade,
        public int $qualityScore,
        public array $failures = [],
        public ?string $unavailableReason = null,
    ) {}

    /**
     * @param  list<ContentQualityFailure>  $failures
     */
    public static function decided(
        bool $passed,
        int $uniquenessScore,
        int $firstPartyDataCount,
        ?int $readingGrade,
        int $qualityScore,
        array $failures = [],
    ): self {
        return new self($passed, $uniquenessScore, $firstPartyDataCount, $readingGrade, $qualityScore, $failures);
    }

    /**
     * Nobody could say. `$reason` is for the check row and an operator, never
     * for the tenant — see {@see ContentQualityFailure::sentence()} for what a
     * tenant is shown, and note there is deliberately no case for this.
     */
    public static function undetermined(
        string $reason,
        int $uniquenessScore,
        int $firstPartyDataCount,
        ?int $readingGrade,
        int $qualityScore,
    ): self {
        return new self(null, $uniquenessScore, $firstPartyDataCount, $readingGrade, $qualityScore, [], $reason);
    }

    public function cleared(): bool
    {
        return $this->passed === true;
    }

    public function wasUndetermined(): bool
    {
        return $this->passed === null;
    }

    /**
     * Whether this page has to wait for a person. Both of the non-clearing
     * states do.
     */
    public function holds(): bool
    {
        return $this->passed !== true;
    }

    /**
     * @return list<string>
     */
    public function failureValues(): array
    {
        return array_map(
            static fn (ContentQualityFailure $failure): string => $failure->value,
            $this->failures,
        );
    }
}
