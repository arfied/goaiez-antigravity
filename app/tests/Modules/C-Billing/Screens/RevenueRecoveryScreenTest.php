<?php

declare(strict_types=1);

namespace Tests\Modules\CBilling\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CBilling\Models\DunningState;
use App\Modules\CBilling\Ui\RevenueRecovery;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RevenueRecoveryScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-billing.revenue-recovery'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No ladder on this screen yet.');

        Tenancy::setUser($owner->id);
        DunningState::create([
            'business_id' => $biz->id,
            'day_in_cycle' => 9,
            'status' => 'warning',
            'ai_enabled' => true,
            'phone_answering' => true,
            'voicemail_only' => false,
        ]);
        Tenancy::forget();

        $this->get(route('c-billing.revenue-recovery'))
            ->assertOk()
            ->assertSee('Day 9 of 21')
            ->assertDontSee('No ladder on this screen yet.');

        Livewire::test(RevenueRecovery::class)->assertOk();
    }
}