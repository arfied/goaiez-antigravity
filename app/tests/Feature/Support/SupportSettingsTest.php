<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Support\SupportDesk;
use App\Support\Tenancy;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    $this->actingAs($this->owner);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('a written subject limit trims the subject', function () {
    PlatformSetting::write('support.ticket.subject_limit', 5, 'test');

    $ticket = app(SupportDesk::class)->raise($this->owner, '123456', 'body');

    expect($ticket->subject)->toBe('12345');
});
