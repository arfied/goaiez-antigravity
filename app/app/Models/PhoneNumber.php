<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessagingLane;
use App\Enums\NumberRole;
use App\Enums\NumberState;
use App\Services\Sms\NumberLifecycle;
use App\Services\Sms\SendingNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One of the platform's own SMS-sending numbers — doc 51 §2, §10.
 *
 * NOT TENANT-OWNED, DESPITE CARRYING A REAL `business_id` — see the creating
 * migration for why `impersonation_sessions`' shape applies rather than a
 * global scope, and `TenancyTest`'s allowlist for the same argument.
 *
 * ⚠️ **`state` IS WRITTEN ONLY BY {@see NumberLifecycle}.**
 * Setting it directly elsewhere bypasses the transition-legality table and
 * leaves `number_state_changes` with no row for a state the number is
 * actually in — a chokepoint lint in `MessagingTest` holds that by confining
 * this class to the four files allowed to name it.
 *
 * ⚠️ **NOTHING HERE IS A CONSENT DECISION.** A number in a sendable state is a
 * number *this platform* may originate traffic from; whether a message may go to
 * a particular person is `SendPermit`'s question and is asked one layer up. The
 * two refusals look alike from a call site and are not interchangeable — see
 * {@see SendingNumber}.
 *
 * @property-read int $id
 * @property ?int $business_id
 * @property ?int $location_id
 * @property string $e164
 * @property ?string $provider_number_id
 * @property NumberRole $role
 * @property NumberState $state
 * @property ?string $state_reason
 * @property ?MessagingLane $lane
 * @property ?Carbon $quarantined_at
 * @property ?Carbon $recovering_at
 * @property ?Carbon $retired_at
 * @property ?Carbon $released_at
 */
final class PhoneNumber extends Model
{
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
            'lane' => MessagingLane::class,
            'role' => NumberRole::class,
            'state' => NumberState::class,
            'health_score' => 'integer',
            'quarantined_at' => 'datetime',
            'recovering_at' => 'datetime',
            'purchased_at' => 'datetime',
            'retired_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }
}
