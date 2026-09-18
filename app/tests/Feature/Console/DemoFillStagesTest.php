<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class DemoFillStagesTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function tearDown(): void
    {
        Tenancy::forgetAll();
        parent::tearDown();
    }

    public function test_the_stages_screens_show_the_demo_rows(): void
    {
        $owner = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', [
            'email' => $owner->email,
            '--only' => 'X-204',
        ])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->actingAs($owner);

        // X-204
        $this->get(route('x-204.refusals-by-reason.admin'))->assertOk()->assertSee('demo·+15550100001');
        $this->get(route('x-204.register-slot-states.admin'))->assertOk()->assertSee('demo·TCPA Safe Harbor');
    }
}
