<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\CReviews\Actions\ReviewRequestAction;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\X121\Actions\PersonLookupAction;
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

it('a 40-day cadence refuses at 35 days', function () {
    PlatformSetting::write('reviews.request.cadence_window_days', 40, 'test');

    $action = app(ReviewRequestAction::class);
    $personId = app(PersonLookupAction::class)->create($this->biz->id, [
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    ReviewRequest::create([
        'business_id' => $this->biz->id,
        'customer_id' => $personId,
        'platform' => 'google',
        'status' => 'sent',
        'gbp_suspended' => false,
        'created_at' => Carbon::now()->subDays(35),
    ]);

    $result = $action->handle($this->biz->id, $personId, 'how did it go?');
    expect($result['status'])->toBe('refused')
        ->and($result['refusal_code'])->toBe('CADENCE_WINDOW_ACTIVE');
});

it('a csat of 6 with a 30-day-old job is refused when the age gate is written as 20', function () {
    PlatformSetting::write('reviews.request.low_csat_job_age_days', 20, 'test');

    $action = app(ReviewRequestAction::class);
    $personId = app(PersonLookupAction::class)->create($this->biz->id, [
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $result = $action->handle($this->biz->id, $personId, 'how did it go?', 'google', 6, 30);
    expect($result['status'])->toBe('refused')
        ->and($result['refusal_code'])->toBe('LOW_CSAT_TRIAGE');
});

it('a csat of 5 is sent when the low csat below is written as 4', function () {
    PlatformSetting::write('reviews.request.low_csat_below', 4, 'test');

    $action = app(ReviewRequestAction::class);
    $personId = app(PersonLookupAction::class)->create($this->biz->id, [
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $result = $action->handle($this->biz->id, $personId, 'how did it go?', 'google', 5, 100);
    expect($result['status'])->toBe('sent');
});
