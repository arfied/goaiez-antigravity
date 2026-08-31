<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\ContentQualityFailure;
use App\Services\Content\ContentQuality;
use App\Support\Readability;
use Database\Factories\ContentQualityCheckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A growth page's quality-gate result (`DATA-MODEL.md` §5.11).
 *
 * ⛔ **ONLY `App\Services\Content\ContentQuality` MAY TOUCH THIS MODEL**,
 * enforced by an `Architecture\ContentTest` lint on `SiteChange`'s precedent
 * (5071, 5521). ⚠️ **This table had ZERO writers from Stage 0 until 2026-08-19**
 * — 272's shape exactly, a green isolation suite over a table nothing filled in
 * — and the chokepoint lands with the first writer rather than after the second.
 *
 * ## ⛔ `passed` HAS THREE STATES AND THE THIRD IS DECISION 347
 *
 *   `true`   every check that could run passed
 *   `false`  something refused it — `failure_reasons` names what, in
 *            {@see ContentQualityFailure}'s closed vocabulary
 *   `null`   no verdict exists — `unavailable_reason` says why nobody could say
 *
 * A classifier declining to read the copy, or a provider that could not be
 * reached, lands in the third and never the second: *"a refusal withholds for a
 * human and is not recorded as a failure"*. A CHECK constraint keeps the three
 * mutually exclusive at the database, because which-column-is-populated is
 * exactly the pairing prose cannot hold.
 *
 * ⚠️ **`readability_score` HOLDS A GRADE, SO LOWER IS BETTER.** It is the
 * Flesch–Kincaid grade level from {@see Readability}, and the gate's rule is
 * `≤ 8`. The column name reads as though bigger were better; a reader who
 * assumes that inverts the whole check and every fixture still passes.
 *
 * ⚠️ **THERE IS NO `belongsTo(GrowthPage::class)` HERE**, and its absence is the
 * chokepoint on the *other* table doing its job: a relationship is the same
 * reach wearing an Eloquent accessor. `page_id` is a foreign key at the database
 * from 2026-08-19, which is what the Stage 0 migration promised would *"land
 * with that table"*.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $page_id
 * @property ?int $uniqueness_score
 * @property ?int $first_party_data_count
 * @property ?array<int, array<string, mixed>> $demand_evidence
 * @property ?int $readability_score
 * @property ?bool $passed
 * @property ?list<string> $failure_reasons
 * @property ?string $unavailable_reason
 * @property ?Carbon $checked_at
 */
final class ContentQualityCheck extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<ContentQualityCheckFactory> */
    use HasFactory;

    /**
     * checked_at is the datum; the table has no other timestamps.
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * The reasons this page was refused, as the closed vocabulary rather than as
     * strings.
     *
     * ⚠️ **A STORED VALUE OUTSIDE THE VOCABULARY IS DROPPED RATHER THAN
     * COERCED**, on `ModerationFlag::tryFromModel()`'s rule: a refusal reason
     * nobody can render is a held page nobody can explain, and inventing a
     * category for it would tell a tenant something we do not know.
     *
     * @return list<ContentQualityFailure>
     */
    public function failures(): array
    {
        $reasons = $this->failure_reasons ?? [];

        $failures = [];

        foreach ($reasons as $reason) {
            $failure = ContentQualityFailure::tryFrom($reason);

            if ($failure instanceof ContentQualityFailure) {
                $failures[] = $failure;
            }
        }

        return $failures;
    }

    /**
     * Whether a verdict exists at all. See {@see ContentQuality} for why the
     * null is its own state.
     */
    public function hasVerdict(): bool
    {
        return $this->passed !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uniqueness_score' => 'integer',
            'first_party_data_count' => 'integer',
            'readability_score' => 'integer',
            'demand_evidence' => 'array',
            'passed' => 'boolean',
            'failure_reasons' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}
