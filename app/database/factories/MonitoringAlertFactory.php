<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use App\Models\MonitoringAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An open GBP unauthorised-edit alert. Does NOT default business_id —
 * BelongsToTenant fills it from the tenant in context (see LocationFactory).
 *
 * @extends Factory<MonitoringAlert>
 */
final class MonitoringAlertFactory extends Factory
{
    protected $model = MonitoringAlert::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'alert_type' => 'gbp_unauthorized_edit',
            'severity' => 'warning',
            'title' => 'Google changed your business hours — review the change.',
            'detail' => ['field' => 'hours'],
        ];
    }

    public function resolved(): self
    {
        return $this->state(fn (): array => [
            'resolved_at' => now(),
        ]);
    }
}
