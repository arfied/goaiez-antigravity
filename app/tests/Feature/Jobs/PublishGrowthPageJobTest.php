<?php

declare(strict_types=1);

use App\Contracts\CmsAdapter;
use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Enums\GrowthPageStatus;
use App\Enums\UserRole;
use App\Jobs\Content\PublishGrowthPageJob;
use App\Models\GrowthPage;
use App\Models\Location;
use App\Models\User;
use App\Services\Actuation\AdapterHealth;
use App\Services\Actuation\AdapterOutcome;
use App\Services\Actuation\FieldSupport;
use App\Services\Actuation\SiteSnapshot;
use App\Services\Billing\CreditLedger;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
    /** @var TestCase $this */
    $this->actingAs($this->owner);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);

    $this->location = Location::factory()->create([
        'business_id' => $this->biz->id,
        'website_url' => 'https://example.com',
        'website_confirmed_at' => now(),
        'about_url' => 'https://example.com/about',
        'about_url_confirmed_at' => now(),
    ]);

    // ensure entitled
    DB::table('wordpress_credentials')->insert([
        'business_id' => $this->biz->id,
        'location_id' => $this->location->id,
        'site_url' => 'https://example.com',
        'rest_root' => 'https://example.com/wp-json/',
        'username' => 'test',
        'wp_user_id' => 1,
        'wp_roles' => json_encode(['administrator']),
        'application_password' => 'test',
        'verified_at' => now(),
    ]);

    $mockAdapter = Mockery::mock(CmsAdapter::class);
    $mockAdapter->shouldReceive('health')->andReturn(new AdapterHealth(true, 'OK'));
    $mockAdapter->shouldReceive('fieldSupport')->andReturn(new FieldSupport(['title', 'meta_description', 'content'], []));
    $mockAdapter->shouldReceive('snapshot')->andReturn(SiteSnapshot::absent());
    $mockAdapter->shouldReceive('writeChangeSet')->andReturn(AdapterOutcome::ok('test'));
    $mockAdapter->shouldReceive('createPage')->andReturn(AdapterOutcome::ok('test'));
    app()->instance(CmsAdapter::class, $mockAdapter);
    app(CreditLedger::class)->record(CreditProduct::Ai, CreditKind::Grant, 1000000, 'test');
});

afterEach(function () {
    Tenancy::forget();
});

it('publishes a held page past its hold', function () {
    Http::fake([
        '*' => Http::response([], 200),
    ]);

    DB::table('platform_settings')->updateOrInsert(
        ['key' => 'actuation.enabled'],
        ['value' => json_encode(true)]
    );

    $page = GrowthPage::factory()->held()->afterMaking(function ($page) {
        unset($page->quality_score);
    })->create([
        'business_id' => $this->biz->id,
        'location_id' => $this->location->id,
        'hold_until' => now()->subHour(),
    ]);

    DB::table('content_quality_checks')->insert([
        'business_id' => $this->biz->id,
        'page_id' => $page->id,
        'passed' => true,
    ]);

    $job = new PublishGrowthPageJob((int) $this->biz->id, (int) $this->location->id, (int) $page->id);
    $job->handle();

    $page->refresh();
});

it('leaves a page inside its hold alone', function () {
    Http::fake([
        '*' => Http::response([], 200),
    ]);

    DB::table('platform_settings')->updateOrInsert(
        ['key' => 'actuation.enabled'],
        ['value' => json_encode(true)]
    );

    $page = GrowthPage::factory()->held()->afterMaking(function ($page) {
        unset($page->quality_score);
    })->create([
        'business_id' => $this->biz->id,
        'location_id' => $this->location->id,
        'hold_until' => now()->addHour(),
    ]);

    DB::table('content_quality_checks')->insert([
        'business_id' => $this->biz->id,
        'page_id' => $page->id,
        'passed' => true,
    ]);

    $job = new PublishGrowthPageJob((int) $this->biz->id, (int) $this->location->id, (int) $page->id);
    $job->handle();

    $page->refresh();
    expect($page->status->value)->toBe(GrowthPageStatus::Held->value);
});

it('is dispatched by the command', function () { /** @var TestCase $this */
    Queue::fake();

    $page = GrowthPage::factory()->held()->afterMaking(function ($page) {
        unset($page->quality_score);
    })->create([
        'business_id' => $this->biz->id,
        'location_id' => $this->location->id,
        'hold_until' => now()->subHour(),
    ]);

    $this->artisan('content:release-holds', ['--business' => (string) $this->biz->id]);

    Queue::assertPushed(PublishGrowthPageJob::class, fn ($j) => $j->growthPageId === $page->id);
});
