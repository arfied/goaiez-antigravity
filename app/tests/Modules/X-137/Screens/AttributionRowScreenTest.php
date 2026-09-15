<?php

declare(strict_types=1);

namespace Tests\Modules\X137\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X137\Models\CallToken;
use App\Modules\X137\Ui\AttributionRow;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class AttributionRowScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-137.attribution-row'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No active DNI tokens allocated.');

        Tenancy::setUser($owner->id);
        CallToken::create([
            'business_id' => $biz->id,
            'visitor_session_token' => 'visitor-4471',
            'allocated_number' => '+15125554471',
            'campaign_source' => 'distinctive_campaign_4471',
            'whisper_text' => 'Call from distinctive_campaign_4471',
            'expires_at' => now()->addMinutes(30),
            'status' => 'active',
        ]);
        Tenancy::forget();

        $this->get(route('x-137.attribution-row'))
            ->assertOk()
            ->assertSee('+15125554471')
            ->assertSee('distinctive_campaign_4471')
            ->assertSee('[active]')
            ->assertDontSee('No active DNI tokens allocated.');

        Livewire::test(AttributionRow::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-137.attribution-row.admin'))->assertOk();

        Livewire::test(AttributionRow::class)->assertOk();
    }
}
