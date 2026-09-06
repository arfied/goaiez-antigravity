<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\PriceListItemSource;
use App\Services\Assistant\PriceBook;
use App\Services\Assistant\PriceList;
use App\Services\Assistant\PriceListEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One priced line on a business's list — T176 P5, R13's skill 4.
 *
 * ⛔ **NO LONGER THE PRICEBOOK OF RECORD**. `PriceBook` now reads and writes
 * `price_book_items` instead of this table.
 * The review-before-live gate rather than tidiness: `confirmed_at` is a nullable
 * column, so **any second reader is one forgotten `where` away from quoting a
 * figure nobody has reviewed** — and the failure is silent, because an
 * unconfirmed row looks exactly like a confirmed one on the way out of the
 * database. `PriceBook` filters, {@see PriceList} refuses to be built from an
 * unconfirmed entry, and neither of those helps a caller who went round both.
 *
 * ⚠️ **`PriceListItem` IS THE ROW AND {@see PriceListEntry}
 * IS WHAT LEAVES THE BOUNDARY**, which is P6's `TenantLinkRecord`/`TenantLink`
 * split with the names the other way round — here the store had the better claim
 * to the plain name, because there is no committed day-0 contract holding it.
 *
 * @property int $id
 * @property int $business_id
 * @property string $label
 * @property string $slug
 * @property PriceListItemSource $source
 * @property int $amount_cents
 * @property ?int $amount_max_cents
 * @property string $currency
 * @property ?CarbonImmutable $confirmed_at
 */
final class PriceListItem extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * ⛔ **EVERYTHING IS GUARDED, AND `confirmed_at` MOST OF ALL.** A
     * mass-assignable confirmation is the review gate settable from a request
     * array, on the one table whose whole content is *what we will quote a
     * member of the public in your name*.
     *
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'business_id',
        'label',
        'slug',
        'source',
        'amount_cents',
        'amount_max_cents',
        'currency',
        'confirmed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => PriceListItemSource::class,
            'amount_cents' => 'integer',
            'amount_max_cents' => 'integer',
            'confirmed_at' => 'immutable_datetime',
        ];
    }
}
