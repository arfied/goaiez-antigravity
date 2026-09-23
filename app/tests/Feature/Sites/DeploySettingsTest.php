<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Models\EdgeZone;
use App\Modules\X163\Models\PriceBookItem;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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

it('a deploy with 25 price-list items carries only the written cap', function () {
    PlatformSetting::write('sites.deploy.pricebook_items_max', 15, 'test');

    for ($i = 1; $i <= 25; $i++) {
        PriceBookItem::create([
            'business_id' => $this->biz->id,
            'service_name' => "Service $i",
            'price_cents' => 1000,
            'is_confirmed' => true,
            'is_sample' => false,
        ]);
    }

    // We need to create a zone to pass edgeZoneId
    $zone = EdgeZone::create([
        'business_id' => $this->biz->id,
        'zone_id' => 'z1',
        'domain_name' => 'test.example.com',
        'has_valid_ssl' => true,
    ]);

    $action = app(EdgeDeployAction::class);
    $result = $action->handle($this->biz->id, $zone->id, 100, 1500, null, null, null);

    expect($result['status'])->toBe('deployed');

    $hash = $result['deploy_hash'];
    $html = Storage::disk('local')->get("sites/{$hash}.html");

    $matches = substr_count($html, 'class="offer-item"');
    expect($matches)->toBe(15);
});
