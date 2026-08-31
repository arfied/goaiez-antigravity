<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One reviewer's undertaking not to include health information (2079-2081).
 *
 * NOT A CONSENT TO BE CONTACTED, and it does not live in `consent_records` for
 * exactly that reason — see this table's migration. It is the per-review answer
 * to a narrower question: may *this* piece of writing be sent to a third-party
 * model for moderation and analysis.
 *
 * ⚠️ WHAT IT DOES NOT DO, because a row here reads as more than it is. It is
 * not a BAA. It does not make a provider we hold no agreement with a lawful
 * recipient of health information that arrives anyway — a person who ticks the
 * box and then writes about their root canal has produced exactly the exposure
 * rule 24 exists to prevent, and this row does not cure it. And it cannot be
 * relied on to have worked: the undertaking is the reviewer's, the obligation
 * stays ours. It reduces exposure; it does not discharge anything.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $review_id
 * @property string $disclosure_version
 * @property string $method
 * @property array<string, mixed> $proof
 * @property ?Carbon $created_at
 */
final class ReviewPhiConsent extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * Consent history is events; rows are written once.
     */
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * Append-only, enforced rather than asserted — `ConsentRecord`'s reasoning,
     * which applies here for the first of its two reasons.
     *
     * This is evidence. A row that can be edited after the fact proves nothing
     * about what somebody agreed to, whatever it says; and the one thing an
     * UPDATE here could do is manufacture agreement for a review whose author
     * never gave any, which is the whole gate defeated by one statement.
     *
     * ⚠️ THE CASCADE IS NOT DEFEATED BY THIS. `review_id` is
     * `cascadeOnDelete`, and a database cascade fires no Eloquent event — so a
     * review that is genuinely erased takes its consent rows with it. That is
     * correct: the undertaking is *about* that writing, and keeping it after the
     * writing is gone would leave a record of consent to analyse nothing.
     */
    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'review_phi_consents is append-only. A reviewer\'s undertaking is a dated '
                .'event: write a new row. Editing one rewrites the evidence, and the only '
                .'thing an edit could add is agreement nobody gave.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'review_phi_consents is append-only. Rows are never deleted; a review\'s '
                .'own erasure cascades them away in the database, which is the only way '
                .'one goes.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proof' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
