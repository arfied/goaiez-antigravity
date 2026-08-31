<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\OutreachChannel;
use Database\Factories\OwnerNotificationConsentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * The account holder's own agreement to be texted about their account
 * (10540) — `TermsAcceptance`'s shape, one record over.
 *
 * ⛔ **A TENANT'S RECORD, NEVER A CUSTOMER'S.** The account holder consented
 * on the setup wizard or the account settings screen to be texted at their own
 * mobile number about their own account. This table carries no `customer_id`
 * column, on purpose — see the creating migration.
 *
 * ⚠️ **IT PERMITS NOTHING BY ITSELF.** `App\Services\Consent\
 * OwnerNotifyNumber` is the current, mutable answer to "may this business be
 * texted, and at what number" — this row is the append-only evidence of one
 * disclosure the owner was shown and agreed to.
 *
 * ⚠️ **`e164` NAMES THE NUMBER THIS ROW IS EVIDENCE FOR, PLAINTEXT, ADDED
 * 2026-08-27 (wave 39 lane A, 10660).** Every other field this table carried
 * from the start answers "what were they shown, and when"; none of them
 * answered "which number" — the one thing a carrier complaint is actually
 * about. Nullable because a row written before this column existed cannot be
 * backfilled from inside a migration (`CLAUDE.md`: a tenant-owned column
 * cannot be backfilled by an `UPDATE` in one) — see the adding migration for
 * the full argument.
 *
 * @property-read int $id
 * @property int $business_id
 * @property OutreachChannel $channel
 * @property string $method
 * @property ?string $e164
 * @property string $accepted_by
 * @property string $disclosure_version
 * @property array<string, mixed> $proof
 * @property ?Carbon $created_at
 */
final class OwnerNotificationConsent extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<OwnerNotificationConsentFactory> */
    use HasFactory;

    /**
     * Consent history is events; rows are written once.
     */
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * Append-only, enforced rather than asserted — `ConsentRecord`'s and
     * `TermsAcceptance`'s reasoning, applied to a third record of the same
     * evidentiary shape. A row that can be edited after the fact proves
     * nothing about what was on the screen, whatever it says.
     */
    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'owner_notification_consents is append-only. A consent is a dated event: a new '
                .'disclosure is a new row. Editing one rewrites the evidence.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'owner_notification_consents is append-only. Rows are never deleted; the '
                .'business\'s own erasure cascades them away in the database, which is the only '
                .'way one goes.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => OutreachChannel::class,
            'proof' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
