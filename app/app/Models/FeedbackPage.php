<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FeedbackPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The public address of one location's feedback page (row 3 slice C).
 *
 * DELIBERATELY NOT TENANT-SCOPED — one of eight models on the
 * TenancyTest allowlist, and the only one there because it *resolves*
 * the tenant rather than because it predates one (decision 335, correcting
 * 318's "second model" count, which was never true and does not matter: the
 * interesting claim was always the reason, not the position in a list that
 * grows). `PublicAudit` and `MagicLinkToken` are outside the boundary because
 * they are written before any tenant exists; `PlatformSetting`, `PlacesApiCall`,
 * `FetchSource` and `FetchAttempt` carry their own separate arguments. This one
 * is outside it because a global scope calling Tenancy::idOrFail() would make
 * resolving the tenant circular in exactly the way ResolveTenant already
 * documents for `businesses`.
 *
 * The boundary is not dropped, only moved down a layer: the `tenant_write`
 * policy in the creating migration means no tenant can create or edit another
 * tenant's page, and `public_read` grants only the mapping.
 *
 * NOTHING ON THIS ROW IS DISPLAY DATA. The page reads the business and location
 * names from their own tables, after ResolveFeedbackPage has set the tenant.
 * Adding a name column here would put tenant data in the one place a stranger
 * can read without being anybody.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $location_id
 * @property string $slug
 * @property bool $is_published
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class FeedbackPage extends Model
{
    /** @use HasFactory<FeedbackPageFactory> */
    use HasFactory;

    /**
     * `$guarded = ['id']` is correct here, and it is not the same protection
     * PublicAudit has on its token: PublicAudit guards `token` because
     * PublicAuditController mass-assigns from a request body. Nothing on this
     * model's path does that — there is no controller, no form request, and no
     * `fill()` from a payload anywhere that reaches FeedbackPage. The slug is
     * minted server-side from the business name plus entropy.
     *
     * What actually makes `slug` single-writer is that FeedbackPages will be
     * the only class permitted to touch FeedbackPage::query(), enforced by an
     * ArchitectureTest lint verified by deliberate violation — not this
     * property. Widening the guard to include `slug` would buy no security
     * against a request path that does not exist and would only force
     * provisionFor() into a two-step write.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * Bound on the slug, so a URL never carries the sequential id.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }
}
