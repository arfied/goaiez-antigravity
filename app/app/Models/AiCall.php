<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\AiModel;
use App\Enums\AiProvider;
use App\Enums\AiTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One model call, what it cost us, and what it charged the tenant (row 3 slice
 * A0; the charge is decision 3358).
 *
 * ⚠️ **TWO CURRENCIES ON ONE ROW.** `cost_hundredths_cents` is the provider's
 * bill to us; `retail_hundredths_cents` is the tenant's charge, eight times it by
 * decision 3304. Neither is derivable from the other after the multiple moves,
 * which is why both are stored — the same argument the cost column already makes
 * against pricing at read time. `App\Services\Ai\AiCredits` owns the multiple.
 *
 * TENANT-OWNED, WHICH IS THE OPPOSITE OF PlacesApiCall AND DELIBERATELY SO. That
 * ledger meters the free public audit, which runs before any tenant exists, so
 * its tenant key is nullable and it sits on the TenancyTest allowlist. Every
 * row here belongs to a business by construction — the migration makes the column
 * `NOT NULL` — because AI spend is charged per tenant, and spend that cannot be
 * attributed cannot be charged to anybody.
 *
 * ⛔ **THE CEILING SUMMED FROM THIS TABLE IS NOT RULE 43's CAP, AND THAT SENTENCE
 * WAS WRONG IN TWO DIFFERENT WAYS** (decisions 3107, 3608, 3820). It first read
 * *"rule 43's per-tenant cost cap is enforced by summing this table"*: rule 43's
 * cap is on a tenant's *total* service cost, and messaging, Places and everything
 * else sit outside this table entirely — 3107 narrowed it to
 * `ai.monthly_cap_per_tenant`, an AI-only containment. ⛔ **Then the owner deleted
 * the dollar cap outright (3293) and the credit balance became the ceiling**, so
 * at 3608 that key was removed and the brake moved off this table and onto
 * `credit_ledger`. ⚠️ **3820 moved half of it back**: `cost_hundredths_cents`
 * summed over the month is again compared against `ai.monthly_cap_per_tenant`, as
 * **outer containment behind** the balance gate, because the balance bounds only a
 * tenant who has one and today every account is unfunded until it verifies. So
 * **two ceilings read two different columns of this table**: the platform cap sums
 * `cost_hundredths_cents` (our cost) and the tenant's balance is debited from
 * `retail_hundredths_cents` (their charge, eight times it). The conflated wording
 * came from decision 281 and appears again in 583 and in `DefaultsManifest`, so
 * **three artefacts independently described the AI ceiling as rule 43's cap** —
 * which is why a missing mandatory safeguard read as a present one for months. 281
 * and 583 are append-only history and stand, with 3107, 3608 and 3820 as the
 * corrections beside them.
 *
 * ✅ **WHAT THIS TABLE IS NOW IS THE RECORD OF SPEND, WHICH IS NOT THE SAME AS THE
 * BALANCE** (3608). `retail_hundredths_cents` is what the tenant was charged and
 * it is written whether or not the ledger could cover it; the balance is what
 * remains of the grant, floored at zero. **The two stop agreeing the moment a
 * tenant runs out, and that is why both exist** — `App\Services\Ai\AiCredits` says
 * which question each answers.
 *
 * APPEND-ONLY IN PRACTICE, like the Places ledger. Nothing updates a recorded
 * call; a ledger that can be edited is not evidence of anything.
 *
 * @property-read int $id
 * @property int $business_id
 * @property AiTask $task
 * @property AiProvider $provider
 * @property AiModel $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cost_hundredths_cents
 * @property int $retail_hundredths_cents
 * @property bool $refused
 * @property ?string $failure_reason
 * @property Carbon $created_at
 */
final class AiCall extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * Calls inside the calendar month containing $moment.
     *
     * A calendar month rather than a rolling 30 days because that is the unit
     * the surviving cap is stated in — `DefaultsManifest`'s
     * `ai.monthly_cap_per_tenant`, *"Per-tenant AI spend ceiling for a calendar
     * month"* — and because a rolling window means a tenant can be over cap on
     * Tuesday and under it on Wednesday having spent nothing, which is impossible
     * to explain to anyone.
     *
     * @param  Builder<AiCall>  $query
     */
    public function scopeInMonthOf(Builder $query, Carbon $moment): void
    {
        $query->whereBetween('created_at', [
            $moment->copy()->startOfMonth(),
            $moment->copy()->endOfMonth(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'task' => AiTask::class,
            'provider' => AiProvider::class,
            'model' => AiModel::class,
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost_hundredths_cents' => 'integer',
            'retail_hundredths_cents' => 'integer',
            'refused' => 'boolean',
        ];
    }
}
