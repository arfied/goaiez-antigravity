<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Gbp\ZernioSpend;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One connected Zernio account, on one day — the unit Zernio actually bills.
 *
 * NOT tenant-owned, and on `TenancyTest`'s named-exception list beside
 * `places_api_calls` and `gbp_account_bindings`, for the reasons in the creating
 * migration. The short version is that a tenant scope here would hide from the
 * platform's own budget query the rows that cost the platform money, and that
 * the row most worth keeping is the one whose business has been erased.
 *
 * APPEND-ONLY IN PRACTICE, like `places_api_calls`: nothing updates a metered
 * day. A ledger that can be edited is not evidence of anything.
 *
 * @property-read int $id
 * @property string $account_ref
 * @property Carbon $on_day
 * @property ?int $business_id
 * @property Carbon $recorded_at
 *
 * @see ZernioSpend for the graduated price ladder these rows are run through.
 */
final class ZernioAccountDay extends Model
{
    /**
     * Written once, never touched again — there is no updated_at to maintain.
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * Account-days inside one calendar month.
     *
     * ⚠️ **The month boundary is the billing boundary, not a rolling window.**
     * Zernio's graduated ladder is applied to a monthly total — *"at the end of
     * the month we add up every account-day, divide by 30, and run the result
     * through the rate ladder"* — so a trailing-30-days sum would produce a
     * number that never appears on any invoice.
     *
     * @param  Builder<ZernioAccountDay>  $query
     */
    public function scopeInMonth(Builder $query, Carbon $month): void
    {
        $query->whereBetween('on_day', [
            $month->copy()->startOfMonth()->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'on_day' => 'date',
            'business_id' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }
}
