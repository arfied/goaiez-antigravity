<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\TenantLinkKind;
use App\Services\Links\TenantLink;
use App\Services\Links\TenantLinks;
use Database\Factories\TenantLinkRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One stored link a business has given its assistant — T176 P6.
 *
 * ⚠️ **`TenantLinkRecord` RATHER THAN `TenantLink`, AND THE SUFFIX IS LOAD-BEARING
 * RATHER THAN A NAMING PREFERENCE.** {@see TenantLink} is the day-0 DTO the
 * registry hands out, and it is the one every other lane builds against. Two
 * classes called `TenantLink` in two namespaces cannot both be imported into the
 * file that maps one to the other — {@see TenantLinks} needs both on its first
 * line — so one of them would be aliased, and an aliased import is exactly how a
 * later edit reaches for the wrong one. The suffix says which is the stored row.
 *
 * ⛔ **READ AND WRITTEN ONLY THROUGH {@see TenantLinks}; A CHOKEPOINT LINT HOLDS
 * THAT** (`tests/Feature/Architecture/LinksTest.php`). The reason is R14 rather
 * than tidiness: this model carries `destination` as a plain attribute, so any
 * second reader can put a raw tenant URL into a message — untracked,
 * un-tokenised, absent from the CRM timeline, and usually over the composer's
 * ≤159-character budget. The DTO the registry returns keeps that value behind a
 * method precisely so a call site cannot reach it by accident; a second reader of
 * the model walks around the DTO entirely.
 *
 * @property int $id
 * @property int $business_id
 * @property TenantLinkKind $kind
 * @property string $label
 * @property string $destination
 * @property ?string $slug
 * @property ?int $fee_cents
 * @property ?string $fee_currency
 * @property ?string $fee_covers
 */
final class TenantLinkRecord extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<TenantLinkRecordFactory> */
    use HasFactory;

    /**
     * ⚠️ **NAMED, BECAUSE ELOQUENT WOULD GUESS `tenant_link_records`.** The table
     * is `tenant_links` — the vocabulary T176 and the day-0 contract both use —
     * and only the class carries the disambiguating suffix.
     */
    protected $table = 'tenant_links';

    /**
     * ⛔ **EVERYTHING IS GUARDED, INCLUDING `destination`.** A mass-assignable
     * destination is a tenant URL settable from an array, on the one table whose
     * whole content is *where we send your customers*. {@see TenantLinks} assigns
     * every column one at a time, after normalising the URL and refusing our own
     * short-link domain; `forceFill()` stays available to a test building a state
     * on purpose.
     *
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'business_id',
        'kind',
        'label',
        'destination',
        'slug',
        'fee_cents',
        'fee_currency',
        'fee_covers',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => TenantLinkKind::class,
            'fee_cents' => 'integer',
        ];
    }
}
