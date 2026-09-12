<?php

declare(strict_types=1);

namespace Tests\Modules\X185\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X185\Actions\CampaignCreateAction;
use App\Modules\X185\Ui\DigestLine;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DigestLineScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-185.digest-line'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No sequences yet')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        app(CampaignCreateAction::class)->createSequence(
            businessId: (int) $biz->id,
            name: 'Spring tune-up reminders',
        );
        Tenancy::forget();

        $this->get(route('x-185.digest-line'))
            ->assertOk()
            ->assertSee('Spring tune-up reminders · running')
            ->assertDontSee('No sequences yet');

        Livewire::test(DigestLine::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-185.digest-line.admin'))->assertOk();

        Livewire::test(DigestLine::class)->assertOk();
    }
}
