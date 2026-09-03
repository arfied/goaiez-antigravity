<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\AgentThreadStatus;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A support thread across any channel (DATA-MODEL §5.9).
 *
 * consent_logged_at is the gate on storing message bodies at all: CIPA requires
 * notice before chat capture, and `29` §2 rule 22 makes 'no capture before
 * consent' build-failing.
 *
 * ⛔ **THE `agent_*` COLUMNS ARE `AgentThreadStates`' AND NOBODY ELSE'S** (T176
 * §2.3 rails 3 and 4). They are annotated so a read is typed; the contract's own
 * docblock says why there may be only one writer, and
 * `Architecture/AgentTest` fails the build on a second one.
 *
 * @property-read int $id
 * @property int $business_id
 * @property ?int $location_id
 * @property ?int $customer_id
 * @property string $channel
 * @property ?Carbon $first_response_at
 * @property ?Carbon $resolved_at
 * @property ?Carbon $consent_logged_at
 * @property ?Carbon $updated_at
 * @property-read ?Customer $customer
 * @property AgentThreadStatus $agent_status
 * @property int $agent_turns_used
 * @property ?int $agent_turn_cap
 * @property ?Carbon $agent_latched_at
 * @property ?int $agent_latched_by
 */
final class Conversation extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * ⚠️ **THE SCHEMA'S OWN DEFAULTS, RESTATED ON THE MODEL, AND NOT AS BELT AND
     * BRACES.** A model that has just been `create()`d does not read back the
     * column defaults the database applied, so `$conversation->agent_status`
     * would be `null` on the one instance most likely to be handed straight to
     * `AgentThreadStates` — and a null status has to be interpreted, with the
     * natural interpretation being the one that lets the agent speak. Declaring them here means the value is *"nobody has answered
     * this"* from the first instant rather than *"unknown"*.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'agent_status' => 'unhandled',
        'agent_turns_used' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_bot_handled' => 'boolean',
            'escalated_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'consent_logged_at' => 'datetime',
            // T176 §2.3 rails 3 and 4. ⛔ **READ THROUGH `AgentThreads`, NEVER
            // WRITTEN FROM ANYWHERE ELSE.** These are cast so a state read is
            // typed, not so a second caller can set them — the contract's own
            // docblock says *"the thing most likely to break rail 4 is a second
            // place that sets the same column"*, and `Architecture/AgentTest`
            // fails the build on any writer outside `AgentThreadStates`.
            'agent_status' => AgentThreadStatus::class,
            'agent_turns_used' => 'integer',
            'agent_turn_cap' => 'integer',
            'agent_latched_at' => 'datetime',
            'agent_latched_by' => 'integer',
        ];
    }

    /**
     * The person this thread is with.
     *
     * ⚠️ **NULLABLE, AND THE INBOX HAS TO SURVIVE IT.** The column is
     * `nullOnDelete`, so a contact the owner deleted leaves the thread standing
     * with nobody on the other end — which is a thread that can be read and
     * never replied to, and the screen says so rather than rendering a blank
     * name.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Whether this thread has been cleared to store message content.
     */
    public function hasLoggedConsent(): bool
    {
        return $this->consent_logged_at !== null;
    }
}
