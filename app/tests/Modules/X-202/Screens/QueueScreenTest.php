<?php

declare(strict_types=1);

namespace Tests\Modules\X202\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X202\Models\ApprovalItem;
use App\Modules\X202\Ui\Queue;
use App\Support\Tenancy;
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
        Tenancy::setTenant($biz->id);

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
        Tenancy::setTenant($biz->id);

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
        Tenancy::setTenant($biz->id);

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
}
