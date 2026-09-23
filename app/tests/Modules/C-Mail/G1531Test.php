<?php

declare(strict_types=1);

namespace App\Tests\Modules\CMail;

use App\Modules\CMail\Actions\EmailWarmupAction;
use App\Modules\CMail\Exceptions\ConstantWarmupQuantityRefused;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class G1531Test extends TestCase
{
    #[Test]
    #[Group('G15-31')]
    public function refuses_constant_quantity_for_warmup(): void
    {
        $business = self::provisionTenant();
        $businessId = $business->id;

        Tenancy::actingAs($businessId, function () use ($businessId) {
            $action = new EmailWarmupAction(app(DefaultsRegistry::class));

            $schedule = [
                'day_1' => ['min' => 50, 'max' => 50, 'quantity' => 50],
            ];

            $this->expectException(ConstantWarmupQuantityRefused::class);
            $this->expectExceptionMessage('Warm-up quantity for day_1 is a constant; G15-31 requires a range plus jitter.');

            $action->handle(businessId: $businessId, mailDomainId: 1, schedule: $schedule);
        });
    }
}
