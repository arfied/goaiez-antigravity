<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Database\Factories\PixelKeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The public key a pixel presents, and the tenant it stands for.
 *
 * `GOAIEZ_PIXEL_MASTER_BUILD` §11 row 1. The creating migration carries the
 * argument for why this is a table rather than a column on `businesses`; the
 * short version is that a `public_read` policy filters rows and not columns, so
 * putting it on the tenant root would make every business's name, EIN, address
 * and `data_classification` fetchable by an unauthenticated connection.
 *
 * ---------------------------------------------------------------------------
 * IT KEEPS ITS GLOBAL SCOPE, LIKE `Plugin` AND `ShortLink`, NOT LIKE `FeedbackPage`
 * ---------------------------------------------------------------------------
 * Decision 401's answer, taken for 401's reason. All four resolve a tenant from a
 * public opaque key and all four meet decision 318's circularity — `TenantScope`
 * calls `Tenancy::idOrFail()`, which cannot run on the query whose answer
 * *establishes* the tenant. `FeedbackPage` answers it by carrying no scope at all
 * and joining the allowlist; this one keeps the scope and
 * `PixelKeys::resolve()` is the single audited opt-out.
 *
 * The asymmetry is deliberate: `idOrFail()` **throws** rather than filtering to
 * nothing, so a scoped model fails loudly on any path that forgot a tenant, while
 * an unscoped one quietly returns whatever `public_read` permits — which is every
 * row on the table. One audited hole is a smaller surface than another allowlist
 * entry, and `PixelKeys` is the only class permitted to touch this model at all.
 *
 * @property-read int $id
 * @property int $business_id
 * @property string $key
 * @property ?Carbon $created_at
 */
final class PixelKey extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<PixelKeyFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * ⚠️ **`key` IS GUARDED AND MUST STAY GUARDED.** It is a permanent public
     * identifier for one tenant's archive; a `fill()` from a request body must
     * never be able to reach it, and `PixelKeys` is the "else" that `forceFill()`s
     * it. `Plugin::$guarded` records the same division for `embed_key`.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id', 'key'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
