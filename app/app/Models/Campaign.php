<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\CampaignAudience;
use App\Enums\CampaignKind;
use App\Enums\CampaignStatus;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One campaign — a reactivation (T137 `SL-2`) or an SMS broadcast (3310).
 *
 * ⛔ **`kind` IS THE ONLY THING THAT TELLS THE TWO APART, AND NOTHING ELSE ON
 * THIS ROW CAN.** Both send with `OutreachPurpose::Marketing`, because
 * reactivation **is** marketing (2100), and both may be dormant-segment or
 * hand-picked. What differs is whose 10DLC brand and whose number carries the
 * traffic, which `App\Services\Campaigns\BroadcastPreconditions` decides at send
 * time and never here.
 *
 * ⚠️ **NOT `ReviewRequestCampaign`.** That table has a model, a factory and an
 * isolation test and has never had a writer — decision 272's shape — so
 * designing against it would have meant inheriting columns shaped for review
 * invitations by somebody who never had to make them work. 1222's rule: check
 * for a writer before *designing*, not before querying.
 *
 * Written through `App\Services\Campaigns\Campaigns` and sent by
 * `App\Jobs\RunCampaignJob`.
 *
 * ⛔ **`status` IS NOT THE KILL SWITCH.** The per-tenant pause is
 * `businesses.paused_at` through `TenantPause`, the suspension is
 * `TenantSuspension`, and the global halt is the `sms.enabled` registry row.
 * All three are re-read **per recipient** by the runner and none of them writes
 * this column, so a resumed tenant's campaigns carry on where they were rather
 * than needing to be un-stopped by hand.
 *
 * @property CampaignStatus $status
 * @property CampaignAudience $audience
 * @property CampaignKind $kind
 * @property ?Carbon $confirmed_at
 * @property ?string $confirmation_actor
 * @property ?Carbon $scheduled_for
 * @property ?Carbon $started_at
 * @property ?Carbon $finished_at
 * @property string $body_template
 * @property ?string $link
 * @property ?string $base_image_path
 * @property ?int $location_id
 */
final class Campaign extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    /**
     * `business_id` comes from the tenant in context via `BelongsToTenant`,
     * never from a caller's array.
     *
     * ⚠️ **`confirmed_at` AND `confirmation_actor` ARE GUARDED, AND THAT IS THE
     * LOAD-BEARING PAIR.** They are the record that a person approved the first
     * send of a campaign type (decision 2106), and a mass-assignable approval is
     * one any caller can assert while every constraint above still passes.
     * `Campaigns::confirm()` sets them or nothing does.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id', 'confirmed_at', 'confirmation_actor'];

    /**
     * ⚠️ **THE SAME DEFAULT THE COLUMN CARRIES, SAID TWICE ON PURPOSE.** The
     * migration's default answers for rows already in the table and for anything
     * that inserts without this attribute; this answers for the **in-memory**
     * model, which the database default cannot reach until a `refresh()`. Without
     * it a freshly created campaign has `kind === null` between the insert and
     * the next read, and the first thing that touches it — `Campaigns::confirm()`
     * recording what was approved — reads a property on null.
     *
     * ⚠️ **`reactivation` IS TRUE OF EVERY CAMPAIGN THIS ENGINE HAD BEFORE 3310**
     * and is also the direction this has to fail: a row nobody classified sends
     * over the platform brand that is already carrying it, whereas a default of
     * `broadcast` would put an unclassified campaign in front of preconditions it
     * cannot satisfy.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'kind' => CampaignKind::Reactivation->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'audience' => CampaignAudience::class,
            'kind' => CampaignKind::class,
            'confirmed_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return HasMany<CampaignRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /**
     * The `SendKey` occasion every send on this campaign carries.
     *
     * ⛔ **IT MOVED HERE THE DAY IT ACQUIRED A SECOND READER** (P20). It was a
     * literal inside `RunCampaignJob`, which was right while the runner was the
     * only thing that had to know it; `CampaignReplyResolver` now has to
     * reproduce the same string to say which send a reply is answering, and two
     * spellings of the value `SendKey` hashes is how a reply gets filed under an
     * occasion no send ever used. The string itself is unchanged — the `:step:1`
     * is still spelled out for the day a campaign gains a second step.
     *
     * ⚠️ **DERIVED PER ROW, NOT A CONSTANT, WHICH IS `SendKey`'s OWN RULE.** A
     * caller passing a constant collapses every message it ever sends a contact
     * into one key, and the second is dropped as a duplicate with no error
     * anywhere. The campaign id is what keeps them apart.
     */
    public function sendOccasion(): string
    {
        return "campaign:{$this->getKey()}:step:1";
    }
}
