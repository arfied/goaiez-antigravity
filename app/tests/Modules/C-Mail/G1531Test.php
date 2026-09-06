<?php

declare(strict_types=1);

namespace App\Tests\Modules\CMail;

use App\Modules\CMail\Actions\EmailWarmupAction;
use App\Modules\CMail\Exceptions\ConstantWarmupQuantityRefused;
use App\Support\Tenancy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class G1531Test extends TestCase
{
    #[Test]
    public function refuses_constant_quantity_for_warmup(): void
    {
        // G15-31: every quantity is a RANGE plus jitter — a constant is the signature · refuses: a constant quantity
        $businessId = 111;
        
        Tenancy::actingAs($businessId, function () use ($businessId) {
            $action = new EmailWarmupAction();

            $schedule = [
                'day_1' => ['min' => 50, 'max' => 50, 'quantity' => 50],
            ];

            $this->expectException(ConstantWarmupQuantityRefused::class);
            $this->expectExceptionMessage('Warm-up quantity for day_1 is a constant; G15-31 requires a range plus jitter.');

            $action->handle(businessId: $businessId, mailDomainId: 1, schedule: $schedule);
        });
    }
}
