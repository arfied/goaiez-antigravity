<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\X120\Actions\CardExpiringScanAction;
use App\Modules\X120\Models\CardToken;
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

it('a card expiring in 35 days alerts when the window is written as 40', function () {
    PlatformSetting::write('billing.card.expiring_warning_days', 40, 'test');

    $action = app(CardExpiringScanAction::class);

    // Create a card that expires in 35 days
    $now = Carbon::now();
    $expDate = $now->copy()->addDays(35);

    CardToken::create([
        'business_id' => $this->biz->id,

        'gateway_customer_id' => 'cus_123',
        'gateway_payment_method_id' => 'tok_123',
        'last_four' => '4242',
        'brand' => 'visa',
        'exp_month' => $expDate->month,
        'exp_year' => $expDate->year,
        'alert_sent' => false,
    ]);

    // Calculate the difference back to target exactly 35 days from the end of month
    // CardExpiringScanAction calculates days from end of month
    $endOfMonth = Carbon::createFromDate($expDate->year, $expDate->month, 1)->endOfMonth();
    $targetNow = $endOfMonth->copy()->subDays(35);

    $result = $action->scan($this->biz->id, $targetNow);
    expect($result['alerted_count'])->toBe(1);
});
