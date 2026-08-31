<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One charge made under an arrangement — the link between an automatic decision
 * and the purchase it opened.
 *
 * ⛔ **IT EXISTS TO ANSWER TWO QUESTIONS `credit_purchases` CANNOT.** That table
 * has no column saying which purchases were automatic, and adding one would have
 * meant editing `CreditPurchases::open()` while parallel lanes were inside it.
 * The questions are *how much has this arrangement spent this window* (the
 * ceiling) and *is there already an open charge for this product* (the trigger's
 * idempotency).
 *
 * ⚠️ **`credit_purchase_id` IS UNIQUE AND THAT IS *NOT* THE IDEMPOTENCY** —
 * 314–316's rule, that a protection claimed before it is true is what stops the
 * next reviewer looking. The index refuses two of these rows pointing at one
 * purchase; a second scheduler run would open a *second* purchase with a second
 * id, which the index accepts. {@see App\Services\Billing\AutoTopUps} is what
 * refuses that, by asking whether any charge under the arrangement still points
 * at a purchase in a state that means money is in flight. What the index protects
 * is the ceiling arithmetic below: one purchase counted twice would spend a
 * tenant's agreed monthly limit at twice the rate they agreed to.
 *
 * @property int $id
 * @property int $business_id
 * @property int $auto_topup_arrangement_id
 * @property int $credit_purchase_id
 * @property int $price_cents
 * @property string $currency
 * @property Carbon $created_at
 */
final class AutoTopUpCharge extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * ⚠️ **NAMED, FOR {@see AutoTopUpArrangement}'s REASON**: Eloquent would guess
     * `auto_top_up_charges` and the table is `auto_topup_charges`, spelled the way
     * the `credits.auto_topup.*` registry keys are.
     */
    protected $table = 'auto_topup_charges';

    /** @var list<string> */
    protected $guarded = ['id', 'business_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<AutoTopUpArrangement, $this> */
    public function arrangement(): BelongsTo
    {
        return $this->belongsTo(AutoTopUpArrangement::class, 'auto_topup_arrangement_id');
    }

    /** @return BelongsTo<CreditPurchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(CreditPurchase::class, 'credit_purchase_id');
    }

    public function amount(): Money
    {
        return Money::of($this->price_cents, $this->currency);
    }
}
