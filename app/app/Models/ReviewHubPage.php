<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Database\Factories\ReviewHubPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The hosted review hub, one page per location (DATA-MODEL §5.11).
 *
 * Any public query over its reviews filters status IN (approved, displayed)
 * (§5.14) — and shows real aggregates, never a filtered or 5-star-only one.
 *
 * ⛔ **`App\Services\Reviews\ReviewHubPages` IS THE ONLY CLASS PERMITTED TO
 * TOUCH `ReviewHubPage::query()`**, and a lint in
 * `tests/Feature/Architecture/ReviewsTest.php` holds it there — the same
 * chokepoint `FeedbackPage` carries, for a sharper reason. Whether this page may
 * be served is three facts and not one (`is_published`, the owner's
 * `autopilot_settings.update_review_hub`, and that the slug matches the one that
 * resolved), and an inline `ReviewHubPage::query()->where('slug', …)` skips all
 * three. The failure is silent, because a page served that way looks exactly
 * like a live one.
 *
 * ⚠️ **`slug` IS A COPY OF THIS LOCATION'S `feedback_pages.slug` AND IS NOT
 * WHAT ROUTES THE REQUEST.** `/r/{slug}` resolves through `feedback_pages`,
 * which is the table carrying `public_read` — this one is tenant-scoped and is
 * read after the tenant exists. See `ReviewHubPages`' class docblock for why a
 * second `public_read` directory was refused, and for what checks the copy.
 *
 * ⚠️ **`title`, `intro_content` AND `last_generated_at` HAVE NO WRITER.** They
 * are DATA-MODEL §5.11's columns for `29` §6's generated hub intro, which is not
 * built; the page renders from `locations` and `reviews` alone. They are left
 * alone rather than dropped because they are a specified feature with a written
 * prompt, not the dead schema decision 311 removed — and left *unread*, so
 * nothing renders a value nothing writes.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $location_id
 * @property string $slug
 * @property ?string $title
 * @property ?string $intro_content
 * @property bool $is_published
 * @property ?Carbon $last_generated_at
 */
final class ReviewHubPage extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<ReviewHubPageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'last_generated_at' => 'datetime',
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
