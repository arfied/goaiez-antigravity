<?php

declare(strict_types=1);

namespace Tests\Modules\X201;

use App\Models\User;
use App\Modules\X201\Actions\DisputeRecordAction;
use App\Modules\X201\Domain\DisputeDefenseEngine;
use App\Modules\X201\Models\Dispute;
use App\Modules\X201\Models\DisputeEvidence;
use App\Modules\X201\Ui\DisputeCard;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class DisputeCardScreenTest extends TestCase
{
    public function test_dispute_card_takes_a_note_into_the_bundle_seals_it_on_submission_and_never_refunds()
    {
        $bizB = self::provisionTenant();
        Tenancy::set($bizB->id);
        app(DisputeRecordAction::class)->handle($bizB->id, 777, 12000);

        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $open = app(DisputeRecordAction::class)->handle($biz->id, 902, 85000, 'unrecognized_transaction');
        $lost = app(DisputeRecordAction::class)->handle($biz->id, 903, 5000);
        app(DisputeDefenseEngine::class)->recordOutcome($biz->id, $lost->id, 'lost');

        Tenancy::forgetUser();
        Livewire::test(DisputeCard::class)->assertForbidden();
        Tenancy::setUser($owner->id);

        $screen = Livewire::actingAs($owner)->test(DisputeCard::class)
            ->assertOk()
            ->assertSee('Invoice #902')
            ->assertSee('850.00')
            ->assertSee('unrecognized_transaction')
            ->assertSee('0 evidence items')
            ->assertSee('chargeback webhook sets it, and no such webhook reaches this checkout')
            ->assertSee('Invoice #903')
            ->assertSee('lost')
            ->assertDontSee('Invoice #777')
            ->assertDontSeeHtml('wire:click="refund')
            ->assertSeeHtml('wire:submit="addNote('.$open->id.')"')
            ->call('approve', $open->id)
            ->assertSee('Compile the evidence first')
            ->call('addNote', $open->id)
            ->assertSee('Write the note first')
            ->set('note.'.$open->id, 'Tech on site two hours, customer signed the estimate')
            ->call('addNote', $open->id)
            ->assertSee('Added to the bundle: 2 evidence items for invoice #902')
            ->assertSee('note: Tech on site two hours')
            ->assertSee('compiled')
            ->set('note.'.$open->id, 'Photos of the finished job are in the thread')
            ->call('addNote', $open->id)
            ->assertSee('Added to the bundle: 3 evidence items for invoice #902')
            ->assertSeeHtml('wire:click="approve('.$open->id.')"')
            ->call('approve', $open->id)
            ->assertSee('Defence for invoice #902 is sealed and recorded here')
            ->assertSee('Nothing was sent: filing it waits on the gateway chargeback contract')
            ->assertSee('sealed, not sent')
            ->assertDontSee('submitted')
            ->assertSee('Nothing was sent to any gateway')
            ->assertSee('decision yourself from the dispute queue when it reaches you')
            ->set('note.'.$open->id, 'One more thing')
            ->call('addNote', $open->id)
            ->assertSee('already submitted')
            ->call('addNote', 999999)
            ->assertSee("isn't in this account");

        $this->assertSame('submitted', $open->fresh()->status, 'a sealed bundle keeps its status');
        $this->assertSame(3, DisputeEvidence::where('dispute_id', $open->id)->count(), 'the refused note wrote nothing');
        Tenancy::set($bizB->id);
        $this->assertSame(1, Dispute::where('business_id', $bizB->id)->count());
    }

    public function test_the_dispute_card_empty_state_names_what_it_waits_on()
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(DisputeCard::class)
            ->assertOk()
            ->assertSee('A dispute opens here when the gateway chargeback webhook reaches this app; no such webhook is received in this checkout, so no bundle exists to add to yet.')
            ->assertSee('You compile the bundle from the dispute queue and add what only you know.');
    }

    public function test_a_dispute_carrying_a_deadline_says_nothing_watches_that_clock()
    {
        Carbon::setTestNow('2026-09-10 09:00:00');

        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $dispute = app(DisputeRecordAction::class)->handle($biz->id, 905, 42000, 'product_not_received');
        Dispute::whereKey($dispute->id)->update(['deadline_at' => Carbon::now()->addDays(10)]);

        Livewire::actingAs($owner)->test(DisputeCard::class)
            ->assertOk()
            ->assertSee('Deadline: 20 Sep 2026.')
            ->assertSee('Nothing watches this clock yet, so no one is raised as it nears.')
            ->assertDontSee('no such webhook reaches this checkout');

        Carbon::setTestNow();
    }
}
