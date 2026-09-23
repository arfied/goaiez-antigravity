<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
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
use App\Services\Audit\AuditEngine;

it('audit engine honours budget seconds', function () {
    PlatformSetting::write('audit.engine.budget_seconds', 45, 'test');
    $service = app(AuditEngine::class);
    $this->assertEquals(45, $service->budgetSeconds());
});
