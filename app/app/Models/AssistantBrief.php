<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Policies\AssistantBriefPolicy;
use App\Services\Assistant\PriceBook;
use App\Services\Assistant\UrgentTerms;
use Illuminate\Database\Eloquent\Model;

/**
 * The two single lines a business gave its assistant — T176 P5, §2.4.
 *
 * The quote disclaimer skill 4 states with every price, and the emergency line
 * skill 9 gives out when it escalates. The lists themselves are
 * {@see PriceListItem} and {@see UrgentTerm}; this row is the pair of answers
 * that are one value each.
 *
 * ⛔ **READ AND WRITTEN ONLY THROUGH {@see PriceBook}, {@see UrgentTerms} AND
 * {@see AssistantToggles}, AND A CHOKEPOINT LINT HOLDS THAT**
 * (`tests/Feature/Architecture/PricesTest.php`), on 624/1223's reasoning. The
 * disclaimer in particular has to be answerable with its registry fallback
 * applied — a second reader taking `$brief->quote_disclaimer` gets `null` for the
 * majority of businesses and would render *"we say nothing with your prices"*
 * about a platform default that is very much said.
 *
 * ⚠️ **THE RULE IS ONE OWNER PER COLUMN GROUP, NOT A FIXED COUNT OF FILES**
 * (4137). P4 added the three §2.4 switches to this row and a third owner with
 * them; every column here still has exactly one reader, and each of the three
 * nullable groups needs its own because `null` means something different in each
 * — the platform's disclaimer, no emergency line at all, and a switch nobody has
 * touched. **A fourth service reading a column one of these three already owns is
 * what the lint refuses**, and that has not changed.
 *
 * ⚠️ **IT IS ALSO THE POLICY SUBJECT FOR THE WHOLE OF §2.4's SECOND HALF** —
 * see {@see AssistantBriefPolicy} for why one policy governs three
 * tables here.
 *
 * @property int $id
 * @property int $business_id
 * @property ?string $quote_disclaimer
 * @property ?string $emergency_line
 * @property ?bool $quotes_enabled
 * @property ?bool $review_ask_enabled
 * @property ?bool $nudge_enabled
 */
final class AssistantBrief extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * ⛔ **EVERYTHING IS GUARDED.** The two text columns are said to members of
     * the public in the business's name, and the three switches decide whether a
     * skill speaks to one at all; none has any business being settable from an
     * array. The three services assign them one at a time, after normalising;
     * `forceFill()` stays available to a test building a state on purpose.
     *
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'business_id',
        'quote_disclaimer',
        'emergency_line',
        'quotes_enabled',
        'review_ask_enabled',
        'nudge_enabled',
    ];

    /**
     * ⚠️ **CAST SO THAT `null` SURVIVES THE ROUND TRIP.** Postgres hands a
     * `boolean` back as `true`/`false` and Laravel would otherwise leave a stored
     * `false` indistinguishable from an untouched `null` to a strict `is_bool()`
     * check — which is the whole of how `AssistantToggles` tells *"turned off"*
     * from *"never chosen"*.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quotes_enabled' => 'boolean',
            'review_ask_enabled' => 'boolean',
            'nudge_enabled' => 'boolean',
        ];
    }
}
