<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending CONFIRM for a GBP core-field change — one of the exactly three
 * things CONFIRM is reserved for. Does NOT default business_id —
 * BelongsToTenant fills it from the tenant in context (see LocationFactory).
 *
 * @extends Factory<ApprovalRequest>
 */
final class ApprovalRequestFactory extends Factory
{
    protected $model = ApprovalRequest::class;

    public function definition(): array
    {
        return [
            'type' => 'gbp_core_fields',
            'channel' => 'sms',
            'message' => 'Google shows your phone number differently. Reply YES to update it.',
            'options' => ['yes', 'no'],
            'status' => 'pending',
            'expires_at' => now()->addDay(),
            'auto_action_on_expiry' => 'skip',
        ];
    }

    public function approved(): self
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'responded_at' => now(),
            'response' => 'yes',
        ]);
    }
}
