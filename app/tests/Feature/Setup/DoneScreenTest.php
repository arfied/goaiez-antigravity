<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Enums\WizardStep;
use App\Models\User;
use App\Models\WizardProgress;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class DoneScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_renders_setup_layout(): void
    {
        $user = User::factory()->create();
        $business = $this->provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::set((int) $business->id);
        Tenancy::setUser($user->id);

        WizardProgress::query()->update([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'current_step' => WizardStep::Done,
        ]);

        $this->actingAs($user)->get(route('setup.done'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_renders_screen_copy(): void
    {
        $user = User::factory()->create();
        $business = $this->provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::set((int) $business->id);
        Tenancy::setUser($user->id);

        WizardProgress::query()->update([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'current_step' => WizardStep::Done,
        ]);

        $this->actingAs($user)->get(route('setup.done'))
            ->assertOk()
            ->assertSee('Your account');
    }

    public function test_renders_summary_for_tenant_setup(): void
    {
        $user = User::factory()->create();
        $business = $this->provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::set((int) $business->id);
        Tenancy::setUser($user->id);

        WizardProgress::query()->update([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'current_step' => WizardStep::Done,
        ]);

        $this->actingAs($user)->get(route('setup.done'))
            ->assertOk()
            ->assertSee('Get a text when something needs you');
    }
}
