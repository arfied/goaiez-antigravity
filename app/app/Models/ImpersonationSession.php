<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImpersonationEnding;
use App\Enums\ImpersonationMode;
use Database\Factories\ImpersonationSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One bounded, reasoned, recorded visit by support into a customer's account.
 *
 * NOT TENANT-OWNED, on the `TenancyTest` allowlist with its reason there
 * and in the migration. It is the table that *establishes* the tenant for an
 * impersonated request, read by an agent who has none of their own, so a global
 * scope calling `Tenancy::idOrFail()` would throw on the query it exists to
 * serve. `feedback_pages` (318) and `plugins` (400) are the same shape.
 *
 * ⚠️ **Reading this model is not authorisation.** A row saying a session is live
 * means somebody started one; whether *this* request may use it is
 * `App\Services\Impersonation\Impersonation`'s question, and it asks three more
 * — is it this agent's, has it lapsed, does the mode permit what is being
 * attempted. Nothing outside that service should construct or interpret one of
 * these, and a lint holds it there.
 *
 * @property-read int $id
 * @property int $agent_id
 * @property int $business_id
 * @property ImpersonationMode $mode
 * @property string $reason
 * @property ?string $ticket_ref
 * @property Carbon $started_at
 * @property Carbon $expires_at
 * @property ?Carbon $ended_at
 * @property ?ImpersonationEnding $ended_reason
 * @property int $page_views
 * @property int $writes
 */
final class ImpersonationSession extends Model
{
    /** @use HasFactory<ImpersonationSessionFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // The database refuses this too, in a trigger. Both, on slice B's
        // thesis (303–316): the trigger catches the repair script that never
        // loaded a model, and this catches the ordinary caller with a message
        // naming the rule instead of a SQLSTATE.
        self::deleting(function (): never {
            throw new LogicException(
                'impersonation_sessions is a permanent record. A support session that the '
                .'person who opened it can delete is not a record of anything.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => ImpersonationMode::class,
            'ended_reason' => ImpersonationEnding::class,
            'started_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'page_views' => 'integer',
            'writes' => 'integer',
        ];
    }

    /**
     * Whether this session is still usable *as a record*, ignoring who is asking.
     *
     * ⚠️ Both halves are load-bearing and the second is the one that bites. A
     * session ends by having `ended_at` written, and it lapses by the clock
     * passing `expires_at` with nothing having written anything — the second
     * state has no marker at all until something notices. Checking only
     * `ended_at` is how an expired session keeps working, which is precisely
     * the build-failing test in `28` §9.4.
     */
    public function isLive(): bool
    {
        return $this->ended_at === null && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * How this session names its actor in `audit_log`.
     *
     * `28` §9.4 fixes the format as `support:{agent_id}`, and it is worth
     * keeping literal: `AuditService`'s actor is a string precisely so that
     * automation and staff are both first-class, and a reader scanning for
     * "when was support in here" greps one prefix.
     */
    public function auditActor(): string
    {
        return 'support:'.$this->agent_id;
    }
}
