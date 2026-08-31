<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\BrandRegistrationStatus;
use App\Services\Sms\BrandRegistrations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One tenant's own 10DLC filing — decision 3310's first precondition.
 *
 * Written only by {@see BrandRegistrations}, which is also the only thing that
 * reads it on a sending path.
 *
 * ⛔ **THE APPROVAL COLUMNS ARE NOT FILLABLE, AND THAT IS THE LOAD-BEARING
 * SET.** `status`, `provider_brand_id`, `provider_campaign_id`, `approved_at`,
 * `rejected_at` and `rejection_reason` together are the statement *"the carriers
 * approved this tenant to send on their own brand"*, which is what unlocks a
 * marketing blast to a list we did not capture the consent for. A
 * mass-assignable approval is one any caller can assert while every constraint
 * above still passes — `Campaign::$guarded`'s argument about `confirmed_at`, in
 * the place where the consequence is a send rather than a record.
 *
 * @property-read int $id
 * @property int $business_id
 * @property string $provider
 * @property BrandRegistrationStatus $status
 * @property ?string $provider_brand_id
 * @property ?string $provider_campaign_id
 * @property Carbon $submitted_at
 * @property ?Carbon $approved_at
 * @property ?Carbon $rejected_at
 * @property ?string $rejection_reason
 * @property string $submitted_by
 */
final class BrandRegistration extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * `business_id` comes from the tenant in context via `BelongsToTenant`,
     * never from a caller's array.
     *
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'submitted_at',
        'submitted_by',
    ];

    /**
     * Is this filing what lets the tenant send on their own brand right now?
     *
     * ⚠️ **A READER'S CONVENIENCE, NEVER THE SEND-PATH ANSWER.** The send path
     * asks {@see BrandRegistrations::isApproved()}, which asks the database.
     * This exists so a screen holding a row does not re-derive the rule —
     * `SendingPause::isLive()`'s argument, and for the same reason: two ideas of
     * what "approved" means is how a guard and a constraint stop agreeing.
     */
    public function permitsSending(): bool
    {
        return $this->status->permitsSending();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BrandRegistrationStatus::class,
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
        ];
    }
}
