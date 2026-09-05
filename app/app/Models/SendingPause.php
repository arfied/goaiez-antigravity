<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\SendingPauseReason;
use App\Services\Messaging\SendingGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One incident of a tenant being stopped from sending — T137 `SL-08`'s per-tenant
 * kill switch, and the record of every time it fired.
 *
 * ⚠️ **A LIVE ROW IS THE STATE, AND A RELEASED ROW IS THE HISTORY** (2470). Until
 * 2119(a) this was *"the row's existence is the state"* and `resume()` deleted
 * it, which destroyed the reason, the actor and `observed_rate_bp` — a snapshot
 * of the rate at the moment of the trip that **cannot be recomputed**, because
 * the window has rolled by the time anybody asks. Now `released_at IS NULL` is
 * what "paused" means, and nothing is ever deleted.
 *
 * There is still no `paused` boolean, and the creating migration's reason for
 * that is untouched: a boolean needs a writer at provisioning time and would
 * inherit `CLAUDE.md`'s writerless-control failure.
 *
 * Written only by {@see SendingGuard}, which is also the only thing that reads it
 * on the sending path.
 *
 * ⚠️ **THE RELEASE COLUMNS ARE DELIBERATELY NOT FILLABLE.** Releasing a tenant is
 * the act that switches sending back on, and a request body carrying
 * `released_at` must never be the thing that performs it — `Business::$guarded`'s
 * rule for `paused_at`, for the identical reason. `SendingGuard::resume()` writes
 * them through an explicit, conditional update.
 *
 * @property-read int $id
 * @property int $business_id
 * @property SendingPauseReason $reason
 * @property string $tripped_by
 * @property ?int $observed_rate_bp
 * @property ?string $note
 * @property ?Carbon $released_at
 * @property ?string $released_by
 * @property ?string $release_note
 * @property Carbon $created_at
 */
final class SendingPause extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $fillable = [
        'reason',
        'tripped_by',
        'observed_rate_bp',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => SendingPauseReason::class,
            'released_at' => 'immutable_datetime',
        ];
    }

    /**
     * Is this the incident currently stopping the tenant?
     *
     * ⚠️ **A READER'S CONVENIENCE, NEVER THE SEND-PATH ANSWER.** The send path
     * asks {@see SendingGuard::isPaused()}, which asks the database with the same
     * predicate the partial unique index uses. This exists so a screen holding a
     * row does not have to re-derive the rule, and it is stated here rather than
     * left ambiguous because two ideas of what "paused" means is how the guard
     * and the constraint stop agreeing.
     */
    public function isLive(): bool
    {
        return $this->released_at === null;
    }
}
