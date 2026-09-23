<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ActivityFeedItem;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Activity\ActivityFeed;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    /** @var TestCase $this */
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    $this->location = $this->biz->locations()->first();
    $this->location->forceFill([
        'website_url' => 'https://example.test',
        'website_confirmed_at' => now(),
    ])->save();
    Mail::fake();
    Notification::fake();
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('activity feed honours per page', function () {
    PlatformSetting::write('activity.feed.per_page', 5, 'test');

    // Create 10 items
    ActivityFeedItem::factory()->count(10)->create([
        'business_id' => $this->biz->id,
        'location_id' => $this->location->id,
    ]);

    $service = app(ActivityFeed::class);
    $page = $service->page();

    $this->assertCount(5, $page->items());
});
