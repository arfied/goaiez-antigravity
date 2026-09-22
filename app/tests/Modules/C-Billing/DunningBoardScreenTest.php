<?php

declare(strict_types=1);

namespace Tests\Modules\CBilling;

use App\Enums\DunningOutcome;
use App\Models\DunningAttempt;
use App\Models\User;
use App\Modules\CBilling\Ui\DunningBoard;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DunningBoardScreenTest extends TestCase
{
    public function test_dunning_board_shows_only_this_tenants_attempts(): void
    {
        $bizA = self::provisionTenant();
        $ownerA = User::findOrFail($bizA->owner_user_id);
        $bizB = self::provisionTenant();

        Tenancy::set((int) $bizB->id);
        DunningAttempt::factory()->create([
            'sequence' => 3,
            'attempt' => 1,
            'reason_code' => 'bank_unavailable',
        ]);

        Tenancy::set((int) $bizA->id);
        Tenancy::setUser($ownerA->id);
        DunningAttempt::factory()->create([
            'sequence' => 7,
            'attempt' => 2,
            'outcome' => DunningOutcome::Declined,
            'reason_code' => 'card_expired',
        ]);

        Livewire::actingAs($ownerA)->test(DunningBoard::class)
            ->assertOk()
            ->assertSee('card_expired')
            ->assertDontSee('bank_unavailable');
    }

    public function test_dunning_board_refuses_a_guest(): void
    {
        Tenancy::forgetUser();
        Livewire::test(DunningBoard::class)
            ->assertForbidden();
    }

    public function test_dunning_board_names_the_outcome_and_never_its_raw_token(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set((int) $biz->id);
        Tenancy::setUser($owner->id);

        DunningAttempt::factory()->create([
            'outcome' => DunningOutcome::Exhausted,
        ]);

        Livewire::actingAs($owner)->test(DunningBoard::class)
            ->assertOk()
            ->assertSee('Suspended, out of attempts')
            ->assertDontSee('exhausted')
            ->assertSeeHtml('bg-alert-bg');
    }

    public function test_dunning_board_is_a_record_and_offers_no_control(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set((int) $biz->id);
        Tenancy::setUser($owner->id);

        DunningAttempt::factory()->create();

        Livewire::actingAs($owner)->test(DunningBoard::class)
            ->assertOk()
            ->assertSee('This board is a record, not a control')
            ->assertDontSee('Advance');

        $this->assertFalse(method_exists(DunningBoard::class, 'advance'));
    }

    public function test_the_empty_board_no_longer_disclaims_the_real_schedule(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set((int) $biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(DunningBoard::class)
            ->assertOk()
            ->assertSee('Nothing on this board yet.')
            ->assertSee('A row appears here the first time a renewal fails')
            ->assertDontSee('retried on a separate schedule that this board does not read')
            ->assertDontSee('Declines live on the Money screen');
    }
}
