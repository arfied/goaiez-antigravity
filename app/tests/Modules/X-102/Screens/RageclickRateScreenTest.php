<?php

declare(strict_types=1);

namespace Tests\Modules\X102\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X102\Models\ChatSession;
use App\Modules\X102\Ui\RageclickRate;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RageclickRateScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-102.rageclick-rate'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No rage clicks recorded.');

        Tenancy::setUser($owner->id);
        ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => 'sess_distinctive_4490',
            'status' => 'active',
            'rage_clicks_count' => 7,
            'is_ai_capped' => false,
        ]);
        Tenancy::forget();

        $this->get(route('x-102.rageclick-rate'))
            ->assertOk()
            ->assertSee('sess_distinctive_4490: 7 rage clicks')
            ->assertSee('[active]')
            ->assertDontSee('No rage clicks recorded.');

        Livewire::test(RageclickRate::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-102.rageclick-rate.admin'))->assertOk();

        Livewire::test(RageclickRate::class)->assertOk();
    }
}
