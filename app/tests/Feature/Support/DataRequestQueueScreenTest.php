<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Support\DataRequests;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class DataRequestQueueScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_renders_support_layout(): void
    {
        $admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get(route('support.data-requests'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_owner_gets_forbidden(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->get(route('support.data-requests'))
            ->assertForbidden();
    }

    public function test_renders_distinctive_detail(): void
    {
        $admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();

        app(DataRequests::class)->fileErasure((int) $business->id, $owner, 'Distinctive Data Request 7719');

        $this->actingAs($admin)->get(route('support.data-requests'))
            ->assertOk()
            ->assertSee('Distinctive Data Request 7719');
    }
}
