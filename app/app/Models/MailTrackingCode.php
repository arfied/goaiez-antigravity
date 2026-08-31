<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MailTrackingCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The eight characters pairing one message to one customer and one client (2097).
 *
 * NOT TENANT-OWNED, on the `TenancyTest` scope allowlist with its reason there
 * and in the creating migration: this row is what *establishes* the tenant for
 * an inbound reply that arrives with none, so a global scope would hide it from
 * the only query that can find out whose it is. `ImpersonationSession`'s shape
 * (562), not `OptOut`'s.
 *
 * ⚠️ **`MailTrackingCodes` IS THE ONLY WRITER AND THE ONLY READER**, held there
 * by a chokepoint lint. The predicate that replaces the missing global scope is
 * a lookup by code, and it is written in exactly one place for that reason.
 *
 * ⚠️ **THERE ARE TWO HANDLES ON THIS ROW SINCE 6360, AND THE SECOND IS WHY
 * EMAIL HAS A COMPLAINT RATE AT ALL.** `code` is what a *reply* comes back on;
 * `transport_message_id` is the SES message id every *feedback event* comes
 * back on.
 * Both are minted by one send and both answer the same question — **whose
 * message was this** — for an inbound thing that names no tenant. Adding the
 * second here rather than in a table of its own is argued in the migration that
 * introduced it; the short version is that this row already had the business,
 * the outreach message, the lifetime and the `USING (true)` policy that a
 * tenantless resolution needs, and a second table would have copied all four to
 * hold one string.
 *
 * @property-read int $id
 * @property string $code
 * @property int $business_id
 * @property int $customer_id
 * @property ?int $outreach_message_id
 * @property ?string $transport_message_id
 * @property ?CarbonImmutable $replied_at
 */
final class MailTrackingCode extends Model
{
    /** @use HasFactory<MailTrackingCodeFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'customer_id' => 'integer',
            'outreach_message_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'replied_at' => 'immutable_datetime',
        ];
    }
}
