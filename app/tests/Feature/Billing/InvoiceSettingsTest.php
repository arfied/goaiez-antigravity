<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\Tenancy;
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
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

use App\Modules\X121\Actions\PersonLookupAction;
use App\Modules\X165\Actions\PlanProposeAction;
use App\Modules\X199\Actions\InvoiceDraftAction;
use App\Modules\X199\Actions\TermsSetAction;

it('respects default credit limit setting', function () {
    PlatformSetting::write('invoices.default_credit_limit_cents', 100000, 'test');

    $action = app(TermsSetAction::class);
    $term = $personId = app(PersonLookupAction::class)->create($this->biz->id, ['first_name' => 'John', 'last_name' => 'Doe']);
    $term = $action->handle($this->biz->id, $personId);

    expect($term->credit_limit_cents)->toBe(100000);
});

it('respects default due days setting', function () {
    PlatformSetting::write('invoices.default_due_days', 14, 'test');

    $action = app(InvoiceDraftAction::class);
    $invoice = $personId = app(PersonLookupAction::class)->create($this->biz->id, ['first_name' => 'John', 'last_name' => 'Doe']);
    $invoice = $action->handle($this->biz->id, $personId, []);

    expect($invoice->due_date->format('Y-m-d'))->toBe(now()->addDays(14)->format('Y-m-d')); // might need to check the exact due date math
});

it('respects default plan interval setting', function () {
    PlatformSetting::write('plans.default_interval_months', 1, 'test');

    $action = app(PlanProposeAction::class);
    $plan = $action->handle($this->biz->id, 'Test', 1000);

    expect($plan->billing_interval_months)->toBe(1);
});
