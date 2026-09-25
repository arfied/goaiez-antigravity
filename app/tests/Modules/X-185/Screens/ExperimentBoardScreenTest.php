<?php

declare(strict_types=1);

namespace Tests\Modules\X185\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X185\Actions\PackSeedAction;
use App\Modules\X185\Models\ContentPack;
use App\Modules\X185\Ui\ExperimentBoard;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ExperimentBoardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-185.experiment-board'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No content pack has been tested for you yet')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        app(PackSeedAction::class)->promotePack((int) $biz->id, 'Spring Tune-Up Pack', 'Beat the rush — book your spring tune-up', 112);
        ContentPack::create([
            'business_id' => $biz->id,
            'pack_name' => 'Winter Draft',
            'industry' => 'hvac',
            'label_text' => 'Winter draft label',
            'is_promoted' => false,
            'fleet_sample_size' => 3,
        ]);
        Tenancy::forget();

        $this->get(route('x-185.experiment-board'))
            ->assertOk()
            ->assertSee('Spring Tune-Up Pack')
            ->assertSee('proven on')
            ->assertSee('Winter Draft')
            ->assertSee('still testing')
            ->assertDontSee('No content pack has been tested');

        Livewire::test(ExperimentBoard::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-185.experiment-board.admin'))->assertOk();

        Livewire::test(ExperimentBoard::class)->assertOk();
    }

    public function test_control_writes_and_clears_empty_states(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        Livewire::test(ExperimentBoard::class)
            ->set('packName', 'Summer Sale')
            ->set('labelText', 'Summer sale is here')
            ->set('fleetSampleSize', 150)
            ->set('industry', 'hvac')
            ->call('promote')
            ->assertSet('error', null)
            ->assertSet('success', 'Promoted pack Summer Sale. This feeds the experiment lists; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas('content_packs', [
            'business_id' => $biz->id,
            'pack_name' => 'Summer Sale',
            'label_text' => 'Summer sale is here',
            'fleet_sample_size' => 150,
            'industry' => 'hvac',
        ]);

        $this->get(route('x-185.experiment-board'))
            ->assertDontSee('No content pack has been tested for you yet')
            ->assertSee('Summer Sale');
    }

    public function test_control_refuses_invalid_input(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        Livewire::test(ExperimentBoard::class)
            ->set('packName', '')
            ->call('promote')
            ->assertSet('error', 'Pack Name cannot be empty.');

        Livewire::test(ExperimentBoard::class)
            ->set('packName', 'Small Sample Pack')
            ->set('fleetSampleSize', 50)
            ->call('promote')
            ->assertSet('error', 'Content pack promotion rejected: fleet sample size must be above minimum (TEST ANCHOR & G12-30)');

        $this->assertDatabaseMissing('content_packs', [
            'business_id' => $biz->id,
            'pack_name' => 'Small Sample Pack',
        ]);
    }
    public function test_the_tenant_id_cannot_be_overwritten_from_the_browser(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        Livewire::test(ExperimentBoard::class)->set('businessId', 999999);
    }
}
