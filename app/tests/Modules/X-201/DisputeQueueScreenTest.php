<?php

declare(strict_types=1);

namespace Tests\Modules\X201;

use App\Models\User;
use App\Modules\X201\Actions\DisputeRecordAction;
use App\Modules\X201\Domain\DisputeDefenseEngine;
use App\Modules\X201\Models\Dispute;
use App\Modules\X201\Models\DisputeEvidence;
use App\Modules\X201\Models\DisputeOutcome;
use App\Modules\X201\Ui\DisputeQueue;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DisputeQueueScreenTest extends TestCase
{
    public function test_dispute_queue_compiles_with_a_note_refuses_an_empty_submission_and_records_the_outcome()
    {
        $bizB = self::provisionTenant();
        Tenancy::set($bizB->id);
        app(DisputeRecordAction::class)->handle($bizB->id, 777, 12000);

        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $open = app(DisputeRecordAction::class)->handle($biz->id, 902, 85000, 'unrecognized_transaction');
        $open2 = app(DisputeRecordAction::class)->handle($biz->id, 904, 1000);
        $won = app(DisputeRecordAction::class)->handle($biz->id, 903, 5000);
        app(DisputeDefenseEngine::class)->recordOutcome($biz->id, $won->id, 'won');

        Tenancy::forgetUser();
        Livewire::test(DisputeQueue::class)->assertForbidden();
        Tenancy::setUser($owner->id);

        $screen = Livewire::actingAs($owner)->test(DisputeQueue::class)
            ->assertOk()
            ->assertSee('One account at a time')
            ->assertSee('Invoice #902')
            ->assertSee('850.00')
            ->assertSee('unrecognized_transaction')
            ->assertSee('opened')
            ->assertSee('0 evidence items')
            ->assertSee('no signature yet')
            ->assertDontSee('Invoice #903')
            ->assertDontSee('Invoice #777')
            ->assertSeeHtml('wire:submit="compile('.$open->id.')"')
            ->call('submit', $open->id)
            ->assertSee('Compile the evidence first');

        $this->assertSame('opened', $open->fresh()->status, 'a refused submission leaves the row untouched');

        $screen->set('note.'.$open->id, 'Customer signed on site, tech on the job 2 hours')
            ->call('compile', $open->id)
            ->assertSee('Compiled 2 evidence items for invoice #902')
            ->assertSee('compiled')
            ->assertSee('note: Customer signed on site')
            ->assertSeeHtml('wire:click="submit('.$open->id.')"')
            ->call('submit', $open->id)
            ->assertSee('Defence for invoice #902 is sealed and recorded here')
            ->assertSee('Nothing was sent: filing it waits on the gateway chargeback contract')
            ->assertSee('submitted')
            ->assertSeeHtml('wire:click="outcome('.$open->id.', \'lost\')"')
            ->call('outcome', $open->id, 'lost')
            ->assertSee('Recorded: invoice #902 lost.')
            ->assertSee('no commission has been taken back')
            ->assertDontSee('Commission clawed back.')
            ->assertSee('Invoice #904')
            ->assertDontSee('Invoice #902')
            ->call('outcome', $open2->id, 'won')
            ->assertSee('A dispute opens here when the gateway chargeback webhook reaches this app, and no such webhook is received in this checkout, so nothing opens one and nothing compiles on a schedule')
            ->call('outcome', 999999, 'won')
            ->assertSee("isn't in this account")
            ->call('outcome', $won->id, 'maybe')
            ->assertSee('is not an outcome');

        $this->assertSame('lost', $open->fresh()->status);
        $this->assertSame(2, DisputeEvidence::where('dispute_id', $open->id)->count());
        $this->assertTrue(DisputeOutcome::where('dispute_id', $open->id)->firstOrFail()->commission_clawback_triggered);
        Tenancy::set($bizB->id);
        $this->assertSame(1, Dispute::where('business_id', $bizB->id)->count());
    }
}
