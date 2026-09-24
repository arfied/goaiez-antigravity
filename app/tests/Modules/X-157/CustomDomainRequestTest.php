<?php

declare(strict_types=1);

namespace Tests\Modules\X157;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X157\Actions\CustomDomainRequestAction;
use App\Modules\X157\Actions\CustomDomainStatusAction;
use App\Modules\X157\Models\CustomDomainRequest;
use App\Support\Tenancy;
use InvalidArgumentException;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class CustomDomainRequestTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_valid_domain_records_once(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $business = $this->provisionTenant(['owner_user_id' => $user->id]);

        Tenancy::set($business->id);

        $action = new CustomDomainRequestAction;
        $request1 = $action->handle($business->id, 'example.com');

        $this->assertEquals('example.com', $request1->domain);
        $this->assertNotNull($request1->requested_at);

        $request2 = $action->handle($business->id, 'Example.com');
        $this->assertEquals($request1->id, $request2->id);

        $this->assertDatabaseCount('custom_domain_requests', 1);
    }

    public function test_invalid_domain_throws(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $business = $this->provisionTenant(['owner_user_id' => $user->id]);

        Tenancy::set($business->id);

        $action = new CustomDomainRequestAction;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a domain');
        $action->handle($business->id, 'not a domain');
    }

    public function test_tenant_rls(): void
    {
        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        Tenancy::set($bizA->id);
        $action = new CustomDomainRequestAction;
        $action->handle($bizA->id, 'example-a.com');

        $this->assertDatabaseHas('custom_domain_requests', ['domain' => 'example-a.com']);
        $this->assertEquals(1, CustomDomainRequest::count());

        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);

        $this->assertEquals(0, CustomDomainRequest::count());
        $this->assertDatabaseMissing('custom_domain_requests', ['domain' => 'example-a.com']);
    }

    public function test_the_domain_panel_and_the_check_follow_the_latest_request(): void
    {
        $business = $this->provisionTenant([]);
        Tenancy::set($business->id);

        $action = new CustomDomainRequestAction;
        $action->handle($business->id, 'exmaple-4653.com');
        $action->handle($business->id, 'example-4653.com');

        $status = app(CustomDomainStatusAction::class)->handle($business->id);

        $this->assertEquals('example-4653.com', $status['requested_domain']);
        $this->assertEquals('requested', $status['status']);
        $this->assertArrayNotHasKey('zone_domain', $status);
        $this->assertArrayNotHasKey('has_valid_ssl', $status);
    }
}
