<?php

declare(strict_types=1);

namespace Tests\Modules\CBilling\Screens;

use App\Enums\UserRole;
use App\Models\DunningAttempt;
use App\Models\User;
use App\Modules\CBilling\Ui\DunningBoard;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DunningBoardScreenTest extends TestCase
{
    /** @test */
    public function test_screen_renders_for_tenant_with_attempts(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-billing.dunning-board'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nothing on this board yet.');

        Tenancy::setUser($owner->id);
        Tenancy::set((int) $biz->id);
        DunningAttempt::factory()->create([
            'reason_code' => 'card_expired',
        ]);
        Tenancy::forget();

        $this->get(route('c-billing.dunning-board'))
            ->assertOk()
            ->assertSee('card_expired')
            ->assertDontSee('Nothing on this board yet.');

        Livewire::test(DunningBoard::class)->assertOk();
    }
}
