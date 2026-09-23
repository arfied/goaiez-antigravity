<?php

declare(strict_types=1);

namespace Tests\Modules\X202\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X202\Domain\ApprovalDeskEngine;
use App\Modules\X202\Models\ApprovalItem;
use App\Modules\X202\Ui\Queue;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class QueueScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-202.queue'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nothing is waiting on you.');

        Tenancy::setUser($owner->id);
        ApprovalItem::create([
            'business_id' => $biz->id,
            'item_type' => 'distinctive_refund_4631',
            'subject' => 'Distinctive refund 4631',
            'payload' => ['amount_cents' => 4631],
            'expires_at' => now()->addDay(),
        ]);
        Tenancy::forget();

        $this->get(route('x-202.queue'))
            ->assertOk()
            ->assertSee('Distinctive refund 4631')
            ->assertSee('distinctive_refund_4631')
            ->assertDontSee('Nothing is waiting on you.');

        Livewire::test(Queue::class)->assertOk();
    }

    public function test_can_enqueue_approval_item(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        $this->get(route('x-202.queue'))
            ->assertOk()
            ->assertSee('Approvals');

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Test Subject Enqueue')
            ->set('note', 'Just a note')
            ->call('enqueueItem')
            ->assertSet('error', null)
            ->assertSet('success', 'Enqueued an item waiting for a decision; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas((new ApprovalItem)->getTable(), [
            'business_id' => $biz->id,
            'item_type' => 'test_type',
            'subject' => 'Test Subject Enqueue',
            'status' => 'pending',
        ]);

        $this->get(route('x-202.queue'))
            ->assertSee('Test Subject Enqueue');

        $this->get(route('x-202.audit-export'))
            ->assertDontSee('Test Subject Enqueue');
    }

    public function test_refuses_empty_payload_in_queue(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Test Subject Empty')
            ->set('note', null)
            ->call('enqueueItem')
            ->assertSet('error', 'Please fill out all required fields.')
            ->assertSet('success', null);

        $this->assertDatabaseMissing((new ApprovalItem)->getTable(), [
            'business_id' => $biz->id,
            'subject' => 'Test Subject Empty',
        ]);
    }

    public function test_refuses_error_only_payload_in_queue(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Test Subject Error')
            ->set('note', 'foo')
            ->set('isErrorOnly', true)
            ->call('enqueueItem')
            ->assertSet('error', 'Cannot enqueue bare errors or empty payloads to approval desk')
            ->assertSet('success', null);

        $this->assertDatabaseMissing((new ApprovalItem)->getTable(), [
            'business_id' => $biz->id,
            'subject' => 'Test Subject Error',
        ]);
    }

    public function test_can_escalate_a_pending_item(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Refund above the desk limit')
            ->set('note', 'Just a note')
            ->call('enqueueItem');

        $item = ApprovalItem::where('business_id', $biz->id)->firstOrFail();

        $component = Livewire::test(Queue::class)
            ->set('escalateReason', 'Need more eyes on this')
            ->call('escalateItem', $item->id)
            ->assertSet('error', null);

        $success = $component->get('success');
        $this->assertStringContainsString('stays on this queue, now marked escalated', (string) $success);
        $this->assertStringContainsString('Nobody is notified', (string) $success);

        $this->assertDatabaseHas((new ApprovalItem)->getTable(), [
            'id' => $item->id,
            'status' => 'escalated',
        ]);
    }

    public function test_an_escalated_item_stays_on_the_queue(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Refund above the desk limit')
            ->set('note', 'Just a note')
            ->call('enqueueItem');

        $item = ApprovalItem::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(Queue::class)
            ->set('escalateReason', 'Need more eyes on this')
            ->call('escalateItem', $item->id);

        // Before the fix this GET showed the empty state
        $this->get(route('x-202.queue'))
            ->assertSee('Refund above the desk limit')
            ->assertSee('escalated')
            ->assertDontSee('Nothing is waiting on you.');
    }

    public function test_an_expired_item_is_visible_too(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Refund above the desk limit')
            ->set('note', 'Just a note')
            ->call('enqueueItem');

        $item = ApprovalItem::where('business_id', $biz->id)->firstOrFail();
        $item->update(['expires_at' => now()->subDay()]);

        app(ApprovalDeskEngine::class)->processExpirations($biz->id);

        // processExpirations's docblock promises expired items "appear on a human's screen, not in a void" — this test is the first thing that makes that true.
        $this->get(route('x-202.queue'))
            ->assertSee('Refund above the desk limit')
            ->assertSee('expired');
    }

    public function test_escalating_without_a_reason_is_refused(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Refund above the desk limit')
            ->set('note', 'Just a note')
            ->call('enqueueItem');

        $item = ApprovalItem::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(Queue::class)
            ->set('escalateReason', '   ')
            ->call('escalateItem', $item->id)
            ->assertSet('error', 'Say why this needs to go higher before escalating.');

        $this->assertDatabaseHas((new ApprovalItem)->getTable(), [
            'id' => $item->id,
            'status' => 'pending',
        ]);
    }

    public function test_escalating_another_tenants_item_is_refused(): void
    {
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);
        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);
        $this->actingAs($ownerB);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Refund above the desk limit')
            ->set('note', 'Just a note')
            ->call('enqueueItem');

        $itemB = ApprovalItem::where('business_id', $bizB->id)->firstOrFail();

        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);
        Tenancy::setUser($ownerA->id);
        Tenancy::set((int) $bizA->id);
        $this->actingAs($ownerA);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Queue::class)
            ->set('escalateReason', 'Escalate')
            ->call('escalateItem', $itemB->id);
    }

    public function test_can_approve_an_item(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Refund over the desk limit')
            ->set('note', 'Just a note')
            ->call('enqueueItem');

        $item = ApprovalItem::where('business_id', $biz->id)->firstOrFail();

        $component = Livewire::test(Queue::class)
            ->set('decisionComment', 'looks good')
            ->call('approveItem', $item->id)
            ->assertSet('error', null);

        $success = $component->get('success');
        $this->assertStringContainsString('is approved and has moved to the approval history', (string) $success);
        $this->assertStringContainsString('it does not carry out what was asked', (string) $success);

        $this->assertDatabaseHas((new ApprovalItem)->getTable(), [
            'id' => $item->id,
            'status' => 'approved',
        ]);
        $this->assertNotNull(ApprovalItem::find($item->id)->decided_at);
    }

    public function test_a_decided_item_leaves_the_queue_and_appears_in_the_history(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set((int) $biz->id);
        $this->actingAs($owner);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Refund over the desk limit')
            ->set('note', 'Just a note')
            ->call('enqueueItem');

        $item = ApprovalItem::where('business_id', $biz->id)->firstOrFail();

        Tenancy::forget();
        $this->get(route('x-202.queue'))
            ->assertSee('Refund over the desk limit');

        $this->get(route('x-202.audit-export'))
            ->assertSee('No decisions yet.');

        Tenancy::set((int) $biz->id);
        Livewire::test(Queue::class)
            ->set('decisionComment', 'approved it')
            ->call('approveItem', $item->id);

        Tenancy::forget();
        $this->get(route('x-202.queue'))
            ->assertDontSee('Refund over the desk limit');

        $this->get(route('x-202.audit-export'))
            ->assertSee('Refund over the desk limit')
            ->assertSee('approved')
            ->assertDontSee('No decisions yet.');
    }

    public function test_can_reject_an_item(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Refund over the desk limit')
            ->set('note', 'Just a note')
            ->call('enqueueItem');

        $item = ApprovalItem::where('business_id', $biz->id)->firstOrFail();

        $component = Livewire::test(Queue::class)
            ->set('decisionComment', 'looks bad')
            ->call('rejectItem', $item->id)
            ->assertSet('error', null);

        $success = $component->get('success');
        $this->assertStringContainsString('is rejected and has moved to the approval history', (string) $success);
        $this->assertStringContainsString('it does not carry out what was asked', (string) $success);

        $this->assertDatabaseHas((new ApprovalItem)->getTable(), [
            'id' => $item->id,
            'status' => 'rejected',
        ]);
        $this->assertNotNull(ApprovalItem::find($item->id)->decided_at);
    }

    public function test_the_comment_is_recorded_and_shown_in_the_history(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Refund over the desk limit')
            ->set('note', 'Just a note')
            ->call('enqueueItem');

        $item = ApprovalItem::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(Queue::class)
            ->set('decisionComment', 'Distinctive comment 82746')
            ->call('approveItem', $item->id);

        Tenancy::forget();
        $this->get(route('x-202.audit-export'))
            ->assertSee('Distinctive comment 82746');
    }

    public function test_deciding_an_already_decided_item_is_refused(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Refund over the desk limit')
            ->set('note', 'Just a note')
            ->call('enqueueItem');

        $item = ApprovalItem::where('business_id', $biz->id)->firstOrFail();

        Livewire::test(Queue::class)
            ->set('decisionComment', 'looks good')
            ->call('approveItem', $item->id)
            ->assertSet('error', null);

        $this->assertDatabaseHas((new ApprovalItem)->getTable(), [
            'id' => $item->id,
            'status' => 'approved',
        ]);

        $item = $item->fresh();

        $component = Livewire::test(Queue::class)
            ->set('decisionComment', 'looks good again')
            ->call('approveItem', $item->id);

        $error = $component->get('error');
        $this->assertNotNull($error);
        $this->assertStringContainsString('That item was already decided on '.$item->decided_at->toDateString(), (string) $error);

        $this->assertDatabaseHas((new ApprovalItem)->getTable(), [
            'id' => $item->id,
            'status' => 'approved',
        ]);
    }

    public function test_approving_another_tenants_item_is_refused(): void
    {
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);
        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);
        $this->actingAs($ownerB);

        Livewire::test(Queue::class)
            ->set('itemType', 'test_type')
            ->set('subject', 'Refund over the desk limit')
            ->set('note', 'Just a note')
            ->call('enqueueItem');

        $itemB = ApprovalItem::where('business_id', $bizB->id)->firstOrFail();

        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);
        Tenancy::setUser($ownerA->id);
        Tenancy::set((int) $bizA->id);
        $this->actingAs($ownerA);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Queue::class)
            ->set('decisionComment', 'looks good')
            ->call('approveItem', $itemB->id);
    }
}
