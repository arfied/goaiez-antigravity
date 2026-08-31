<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Services\Agent\AgentNudges;
use Database\Factories\AgentNudgeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The single follow-up one silent thread is owed — T176 skill 14, patch P11.
 *
 * ⚠️ **WRITTEN AND MOVED ONLY BY {@see AgentNudges}**, which is
 * `AgentThreadStates`' rule for its reason: the property this row exists to
 * guarantee is *"one, then stop"*, and a second place that sets `sent_at` is how
 * that stops being true.
 *
 * ⛔ **IT IS A SCHEDULE, NOT A PERMIT.** Consent, suppression, STOP, the Do Not
 * Call registers and the recipient-local daytime window are all downstream and
 * unchanged. A row here says the assistant *would like* to follow up; nothing
 * here says this person may be messaged.
 *
 * @property-read int $id
 * @property int $business_id
 * @property ?int $location_id
 * @property int $conversation_id
 * @property int $customer_id
 * @property Carbon $armed_at
 * @property int $armed_at_turns
 * @property Carbon $due_at
 * @property Carbon $expires_at
 * @property ?Carbon $sent_at
 * @property ?Carbon $cancelled_at
 * @property ?string $cancel_reason
 */
final class AgentNudge extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<AgentNudgeFactory> */
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
            'armed_at' => 'datetime',
            'armed_at_turns' => 'integer',
            'due_at' => 'datetime',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Whether this nudge is still waiting to go.
     *
     * ⚠️ **ONE PREDICATE RATHER THAN TWO NULL CHECKS AT EVERY CALL SITE**, on
     * `ThreadState::mayTakeTurn()`'s reasoning: two conditions asked in two
     * places is how one of them gets asked in only one of them.
     */
    public function isPending(): bool
    {
        return $this->sent_at === null && $this->cancelled_at === null;
    }
}
