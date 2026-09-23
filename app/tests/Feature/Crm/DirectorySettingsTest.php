<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Crm\CustomerDirectory;
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

it('limits customer directory page size', function () {
    PlatformSetting::write('crm.directory.per_page', 5, 'test');
    Customer::factory()->count(10)->create();
    expect(app(CustomerDirectory::class)->page()->items())->toHaveCount(5);
});
