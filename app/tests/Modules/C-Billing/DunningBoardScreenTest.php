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
            'status' => 'warning',
            'ai_enabled' => true,
            'phone_answering' => true,
            'voicemail_only' => false,
        ]);

        Tenancy::set($otherBiz->id);
        $stateB = DunningState::create([
            'business_id' => $otherBiz->id,
            'day_in_cycle' => 10,
            'status' => 'banner',
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
            ->assertSee('warning stage')
            ->call('advance', $stateA->id);

        $stateA->refresh();
        $this->assertEquals(7, $stateA->day_in_cycle);

        Livewire::actingAs($owner)->test(DunningBoard::class)
            ->assertSee('Day 7')
            ->assertSee('day 21 pause with the phone answering')
            ->call('advance', 999999)
            ->assertSee("isn't in this account");
    }

    public function test_the_dunning_board_says_it_reads_only_its_own_ladder(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(DunningBoard::class)
            ->assertOk()
            ->assertSee('retried on a separate schedule that this board does not read')
            ->assertSee('Nothing on this board yet.')
            ->assertSee('Declines live on the Money screen')
            ->assertDontSee('Nothing in this checkout puts an account on the dunning ladder')
            ->assertDontSee('No account is in dunning');
    }

    public function test_dunning_board_names_the_final_stage_and_never_its_raw_token(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        DunningState::create([
            'business_id' => $biz->id,
            'day_in_cycle' => 21,
            'status' => 'ai_off_voicemail_only',
            'ai_enabled' => false,
            'phone_answering' => true,
            'voicemail_only' => true,
        ]);

        Livewire::actingAs($owner)->test(DunningBoard::class)
            ->assertOk()
            ->assertSee('final stage')
            ->assertDontSee('ai_off_voicemail_only')
            ->assertSeeHtml('bg-alert-bg');
    }

    public function test_the_dunning_board_calls_its_next_step_a_plan_and_says_nothing_performs_it(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        DunningState::create([
            'business_id' => $biz->id,
            'day_in_cycle' => 10,
            'status' => 'banner',
            'ai_enabled' => false,
            'phone_answering' => true,
            'voicemail_only' => false,
        ]);

        Livewire::actingAs($owner)->test(DunningBoard::class)
            ->assertOk()
            ->assertSee('Next step (planned)')
            ->assertSee('day 21 pause with the phone answering')
            ->assertSee('nothing in this checkout switches a phone, an agent or a number when a day is reached');
    }
}
