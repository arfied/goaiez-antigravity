<?php

use App\Enums\CredentialChangeAction;
use App\Enums\UserRole;
use App\Models\CredentialChange;
use App\Models\PlatformSetting;
use App\Models\RegistryChange;
use App\Models\User;
use App\Services\Config\CredentialStore;
use App\Services\Config\DefaultsRegistry;
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

it('limits defaults registry history', function () {
    PlatformSetting::write('settings.history_limit', 5, 'test');

    for ($i = 0; $i < 10; $i++) {
        RegistryChange::create([
            'setting_key' => 'test.key',
            'value_before' => $i,
            'value_after' => $i + 1,
            'actor' => 'test',
            'created_at' => now(),
        ]);
    }

    expect(app(DefaultsRegistry::class)->historyFor('test.key'))->toHaveCount(5);
});

it('limits credential store history', function () {
    PlatformSetting::write('credentials.history_limit', 5, 'test');

    for ($i = 0; $i < 10; $i++) {
        CredentialChange::create([
            'credential_key' => 'test.key',
            'action' => CredentialChangeAction::Set,
            'actor' => 'test',
            'created_at' => now(),
        ]);
    }

    expect(app(CredentialStore::class)->historyFor('test.key'))->toHaveCount(5);
});
