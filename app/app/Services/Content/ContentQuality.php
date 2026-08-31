<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\ContentQualityFailure;
use App\Enums\ModerationFlag;
use App\Models\ContentQualityCheck;
use App\Services\Config\DefaultsRegistry;
use App\Support\Readability;
use App\Support\Shingles;

/**
 * The publish gate — doc `16` §15.3, and `29` §2 rule 29: *"≥60% unique versus
 * every other page, ≥2 genuine first-party data points, demonstrated demand, no
 * fabrication, moderation passed. **Fail → hold.**"*
 *
 * ⛔ **THE FIRST AND ONLY WRITER OF `content_quality_checks`**, held by a
 * chokepoint lint in `tests/Feature/Architecture/ContentTest.php`. The table has
 * existed since Stage 0 with **zero writers** (`BUILD-PLAN` §2.11.1) — 272's
 * shape, a green isolation suite over a table nothing filled in — and this slice
 * is what closes it.
 *
 * ## ⛔ THREE VERDICTS, AND THE THIRD IS DECISION 347
 *
 * `passed` is nullable and always was; nothing had given the null a meaning:
 *
 *   `true`   every check that could run passed
 *   `false`  something refused it — `failure_reasons` names what
 *   `null`   no verdict exists — `unavailable_reason` says why nobody could say
 *
 * A classifier declining to read the copy lands in the third, never the second.
 * 347: *"a refusal withholds for a human and is not recorded as a failure …
 * filing it as an outage would send somebody to check a vendor that is fine."*
 * Filing it as a **content** failure would be worse — it tells a tenant their
 * writing broke a rule it did not break. A CHECK constraint keeps the three
 * mutually exclusive at the database.
 *
 * ## ⛔ THE MODEL IS ASKED LAST, AND ONLY ABOUT COPY THAT WOULD OTHERWISE SHIP
 *
 * Every deterministic check runs first. If any refuses, no moderation call is
 * made at all. Two reasons, and the second is the one that matters:
 *
 *   1. It is the only line of this gate that spends money (3297), and paying to
 *      classify a page that has already failed is spending a tenant's AI credit
 *      on an answer nobody will read.
 *   2. **It keeps a refusal unambiguous.** If moderation ran alongside the
 *      others, a page could be both content-failed and moderation-undetermined,
 *      and one of the two states would have to win — which is exactly the
 *      collapse 347 exists to prevent.
 *
 * ## ⚠️ WHAT IS NOT CHECKED HERE, SAID OUT LOUD
 *
 * `16` §15.3 also asks for *"no fabricated claims, statistics, credentials, or
 * testimonials"* and `BUILD-PLAN` §2.11.3's slice C row does not list it. **It is
 * not built** (5573), because a check for fabrication needs a ground truth to
 * compare against and this slice has none: asking a model *"is any of this made
 * up?"* about text it cannot verify produces a confident answer with nothing
 * behind it, which is 256's vacuous pass wearing a threshold. The honest place
 * for it is the generator that knows which facts it was given — Stage 5.
 */
final class ContentQuality
{
    /**
     * `16` §15.3's *"≥60% content unique"*. A registry seed rather than a
     * constant, on `reviews.default_invite_threshold`'s pattern: it is platform
     * policy, Ops may move it downward, and **it is not a tenant setting** —
     * `CLAUDE.md`'s no-toggle rule, whose single overrule (1143) was explicitly
     * not to be read as the rule.
     */
    public const string MIN_UNIQUENESS_KEY = 'content.quality.min_uniqueness_pct';

    /** `16` §15.3's *"≥2 pieces of genuine first-party data"*. */
    public const string MIN_FIRST_PARTY_KEY = 'content.quality.min_first_party_data_points';

    /** `29` §9.1's *"target reading level 8"*; doc `33`'s *"readability grade ≤8"*. */
    public const string MAX_READING_GRADE_KEY = 'content.quality.max_reading_grade';

    public function __construct(
        private readonly ContentModerator $moderator,
        private readonly DefaultsRegistry $registry,
    ) {}

