<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingTerm;
use App\Support\Money;
use Database\Factories\PlanOfferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One offer's price for one billing term (T176 P1, decisions 1152, 2090, 2754).
 *
 * Ours, not a tenant's — these are the terms a business may be *offered*, not
 * data it owns — so the model sits on the scope allowlist in
 * `tests/Feature/Architecture/TenancyTest.php` with its argument written there,
 * alongside `PlanEntitlement` and `LegalDocument` for the same reason.
 *
 * ⚠️ **A ROW IS NEVER DELETED.** An offer that has ended is closed, not removed:
 * `closes_at` is what stops it being quoted, and the row afterwards is the only
 * record of what was on the table while somebody was buying. Deleting one would
 * leave every subscription sold under it looking like a retail subscription
 * somebody had mispriced by hand. Updates *are* allowed, because closing a window
 * is an update — `PlanOffers` is the only caller either way.
 *
 * @property int $id
 * @property string $key
 * @property BillingTerm $term
 * @property int $price_cents
 * @property int $additional_location_cents
 * @property string $price_currency
 * @property ?int $instalment_payments
 * @property Carbon $opens_at
 * @property ?Carbon $closes_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class PlanOffer extends Model
{
    /** @use HasFactory<PlanOfferFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * What one location costs on this offer's term.
     *
     * ⚠️ **A `Money` RATHER THAN THE BARE COLUMN, AT THE ONE PLACE THE TWO HALVES
     * OF A PRICE SIT TOGETHER.** `price_cents` and `price_currency` are the pair
     * `Money`'s own docblock calls "the shape that lets $50 of one currency be
     * added to $50 of another"; a caller reading the integer column has to
     * remember the code, and `PlanCharges` multiplies and adds these.
     */
    public function price(): Money
    {
        return Money::of($this->price_cents, $this->price_currency);
    }

    /**
     * Each location beyond the first, on this offer's term.
     */
    public function additionalLocationPrice(): Money
    {
        return Money::of($this->additional_location_cents, $this->price_currency);
    }

    /*
     * ⛔ THERE IS NO `isLiveAt()` HERE, AND ONE WAS WRITTEN AND DELETED.
     *
     * The window is filtered in SQL by `PlanOffers::liveAt()` — the index is on
     * those two columns and the marketing home asks on the page row 1's LCP gate
     * is measured against. A model method beside it would have had **no caller**
     * (CLAUDE.md's 272 shape, written into a slice whose own tests would never
     * have noticed), and worse: two spellings of the half-open boundary, one of
     * which nothing exercises. The boundary is stated once, where it runs.
     */

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'term' => BillingTerm::class,
            'price_cents' => 'integer',
            'additional_location_cents' => 'integer',
            'instalment_payments' => 'integer',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    /**
     * Never deleted, on `PlanEntitlement`'s reasoning one table over.
     *
     * Known gap, the same one `PlanEntitlement` and `AuditLogEntry` document: a
     * Query Builder mass delete bypasses model events. The chokepoint lint that
     * confines this model to `PlanOffers` is what narrows who could write one.
     */
    protected static function booted(): void
    {
        self::deleting(function (): never {
            throw new LogicException(
                'An offer is closed, not deleted. Set closes_at — the row is the only '
                .'record of what was being offered while somebody was buying it.'
            );
        });
    }
}
