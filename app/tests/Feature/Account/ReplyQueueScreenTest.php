<?php

declare(strict_types=1);

use App\Enums\ReplyStatus;
use App\Enums\TenantLinkKind;
use App\Enums\UserRole;
use App\Jobs\Reviews\PostReplyJob;
use App\Livewire\Account\ReplyQueue;
use App\Models\Location;
use App\Models\Reply;
use App\Models\Review;
use App\Models\TenantLinkRecord;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

uses(RefreshesTenantDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
    $this->actingAs($this->owner);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);

    Mail::fake();
    Notification::fake();
    Bus::fake([PostReplyJob::class]);

    $this->location = Location::factory()->forBusiness($this->biz->id)->create();
});

test('real GET route renders', function () {
    $review = Review::factory()->create(['location_id' => $this->location->id]);
    $reply = Reply::factory()->create([
        'review_id' => $review->id,
        'status' => ReplyStatus::Suggested,
        'text' => 'This is a pending reply',
    ]);

    $this->get(route('account.replies'))
        ->assertOk()
        ->assertSee('Your account', false)
        ->assertDontSee('Internal Platform Console')
        ->assertSee('This is a pending reply');
});

test('mount requires tenant and preloads drafts', function () {
    $review = Review::factory()->create(['location_id' => $this->location->id]);
    $pending = Reply::factory()->create([
        'review_id' => $review->id,
        'status' => ReplyStatus::Suggested,
        'text' => 'Pending text',
    ]);
    $awaiting = Reply::factory()->create([
        'review_id' => $review->id,
        'status' => ReplyStatus::Approved,
        'text' => 'Awaiting text',
    ]);

    Livewire::test(ReplyQueue::class)
        ->assertSet('drafts.'.$pending->id, 'Pending text')
        ->assertSet('drafts.'.$awaiting->id, 'Awaiting text');

    Tenancy::forgetAll();

    Livewire::test(ReplyQueue::class)->assertForbidden();
});

test('approve control', function () {
    $review = Review::factory()->create(['location_id' => $this->location->id]);

    // (a) unknown id
    Livewire::test(ReplyQueue::class)
        ->call('approve', 999)
        ->assertNotFound();

    // (b) edited draft
    $pending = Reply::factory()->create([
        'review_id' => $review->id,
        'status' => ReplyStatus::Suggested,
        'text' => 'Old text',
    ]);
    Livewire::test(ReplyQueue::class)
        ->set('drafts.'.$pending->id, 'New text')
        ->call('approve', $pending->id);

    expect($pending->fresh()->status)->toBe(ReplyStatus::Approved)
        ->and($pending->fresh()->text)->toBe('New text');
    Bus::assertDispatched(PostReplyJob::class, function ($job) use ($pending) {
        return $job->replyId === $pending->id;
    });

    // (c) empty draft
    $pendingEmpty = Reply::factory()->create([
        'review_id' => $review->id,
        'status' => ReplyStatus::Suggested,
        'text' => 'To be emptied',
    ]);
    Livewire::test(ReplyQueue::class)
        ->set('drafts.'.$pendingEmpty->id, '   ')
        ->call('approve', $pendingEmpty->id);

    expect($pendingEmpty->fresh()->status)->toBe(ReplyStatus::Suggested);

    // (d) posted reply
    $posted = Reply::factory()->posted()->create([
        'review_id' => $review->id,
    ]);
    Livewire::test(ReplyQueue::class)
        ->call('approve', $posted->id);
    expect($posted->fresh()->text)->toBe($posted->text);

    // (e) already-approved reply
    $approved = Reply::factory()->create([
        'review_id' => $review->id,
        'status' => ReplyStatus::Approved,
        'text' => 'Already approved',
    ]);
    Livewire::test(ReplyQueue::class)
        ->call('approve', $approved->id);
    expect($approved->fresh()->status)->toBe(ReplyStatus::Approved);
});

test('skip control', function () {
    $review = Review::factory()->create(['location_id' => $this->location->id]);

    // (a) pending reply
    $pending = Reply::factory()->create([
        'review_id' => $review->id,
        'status' => ReplyStatus::Suggested,
        'text' => 'Skip me',
    ]);

    Livewire::test(ReplyQueue::class)
        ->call('skip', $pending->id)
        ->assertNotSet('drafts.'.$pending->id, 'Skip me');

    expect(Reply::find($pending->id))->toBeNull();

    // (b) posted reply
    $posted = Reply::factory()->posted()->create([
        'review_id' => $review->id,
    ]);
    Livewire::test(ReplyQueue::class)
        ->call('skip', $posted->id);

    expect(Reply::find($posted->id))->not->toBeNull();

    // (c) unknown id
    Livewire::test(ReplyQueue::class)
        ->call('skip', 999)
        ->assertNotFound();
});

test('insertBookingLink control', function () {
    $review = Review::factory()->create(['location_id' => $this->location->id]);
    $pending = Reply::factory()->create([
        'review_id' => $review->id,
        'status' => ReplyStatus::Suggested,
        'text' => 'Hello',
    ]);

    // (a) no usable link
    Livewire::test(ReplyQueue::class)
        ->call('insertBookingLink', $pending->id)
        ->assertSet('drafts.'.$pending->id, 'Hello');

    // (b) usable link
    TenantLinkRecord::factory()->create([
        'kind' => TenantLinkKind::Booking,
        'destination' => 'https://example.com',
        'label' => 'Book here',
    ]);

    $component = Livewire::test(ReplyQueue::class)
        ->call('insertBookingLink', $pending->id)
        ->assertSet('drafts.'.$pending->id, "Hello\n\nBook here: https://example.com");

    // (c) pressed twice
    $component->call('insertBookingLink', $pending->id)
        ->assertSet('drafts.'.$pending->id, "Hello\n\nBook here: https://example.com");

    // Empty draft case
    $emptyReply = Reply::factory()->create([
        'review_id' => $review->id,
        'status' => ReplyStatus::Suggested,
        'text' => '',
    ]);
    Livewire::test(ReplyQueue::class)
        ->call('insertBookingLink', $emptyReply->id)
        ->assertSet('drafts.'.$emptyReply->id, 'Book here: https://example.com');
});