    /**
     * Judge one candidate and write the row that explains the judgement.
     *
     * @param  iterable<int, string>  $corpus  Every other page on this tenant's
     *                                         site, as text. Built by
     *                                         {@see GrowthPages::gate()}, which
     *                                         is the only caller — this class
     *                                         deliberately never touches
     *                                         `growth_pages`, so that the two
     *                                         chokepoints stay one file each.
     *
     * ⚠️ **`$pageId` IS TRUSTED AND THE TRUST IS THE CHOKEPOINT'S RATHER THAN A
     * CONSTRAINT'S.** `content_quality_checks.business_id` is filled from the
     * tenant in context and constrained by row-level security, but a foreign key
     * is checked as the table owner — so a caller that passed **another
     * tenant's** page id would write a verdict about a page it cannot read. What
     * prevents it is that the only caller resolves the page under the tenant
     * scope, and the chokepoint lint is what keeps "the only caller" true. ⛔ A
     * composite `(business_id, page_id)` key would hold it structurally and was
     * deliberately not added: no other foreign key in this schema is composite,
     * and a lone pattern here would read as a rule the rest of the schema
     * breaks. **Raised rather than assumed away** — if a second caller is ever
     * permitted, that key is the thing to add with it.
     */
    public function assess(int $pageId, PageCopy $copy, iterable $corpus, ContentEvidence $evidence): QualityOutcome
    {
        $text = $copy->fullText();

        /** @var list<ContentQualityFailure> $failures */
        $failures = [];
        $ran = 0;
        $passed = 0;

        $uniqueness = Shingles::uniquenessPercent($text, $corpus);
        $ran++;

        if ($uniqueness >= $this->registry->int(self::MIN_UNIQUENESS_KEY)) {
            $passed++;
        } else {
            $failures[] = ContentQualityFailure::UniquenessBelowThreshold;
        }

        $firstPartyCount = count($evidence->cited($copy));
        $ran++;

        if ($firstPartyCount >= $this->registry->int(self::MIN_FIRST_PARTY_KEY)) {
            $passed++;
        } else {
            $failures[] = ContentQualityFailure::InsufficientFirstPartyData;
        }

        $ran++;

        if ($evidence->realDemand() !== []) {
            $passed++;
        } else {
            $failures[] = ContentQualityFailure::NoDemandEvidence;
        }

        $grade = Readability::gradeLevel($text);

        // ⚠️ **A GRADE THAT COULD NOT BE MEASURED SCORES NEITHER WAY**
        // (`AuditScore`'s rule). A page with no words at all has no reading
        // level, and counting that as a failure would give a thin page two
        // reasons for one problem while counting it as a pass would reward it.
        // The uniqueness line is what actually refuses an empty page.
        if ($grade !== null) {
            $ran++;

            if ($grade <= $this->registry->int(self::MAX_READING_GRADE_KEY)) {
                $passed++;
            } else {
                $failures[] = ContentQualityFailure::ReadingGradeTooHigh;
            }
        }

        if ($failures !== []) {
            return $this->record($pageId, $evidence, QualityOutcome::decided(
                false,
                $uniqueness,
                $firstPartyCount,
                $grade,
                self::share($passed, $ran),
                $failures,
            ));
        }

        $verdict = $this->moderator->moderate($copy);
        $ran++;

        if (! $verdict->wasModerated()) {
            return $this->record($pageId, $evidence, QualityOutcome::undetermined(
                self::unavailableReason($verdict->reason ?? 'unknown'),
                $uniqueness,
                $firstPartyCount,
                $grade,
                self::share($passed, $ran),
            ));
        }

        // ⛔ **A REFUSAL IS NOT A FLAG ON THE CONTENT** (347). It is the one
        // `flagged` shape that means the model read nothing rather than found
        // something, and `ModerationFlag::Refused` is platform-written precisely
        // so the two can never arrive in the same list.
        if (in_array(ModerationFlag::Refused, $verdict->flags ?? [], true)) {
            return $this->record($pageId, $evidence, QualityOutcome::undetermined(
                self::unavailableReason('refused'),
                $uniqueness,
                $firstPartyCount,
                $grade,
                self::share($passed, $ran),
            ));
        }

        if ($verdict->isFlagged()) {
            // ⚠️ **`Unrecognised` LANDS HERE AND NOT ABOVE.** The model named a
            // reason this vocabulary has no word for, which is an objection we
            // cannot read — not an absence of one. Reading it as "no verdict"
            // would let slice D's hold lapse on a page a classifier asked to
            // stop.
            $failures[] = ContentQualityFailure::ModerationFlagged;

            return $this->record($pageId, $evidence, QualityOutcome::decided(
                false,
                $uniqueness,
                $firstPartyCount,
                $grade,
                self::share($passed, $ran),
                $failures,
            ));
        }

        $passed++;

        return $this->record($pageId, $evidence, QualityOutcome::decided(
            true,
            $uniqueness,
            $firstPartyCount,
            $grade,
            self::share($passed, $ran),
        ));
    }

