<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\ShortLinkPurpose;
use App\Services\ShortLinks\ShortLinks;
use Database\Factories\ShortLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One per-send redirect on the short domain — T137 `SL-5`.
 *
 * ⚠️ **KEEPS ITS GLOBAL SCOPE, AND THE ONE PLACE THAT DROPS IT IS
 * {@see ShortLinks::resolve()}.** Decision 401's asymmetry, for its stated
 * reason: `Tenancy::idOrFail()` throws rather than filtering to nothing, so a
 * scoped model fails loudly wherever a tenant was forgotten, while an unscoped
 * one quietly returns whatever `public_read` permits — every row of every
 * tenant. The table carries `public_read` + `tenant_write` (318/400) because the
 * redirect arrives with no tenant and this row is what establishes one.
 *
 * Read and written only through {@see ShortLinks}; a chokepoint lint holds that.
 *
 * @property-read int $id
 * @property int $business_id
 * @property string $token
 * @property string $target_url
 * @property ?int $customer_id
 * @property ShortLinkPurpose $purpose
 * @property ?Carbon $expires_at
 * @property ?Carbon $revoked_at
 */
final class ShortLink extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<ShortLinkFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'token',
        'target_url',
        'customer_id',
        'purpose',
        'expires_at',
        'revoked_at',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Whether this link still redirects.
     *
     * ⚠️ **A REVOKED OR EXPIRED LINK IS NOT A MISSING ONE**, and the redirector
     * tells them apart: a token that never existed is somebody guessing, and a
     * token that has expired is a real message that got old. They produce the
     * same status to the visitor and different rows in the log, which is what
     * makes the guessing visible.
     */
    public function isLive(Carbon $now): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->greaterThan($now);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => ShortLinkPurpose::class,
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
