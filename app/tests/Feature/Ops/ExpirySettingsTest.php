<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\X121\Actions\PersonLookupAction;
use App\Modules\X153\Actions\AlertSendAction;
use App\Modules\X181\Actions\QaTicketCreateAction;
use App\Modules\X202\Domain\ApprovalDeskEngine;
use App\Support\Tenancy;
use Carbon\Carbon;
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

it('an alert claim written as 5 minutes expires at 5 minutes', function () {
    PlatformSetting::write('alerts.claim.expiry_minutes', 5, 'test');

    $action = app(AlertSendAction::class);
    $result = $action->handle($this->biz->id, 'Title', 'Body');

    $expiry = Carbon::parse($result['claim_expires_at']);
    expect($expiry->toDateTimeString())->toBe(now()->addMinutes(5)->toDateTimeString());
});

it('an approval written as 10 hours expires at 10 hours', function () {
    PlatformSetting::write('approvals.expiry_hours', 10, 'test');

    $engine = app(ApprovalDeskEngine::class);
    $result = $engine->enqueue($this->biz->id, 'type', 'subj', ['foo' => 'bar']);

    $item = $result['item'];
    expect($item->expires_at->toDateTimeString())->toBe(now()->addHours(10)->toDateTimeString());
});

it('a QA SLA written as 5 hours is due at 5 hours', function () {
    PlatformSetting::write('qa.ticket.sla_hours', 5, 'test');

    $action = app(QaTicketCreateAction::class);
    $personId = app(PersonLookupAction::class)->create($this->biz->id, ['first_name' => 'John']);
    $ticket = $action->handle($this->biz->id, $personId, 'subject');

    expect($ticket->sla_due_at->toDateTimeString())->toBe(now()->addHours(5)->toDateTimeString());
});
