<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\CreditProduct;
use App\Enums\CreditTopUpTier;
use App\Services\Billing\AutoTopUps;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A tenant's standing agreement to be charged automatically when a balance runs
 * low — decision 3306, and the owner's "disabled by default" of 2026-08-14.
 *
 * ⛔ **THE ROW'S EXISTENCE IS THE ON STATE.** There is no `enabled` column, and
 * that is the design rather than an omission: a boolean would have a default, and
 * a default on this table is a recurring charge nobody agreed to, one migration
 * away. An account that has never chosen has no row, and nothing in `app/` writes
 * one except {@see AutoTopUps::agree()} — which requires a
 * confirmation it cannot fabricate.
 *
 * ⚠️ **THREE STATES, AND THEY ARE NOT THE SAME.** *Live* is a row with no
 * `cancelled_at` and no `stopped_at`. *Suspended* is the card failing — the
 * agreement stands and resumes when it works again. *Cancelled* is the tenant
 * withdrawing, and the row is kept rather than deleted because "did they ever
 * agree to this" is asked about periods that have already passed.
 *
 * ⛔ **THE SUSPENDED STATE'S COLUMNS ARE `stopped_at` / `stopped_reason` AND THE
 * OBVIOUS NAMES ARE TAKEN — 3473's COLLISION, SECOND INSTANCE.** `TenancyTest`'s
 * *"only the suspension service stops or starts a tenant for cause"* matches the
 * bare `suspended_at` across every file in `app/`, because a second writer of
 * that column is a way to suspend a business, and `businesses`' own RLS policy
 * admits any write by the tenant in context. A billing table reusing the word
 * reddened it. **The column moved rather than the lint** — narrowing a guard on
 * tenant suspension so an arrangement can have a prettier column name is 511's
 * failure with the stakes reversed. The vocabulary a person reads is still
 * "suspended"; only the columns differ.
 *
 * @property int $id
 * @property int $business_id
 * @property CreditProduct $product
 * @property CreditTopUpTier $tier
 * @property int $ceiling_cents
 * @property string $currency
 * @property string $agreed_actor
 * @property int $agreed_amount_cents
 * @property string $agreement_wording
 * @property Carbon $agreed_at
 * @property int $consecutive_failures
 * @property ?Carbon $stopped_at
 * @property ?string $stopped_reason
 * @property ?Carbon $cancelled_at
 */
final class AutoTopUpArrangement extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * ⛔ **THE FAILURES AFTER WHICH IT SUSPENDS ITSELF.**
     *
     * Three, and the figure is operational rather than a pricing ruling — the
     * owner set the ceiling and said nothing about retries. A bank declining the
     * same card three times running is not a transient network problem, and a
     * fourth attempt is how an account gets flagged by the processor's own
     * fraud screening. **Under-retrying costs a tenant a delay; over-retrying
     * costs them their merchant relationship.**
     */
    public const int FAILURES_BEFORE_SUSPENSION = 3;

    /**
     * ⚠️ **NAMED, BECAUSE ELOQUENT WOULD GUESS `auto_top_up_arrangements` AND THE
     * TABLE IS `auto_topup_arrangements`.** The schema spells it the way the
     * registry keys do — `credits.auto_topup.*` — and the foreign key on the
     * charges table follows suit. The convention that the guess is right holds
     * everywhere else in this schema, which is exactly why it went unnoticed here
     * until a test asked the database a question: every method on this model
     * worked, and every query against it named a relation that does not exist.
     */
    protected $table = 'auto_topup_arrangements';

    /**
     * ⚠️ **THE COLUMN DEFAULT IS ZERO AND A MODEL THAT HAS NOT BEEN RE-READ WOULD
     * SAY NULL.** {@see AutoTopUps::agree()} returns the instance it saved, so
     * anything asking a fresh arrangement how many failures it has had would get
     * null from PHP and 0 from the database — the two answers differ only in a
     * language that treats them alike, which is how a count silently starts again.
     *
     * @var array<string, int>
     */
    protected $attributes = ['consecutive_failures' => 0];

    /**
     * ⛔ **THE AGREEMENT COLUMNS ARE GUARDED, ON `CreditPurchase`'s REASONING.** A
     * mass-assignable confirmation is a CONFIRM record a caller can write without
     * anybody having confirmed anything, and a mass-assignable `ceiling_cents` is
     * the tenant's own limit on automatic spending, settable from an array.
     * {@see AutoTopUps::agree()} assigns every one of them explicitly, from a
     * {@see App\Support\Billing\PurchaseConfirmation} it cannot fabricate.
     *
     * ⛔ **AND THE STATE COLUMNS ARE GUARDED TOO, WHICH THEY WERE NOT** (3873).
     * The list protected what a tenant agreed to and left what the arrangement
     * *is* wide open: a mass-assignable `cancelled_at` or `stopped_at` is the
     * on/off switch this table deliberately has no boolean for, settable from an
     * array; `stopped_reason` is the operator's record of why, and
     * {@see AutoTopUps::stoppedCause()} matches on its prefix to decide what a
     * customer is told; and `product` and `tier` choose which SKU the card is
     * charged for. **Every legitimate writer assigns them one at a time** —
     * {@see AutoTopUps::agree()}, `cancel()` and `suspend()` — so nothing in
     * `app/` loses anything, and `forceFill()` is still how a test builds a state
     * on purpose.
     *
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'business_id',
        'product',
        'tier',
        'ceiling_cents',
        'currency',
        'agreed_actor',
        'agreed_amount_cents',
        'agreement_wording',
        'agreed_at',
        'cancelled_at',
        'stopped_at',
        'stopped_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'product' => CreditProduct::class,
            'tier' => CreditTopUpTier::class,
            'ceiling_cents' => 'integer',
            'agreed_amount_cents' => 'integer',
            'consecutive_failures' => 'integer',
            'agreed_at' => 'datetime',
            'stopped_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** The most this arrangement may spend automatically inside one window. */
    public function ceiling(): Money
    {
        return Money::of($this->ceiling_cents, $this->currency);
    }

    /** The per-charge figure the tenant was shown when they agreed. */
    public function agreedAmount(): Money
    {
        return Money::of($this->agreed_amount_cents, $this->currency);
    }

    /**
     * Whether this arrangement may charge at all.
     *
     * ⚠️ **A SEPARATE QUESTION FROM WHETHER IT SHOULD** — the balance, the
     * ceiling and the plan's status are all asked by
     * {@see AutoTopUps}. This one is only about the
     * agreement itself still standing.
     */
    public function isLive(): bool
    {
        return ! $this->cancelled_at instanceof Carbon
            && ! $this->stopped_at instanceof Carbon;
    }
}
