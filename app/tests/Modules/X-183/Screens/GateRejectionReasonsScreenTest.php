<?php

declare(strict_types=1);

namespace Tests\Modules\X183\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X183\Actions\ContentGateAction;
use App\Modules\X183\Actions\ContentWriteAction;
use App\Modules\X183\Models\ContentDraft;
use App\Modules\X183\Models\GateResult;
use App\Modules\X183\Ui\DraftReview;
use App\Modules\X183\Ui\GateRejectionReasons;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class GateRejectionReasonsScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-183.gate-rejection-reasons'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('Nothing held back')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        $priced = app(ContentWriteAction::class)->writeDraft(
            businessId: (int) $biz->id,
            title: 'Tune-up prices for October',
            bodyText: 'A full tune-up is $XX this month.',
        );
        $story = app(ContentWriteAction::class)->writeDraft(
            businessId: (int) $biz->id,
            title: 'A customer story from the Maple Street job',
            bodyText: 'The family on Maple Street had no heat for two days before we replaced the igniter.',
            isCaseStudy: true,
        );
        $clean = app(ContentWriteAction::class)->writeDraft(
            businessId: (int) $biz->id,
            title: 'Five signs your furnace needs a tune-up',
            bodyText: 'A furnace that short-cycles or smells of dust is worth a visit before the cold arrives.',
        );
        app(ContentGateAction::class)->evaluateGate((int) $biz->id, (int) $priced->id);
        app(ContentGateAction::class)->evaluateGate((int) $biz->id, (int) $story->id);
        app(ContentGateAction::class)->evaluateGate((int) $biz->id, (int) $clean->id);
        Tenancy::forget();

        $this->get(route('x-183.gate-rejection-reasons'))
            ->assertOk()
            ->assertSee('Tune-up prices for October · Draft contains placeholder sample price')
            ->assertSee('A customer story from the Maple Street job · Case study requires verified double consent before publication')
            ->assertDontSee(': Case study requires')
            ->assertDontSee('Five signs your furnace needs a tune-up')
            ->assertDontSee('Nothing held back');

        Livewire::test(GateRejectionReasons::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-183.gate-rejection-reasons.admin'))->assertOk();

        Livewire::test(GateRejectionReasons::class)->assertOk();
    }

    public function test_can_gate_rejection(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);

        Livewire::test(DraftReview::class)
            ->set('title', 'Sample Price Draft')
            ->set('bodyText', 'Price is $XX')
            ->call('submit')
            ->assertSet('error', null);

        $draft = ContentDraft::where('business_id', $biz->id)->first();

        Livewire::test(GateRejectionReasons::class)
            ->set('draftId', $draft->id)
            ->call('gateDraft')
            ->assertSet('success', 'Evaluated draft '.$draft->id.'. It was rejected: Draft contains placeholder sample price The draft row\'s own flags were updated; nothing was published anywhere external.');

        $this->assertDatabaseHas((new GateResult)->getTable(), [
            'business_id' => $biz->id,
            'draft_id' => $draft->id,
            'passed' => false,
            'rejection_reason' => 'Draft contains placeholder sample price',
        ]);

        $this->get(route('x-183.gate-rejection-reasons'))
            ->assertSee('Sample Price Draft · Draft contains placeholder sample price');
    }

    public function test_can_gate_clean_draft(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);

        Livewire::test(DraftReview::class)
            ->set('title', 'Clean Draft')
            ->set('bodyText', 'Clean body')
            ->call('submit')
            ->assertSet('error', null);

        $draft = ContentDraft::where('business_id', $biz->id)->first();

        Livewire::test(GateRejectionReasons::class)
            ->set('draftId', $draft->id)
            ->call('gateDraft')
            ->assertSet('success', 'Evaluated draft '.$draft->id.'. It passed. The draft row\'s own flags were updated; nothing was published anywhere external.');

        $this->assertDatabaseHas((new GateResult)->getTable(), [
            'business_id' => $biz->id,
            'draft_id' => $draft->id,
            'passed' => true,
            'rejection_reason' => null,
        ]);

        $this->get(route('x-183.gate-rejection-reasons'))
            ->assertDontSee('Clean Draft');
    }

    public function test_refuses_empty_draft(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(GateRejectionReasons::class)
            ->call('gateDraft')
            ->assertSet('error', 'Please select a draft.');

        $this->assertDatabaseMissing((new GateResult)->getTable(), [
            'business_id' => $biz->id,
        ]);
    }
}