    /**
     * Whether the last thing this gate said about a page was yes.
     *
     * ⚠️ **THE VERDICT LIVES HERE AND IS NEVER COPIED ONTO `growth_pages`.** Two
     * homes for one fact is `reviews.status` versus `flagged_at` (345), where a
     * job ordering decided whether abuse was published.
     *
     * ⚠️ **NO ROW MEANS NO, AND THAT IS FAIL-CLOSED RATHER THAN PESSIMISTIC.** A
     * page nobody has gated has not cleared the gate.
     */
    public function clearedTheGate(int $pageId): bool
    {
        // ⚠️ **BY `id`, NOT BY `checked_at`, AND `ConventionsTest` IS WHY.**
        // Postgres puts NULLs *first* in a DESC sort, and `checked_at` is
        // nullable — so a row that somehow arrived without one would sort to the
        // top and be read as the newest verdict. The sequence is monotonic per
        // insert and cannot be null, which makes it the honest answer to "what
        // did this gate last say".
        return ContentQualityCheck::query()
            ->where('page_id', $pageId)
            ->orderByDesc('id')
            ->value('passed') === true;
    }

    /**
     * ⚠️ **THE EVIDENCE IS STORED AS IT WAS OFFERED, INCLUDING THE PARTS THAT
     * COUNTED FOR NOTHING.** A row showing an empty demand list and a row
     * showing three signals that all reported zero are two different
     * conversations with a tenant, and only the second is a generator bug.
     */
    private function record(int $pageId, ContentEvidence $evidence, QualityOutcome $outcome): QualityOutcome
    {
        ContentQualityCheck::query()->create([
            'page_id' => $pageId,
            'uniqueness_score' => $outcome->uniquenessScore,
            'first_party_data_count' => $outcome->firstPartyDataCount,
            'demand_evidence' => array_map(
                static fn (DemandSignal $signal): array => $signal->toArray(),
                $evidence->demand,
            ),
            'readability_score' => $outcome->readingGrade,
            'passed' => $outcome->passed,
            'failure_reasons' => $outcome->failures === [] ? null : $outcome->failureValues(),
            'unavailable_reason' => $outcome->unavailableReason,
            'checked_at' => now(),
        ]);

        return $outcome;
    }

    /**
     * The share of the checks that ran that this page passed.
     *
     * ⛔ **A SUMMARY FOR A PERSON, NEVER A THRESHOLD** (5568). Nothing compares
     * it against anything: the gate requires every check to pass, so a page
     * scoring 80 is refused exactly as firmly as one scoring 20. A weighted
     * composite would have meant inventing weights, which is a product judgement
     * nobody has made — and the moment a number like this acquires a comparison
     * it becomes the real gate, quietly.
     */
    private static function share(int $passed, int $ran): int
    {
        return $ran === 0 ? 0 : (int) round(100 * $passed / $ran);
    }

    /**
     * ⚠️ **PREFIXED AND TRUNCATED.** Prefixed because `no_json` on its own says
     * nothing about which of this application's model calls produced it;
     * truncated because the column is 64 characters and a vendor reason string
     * is not ours to bound.
     */
    private static function unavailableReason(string $reason): string
    {
        return mb_substr('moderation:'.$reason, 0, 64);
    }
}
