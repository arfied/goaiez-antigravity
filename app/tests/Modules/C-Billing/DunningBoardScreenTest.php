<?php

declare(strict_types=1);

namespace Tests\Modules\CBilling;

use App\Models\User;
use App\Modules\CBilling\Models\DunningState;
use App\Modules\CBilling\Ui\DunningBoard;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DunningBoardScreenTest extends TestCase
{
    public function test_dunning_board_screen(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        $otherBiz = self::provisionTenant();

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $stateA = DunningState::create([
            'business_id' => $biz->id,
            'day_in_cycle' => 6,
            'status' => 'active',
            'ai_enabled' => true,
            'phone_answering' => true,
            'voicemail_only' => false,
        ]);

        Tenancy::set($otherBiz->id);
        $stateB = DunningState::create([
            'business_id' => $otherBiz->id,
            'day_in_cycle' => 10,
            'status' => 'active',
            'ai_enabled' => false,
            'phone_answering' => true,
            'voicemail_only' => false,
        ]);

        Tenancy::set($biz->id);
        Tenancy::forgetUser();
        Livewire::test(DunningBoard::class)
            ->assertForbidden();

        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(DunningBoard::class)
            ->assertOk()
            ->assertSee('Day 6')
            ->assertSee('day 7 human')
            ->assertDontSee('Day 10')
            ->assertDontSee('day 21 pause')
            ->assertSee('active')
            ->call('advance', $stateA->id);

        $stateA->refresh();
        $this->assertEquals(7, $stateA->day_in_cycle);

        Livewire::actingAs($owner)->test(DunningBoard::class)
            ->assertSee('Day 7')
            ->assertSee('day 21 pause with the phone answering')
            ->call('advance', 999999)
            ->assertSee("isn't in this account");
    }
}
