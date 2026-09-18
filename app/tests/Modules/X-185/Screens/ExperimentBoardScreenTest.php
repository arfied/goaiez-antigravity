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
}
