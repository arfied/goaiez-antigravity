<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Enums\WizardStep;
use App\Models\FeedbackPage;
use App\Models\Location;
use App\Models\User;
use App\Models\WizardProgress;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class HowCustomersReachScreenTest extends TestCase
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
            'current_step' => WizardStep::HowCustomersReach,
        ]);

        $this->actingAs($user)->get(route('setup.how-customers-reach'))
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
            'current_step' => WizardStep::HowCustomersReach,
        ]);

        $this->actingAs($user)->get(route('setup.how-customers-reach'))
            ->assertOk()
            ->assertSee('How customers reach you');
    }

    public function test_renders_saved_channel(): void
    {
        $user = User::factory()->create();
        $business = $this->provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::set((int) $business->id);
        Tenancy::setUser($user->id);

        WizardProgress::query()->update([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'current_step' => WizardStep::HowCustomersReach,
        ]);

        $location = Location::query()->sole();
        FeedbackPage::query()->where('location_id', $location->id)->update(['slug' => 'distinctive-slug-7719']);

        $this->actingAs($user)->get(route('setup.how-customers-reach'))
            ->assertOk()
            ->assertSee('distinctive-slug-7719');
    }
}
