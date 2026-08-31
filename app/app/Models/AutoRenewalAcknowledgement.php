<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\BillingTerm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * The account holder's acknowledgment that the plan renews by itself
 * (2980–2999).
 *
 * NOT A CONSENT TO BE CONTACTED, and it does not live in `consent_records` for
 * exactly that reason — see this table's migration. It permits nothing: it is
 * evidence that the person who bought the plan was told, in a distinct
 * affirmative act separate from pressing Save card, that it renews, at what
 * price, how often, and how to stop it.
 *
 * ⚠️ **IT IS NOT A CANCELLATION AND IT IS NOT A SUBSCRIPTION STATE.** A row
 * here says what somebody was shown. Whether they are subscribed, and until
 * when, is `subscriptions` and is written from a verified webhook.
 *
 * ⚠️ **WHAT IT DOES NOT DO.** It does not make the terms fair, it does not
 * discharge the reminder or the cancellation mechanism — the statute requires
 * all three and this is one — and it cannot be relied on to have been read. It
 * is the record of a disclosure, not proof of understanding.
 *
 * @property-read int $id
 * @property int $business_id
 * @property string $disclosure_version
 * @property string $method
 * @property BillingTerm $term
 * @property array<string, mixed> $proof
 * @property ?Carbon $created_at
 */
final class AutoRenewalAcknowledgement extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * Acknowledgment history is events; rows are written once.
     */
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * Append-only, enforced rather than asserted — `ReviewPhiConsent`'s
     * reasoning, and it applies here with the same force.
     *
     * This is evidence in a consumer-protection dispute. A row that can be
     * edited after the fact proves nothing about what was on the page, whatever
     * it says, and the one thing an UPDATE here could do is manufacture an
     * acknowledgment for a tenant who was never shown the terms — which is the
     * whole requirement defeated by one statement.
     */
    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'auto_renewal_acknowledgements is append-only. An acknowledgment is a dated '
                .'event: write a new row. Editing one rewrites the evidence, and the only '
                .'thing an edit could add is agreement nobody gave.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'auto_renewal_acknowledgements is append-only. Rows are never deleted; the '
                .'business\'s own erasure cascades them away in the database, which is the '
                .'only way one goes.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'term' => BillingTerm::class,
            'proof' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
