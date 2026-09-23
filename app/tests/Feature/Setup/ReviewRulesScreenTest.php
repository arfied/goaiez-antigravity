<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Enums\WizardStep;
use App\Livewire\Setup\ReviewRules;
use App\Models\Location;
use App\Models\User;
use App\Models\WizardProgress;
use App\Services\Reviews\ReviewGating;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class ReviewRulesScreenTest extends TestCase
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
            'current_step' => WizardStep::ReviewRules,
        ]);

        $this->actingAs($user)->get(route('setup.review-rules'))
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
            'current_step' => WizardStep::ReviewRules,
        ]);

        $this->actingAs($user)->get(route('setup.review-rules'))
            ->assertOk()
            ->assertSee('Who should we ask for reviews?');
    }

    public function test_renders_saved_rule(): void
    {
        $user = User::factory()->create();
        $business = $this->provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::set((int) $business->id);
        Tenancy::setUser($user->id);

        WizardProgress::query()->update([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'current_step' => WizardStep::ReviewRules,
        ]);

        $location = Location::query()->sole();
        app(ReviewGating::class)->gateAt($location, 5, 'user:'.$user->id, ReviewRules::DISCLOSURE_VERSION);

        // We use Livewire::test to assert the component's state, but instructions say "real GET assertOk" for each.
        // Actually, the real GET test CAN check the HTML. Wait, does Livewire output checked?
        // Let's assert the Livewire payload has rating = 5.
        $this->actingAs($user)->get(route('setup.review-rules'))
            ->assertOk()
            ->assertSee('&quot;rating&quot;:&quot;5&quot;', false);
    }
}
