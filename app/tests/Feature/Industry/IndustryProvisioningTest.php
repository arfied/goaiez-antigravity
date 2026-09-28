<?php

declare(strict_types=1);

namespace Tests\Feature\Industry;

use App\Models\PublicAudit;
use App\Models\User;
use App\Services\TenantProvisioner;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class IndustryProvisioningTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_provisions_with_industry_derived_from_categories(): void
    {
        $user = User::factory()->create();
        $audit = PublicAudit::factory()->create(['categories' => ['point_of_interest', 'electrician']]);

        $business = app(TenantProvisioner::class)->provision($user, $audit->token);

        $this->assertEquals('trades', $business->industry->value);

        $user2 = User::factory()->create();
        $audit2 = PublicAudit::factory()->create(['categories' => ['museum']]);
        $business2 = app(TenantProvisioner::class)->provision($user2, $audit2->token);

        $this->assertNull($business2->industry);
    }
}
