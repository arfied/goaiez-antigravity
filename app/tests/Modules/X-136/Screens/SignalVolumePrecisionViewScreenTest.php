<?php

declare(strict_types=1);

namespace Tests\Modules\X136\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X136\Actions\SignalScoreAction;
use App\Modules\X136\Ui\SignalVolumePrecisionView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class SignalVolumePrecisionViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-136.signal-volume-precision'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console');

        Livewire::test(SignalVolumePrecisionView::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-136.signal-volume-precision.admin'))->assertOk();

        Livewire::test(SignalVolumePrecisionView::class)->assertOk();
    }

    public function test_volume_screen_counts_a_high_intent_signal(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        app(SignalScoreAction::class)->recordAndScore(
            $biz->id,
            'acme-roofing',
            'pricing_visit',
            [],
            90.0
        );

        Tenancy::forget();

        $this->actingAs($owner);
        $this->get(route('x-136.signal-volume-precision'))
            ->assertOk()
            ->assertSee('Volume: 1 total | 1 high-intent')
            ->assertDontSee('No signal stats yet');
    }
}
