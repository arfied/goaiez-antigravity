<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ClickDeviceClass;
use App\Enums\ClickDiscardReason;
use App\Models\ShortLink;
use App\Models\ShortLinkClick;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context.
 *
 * @extends Factory<ShortLinkClick>
 */
final class ShortLinkClickFactory extends Factory
{
    protected $model = ShortLinkClick::class;

    public function definition(): array
    {
        return [
            'short_link_id' => ShortLink::factory(),
            'clicked_at' => now(),
            'counted' => true,
            'discard_reason' => null,
            'device_class' => ClickDeviceClass::Phone,
        ];
    }

    /**
     * A fetch that was not a person — the carrier scanner case.
     */
    public function discarded(ClickDiscardReason $reason = ClickDiscardReason::DeclaredBot): self
    {
        return $this->state(fn (): array => [
            'counted' => false,
            'discard_reason' => $reason,
        ]);
    }
}
