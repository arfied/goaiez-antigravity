<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\CallAnsweredBy;
use App\Enums\CallOutcome;
use Database\Factories\CallFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One inbound call to a tenant's number (T176 P2, voice call forwarding).
 *
 * ⛔ **INBOUND ONLY, AND THE ABSENCE OF A DIRECTION IS THE ENFORCEMENT.**
 * `29` §2.3 rule 13 was **overridden by an owner ruling on 2026-08-25** (9363),
 * and **what refuses a call here is the schema and a lint rather than the
 * rule**: there is no `direction` column, no scope and no factory state for an
 * outbound call, and `VoiceTest`'s project-wide call-direction census fails the
 * build on one being declared anywhere. Adding it is not a schema change — it is
 * a request to build outbound calling.
 *
 * ⚠️ **`from_e164` IS A MEMBER OF THE PUBLIC'S MOBILE NUMBER.** It is protected
 * by the tenant boundary — the global scope above, `ENABLE`+`FORCE` row-level
 * security beneath — and not by a hash, for the reason the migration argues at
 * length. **It must never reach a log, a toast, an audit `metadata` blob or a
 * broadcast payload**; `MissedCallTextBack` refuses to name it in the audit log
 * even when recording a near-miss about it.
 *
 * ⛔ **THE ONLY WRITER IS `App\Services\Voice\VoiceCalls`.** A second writer is
 * a second idea of what "this call was missed" means, and the thing downstream
 * of that idea is an irreversible message to a stranger.
 *
 * @property int $business_id
 * @property ?int $location_id
 * @property ?int $customer_id
 * @property string $provider_call_id
 * @property string $from_e164
 * @property string $to_e164
 * @property CallOutcome $outcome
 * @property ?string $provider_state
 * @property ?Carbon $started_at
 * @property ?Carbon $answered_at
 * @property ?Carbon $ended_at
 * @property ?int $ring_seconds
 * @property ?CallAnsweredBy $answered_by
 * @property ?string $message_name
 * @property ?string $message_callback
 * @property ?string $message_text
 * @property ?Carbon $message_left_at
 * @property ?Carbon $message_notified_at
 * @property ?Voicemail $voicemail
 */
final class Call extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<CallFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return HasOne<Voicemail, $this>
     */
    public function voicemail(): HasOne
    {
        return $this->hasOne(Voicemail::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => CallOutcome::class,
            'answered_by' => CallAnsweredBy::class,
            'started_at' => 'datetime',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
            'message_left_at' => 'datetime',
            'message_notified_at' => 'datetime',
        ];
    }
}
