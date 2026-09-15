<?php

declare(strict_types=1);

namespace Tests\Modules\CReviews\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CReviews\Ui\LossAlerts;
use App\Modules\X181\Models\QaTicket;
use Livewire\Livewire;
use Tests\TestCase;

class LossAlertsScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-reviews.loss-alerts'))->assertOk();

        Livewire::test(LossAlerts::class)->assertOk();
    }

    public function test_sample_mode_says_actions_are_off(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $component = Livewire::test(LossAlerts::class)
            ->assertDontSee('actions are off')
            ->call('toggleSample')
            ->assertSee('SAMPLE DATA')
            ->assertSee('actions are off')
            ->call('resolveAndAlert', 1, 'sample note')
            ->assertSee('actions are off');

        $this->assertSame(0, QaTicket::where('business_id', $biz->id)->count());

        $component->call('toggleSample')
            ->assertDontSee('actions are off');
    }
}
