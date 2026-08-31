<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How much one audit finding costs the business (`29` §6.2 — each finding is
 * "mapped to a plain sentence + severity").
 *
 * THREE LEVELS, AND ONE OF THEM IS GOOD NEWS. An audit that only lists problems
 * reads as a sales pitch, and a visitor who has already done the obvious things
 * has no way to tell whether we noticed. `Healthy` findings are what make the
 * `Critical` ones credible — and they cost nothing extra, because every check
 * has already computed the answer by the time it knows there is no problem.
 *
 * SEVERITY IS NOT COLOUR. `22` is explicit that colour is information and never
 * the sole indicator, so the public page pairs each of these with an icon and a
 * label. What lives here is the weight the score is built from; the rendering
 * decision belongs to slice G.
 */
enum FindingSeverity: string
{
    /** Costing the business customers now — a missing phone, an unreachable site. */
    case Critical = 'critical';

    /** Weakening results rather than blocking them — thin photos, no description. */
    case Attention = 'attention';

    /** Checked and working. Carried so the audit can say what is already right. */
    case Healthy = 'healthy';

    /**
     * Points this finding earns out of its check's maximum.
     *
     * Earned rather than deducted, because the score is computed over the checks
     * that actually ran (AuditScore). A deduction model needs a starting total,
     * and that total would have to move every time a check could not run — which
     * is exactly the arithmetic that stops being defensible.
     */
    public function points(): int
    {
        return match ($this) {
            self::Healthy => 2,
            self::Attention => 1,
            self::Critical => 0,
        };
    }

    /**
     * The most any single finding can earn, for the score's denominator.
     */
    public static function maxPoints(): int
    {
        return self::Healthy->points();
    }

    public function isProblem(): bool
    {
        return $this !== self::Healthy;
    }

    /**
     * How this severity renders — the decision this enum's docblock deferred to
     * slice G.
     *
     * TWO VOCABULARIES, ON PURPOSE, AND THE TRANSLATION LIVES HERE. This enum
     * says what a finding costs the business; SignalState says what a person is
     * being asked to do about it. They are not the same question, which is why
     * `Critical` maps to `Alert` rather than sharing a name — one is a property
     * of the finding, the other is an instruction to the reader.
     *
     * Kept on the enum rather than in the Blade partial for decision 234's
     * reason: a template that picked its own class could drift from the scoring
     * model without being visibly wrong anywhere a reviewer would look, and
     * SignalState is what guarantees a colour never appears without its icon and
     * label.
     *
     * There is no `Unknown` here and there cannot be. A finding exists because a
     * check ran and produced it; "we could not look" is carried by CheckResult
     * on the `checks` column, never by a finding (decision 229).
     */
    public function signalState(): SignalState
    {
        return match ($this) {
            self::Critical => SignalState::Alert,
            self::Attention => SignalState::Attention,
            self::Healthy => SignalState::Ok,
        };
    }
}
