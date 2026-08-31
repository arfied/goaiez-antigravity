<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\CapturedBy;
use App\Enums\CaptureSurface;
use App\Enums\ConsentType;
use App\Enums\OutreachChannel;
use Database\Factories\ConsentRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A consent event (DATA-MODEL §5.6) — never a boolean.
 *
 * Wording version, capture surface, timestamp, and proof (ip_hash, never raw
 * IP) are what make consent defensible. `captured_by` routes the messaging
 * lane at the service layer; the platform only sends where the platform owns
 * the consent record.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $customer_id
 * @property OutreachChannel $channel
 * @property ?ConsentType $consent_type
 * @property CapturedBy $captured_by
 * @property CaptureSurface $capture_surface
 * @property string $disclosure_version
 * @property ?string $method
 * @property ?array<string, mixed> $proof
 * @property ?Carbon $created_at
 */
final class ConsentRecord extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<ConsentRecordFactory> */
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
     * Append-only, enforced rather than asserted.
     *
     * The constant above says rows are written once and, on its own, only stops
     * Eloquent maintaining an updated_at. Nothing prevented an UPDATE, and two
     * things ride on that being impossible.
     *
     * The first is evidentiary. A consent record is what this company would hand
     * a regulator or a carrier to show somebody agreed to be messaged; a record
     * that can be edited after the fact proves nothing, whatever it says.
     *
     * The second is operational and worse, because it is silent. `captured_by`
     * routes the messaging lane, so `UPDATE consent_records SET captured_by =
     * 'platform'` moves a tenant-attested contact onto the shared platform
     * toll-free number, and ConsentService::refreshDerived() will re-derive the
     * lane from the edited row without complaint. `29` §19.4 requires that a
     * tenant-lane contact cannot send from the platform number; one admin CRUD
     * screen or one repair script would defeat it.
     *
     * The same shape as AuditLogEntry and ActivityFeedItem, which have had this
     * since Stage 0 — the mechanism already existed two files away.
     */
    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'consent_records is append-only (DATA-MODEL §5.14). Consent is a '
                .'history of events: write a new record, or a suppression entry '
                .'to withdraw. Editing one rewrites the evidence.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'consent_records is append-only (DATA-MODEL §5.14). Rows are never '
                .'deleted — a withdrawal is a suppression entry, not an erasure.'
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
            'consent_type' => ConsentType::class,
            'captured_by' => CapturedBy::class,
            'capture_surface' => CaptureSurface::class,
            'proof' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
