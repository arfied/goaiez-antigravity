<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\GscPermissionLevel;
use Database\Factories\GscSitePropertyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which Search Console site property one location is.
 *
 * The binding decision 1083 requires: a `siteUrl` is opaque and one Google
 * account routinely holds many, so without a tenant-owned row nothing can tell
 * whose property a string refers to. Decision 531's finding, with the store
 * shipping alongside the client instead of being deferred.
 *
 * ⚠️ **`App\Services\Visibility\SearchConsoleProperties` is the only thing in
 * `app/` allowed to read or write this**, held there by a lint in
 * `tests/Feature/Architecture/VisibilityTest.php`. The rule earns its place: a
 * second writer is how a property arrives from a request parameter rather than
 * from a list the tenant's own Google account was shown, and that is the exact
 * shape of the hazard the table exists to close.
 */
final class GscSiteProperty extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<GscSitePropertyFactory> */
    use HasFactory;

    /**
     * `business_id` stays guarded: it is the tenant key, and mass-assigning it
     * from request input is how a row crosses the boundary.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permission_level' => GscPermissionLevel::class,
            'chosen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
