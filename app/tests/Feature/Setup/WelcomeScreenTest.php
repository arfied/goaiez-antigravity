<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Enums\WizardStep;
use App\Models\User;
use App\Models\WizardProgress;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class WelcomeScreenTest extends TestCase
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
            'current_step' => WizardStep::Welcome,
        ]);

        $this->actingAs($user)->get(route('setup.welcome'))
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
            'current_step' => WizardStep::Welcome,
        ]);

        $this->actingAs($user)->get(route('setup.welcome'))
            ->assertOk()
            ->assertSee('Four short steps. You can stop anywhere and pick it up later.');
    }

    public function test_renders_business_name_from_audit(): void
    {
        $user = User::factory()->create();
        $business = $this->provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::set((int) $business->id);
        Tenancy::setUser($user->id);

        WizardProgress::query()->update([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'current_step' => WizardStep::Welcome,
            'data' => ['audit' => ['name' => 'Distinctive Person 7719']],
        ]);

        $this->actingAs($user)->get(route('setup.welcome'))
            ->assertOk()
            ->assertSee('Distinctive Person 7719');
    }
}
