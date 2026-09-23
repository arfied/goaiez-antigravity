<?php

declare(strict_types=1);

use App\Enums\CrmTaskStatus;
use App\Enums\UserRole;
use App\Livewire\Account\FollowUps;
use App\Models\CrmTask;
use App\Models\Customer;
use App\Models\User;
use App\Services\Crm\CrmTasks;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

uses(RefreshesTenantDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
    $this->actingAs($this->owner);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
});

it('shows the follow ups console with a due-now task', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15 10:00:00'));
    $customer = Customer::factory()->create();
    $task = CrmTask::factory()->create([
        'customer_id' => $customer->id,
        'title' => 'Important due task right now',
        'due_at' => now()->startOfDay(),
    ]);

    $this->get(route('account.follow-ups'))
        ->assertOk()
        ->assertSee('Your account', false)
        ->assertDontSee('Internal Platform Console')
        ->assertSee('Important due task right now');
});

it('refuses no-tenant user on get and mount', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::forget();

    $this->get(route('account.follow-ups'))->assertForbidden();

    Livewire::actingAs($staff)->test(FollowUps::class)->assertForbidden();
});

it('partitions tasks into dueNow, later, and snoozed buckets', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15 10:00:00'));
    $customer = Customer::factory()->create();

    $dueNow = CrmTask::factory()->create([
        'customer_id' => $customer->id,
        'title' => 'Task Due Now',
        'due_at' => now(),
        'snoozed_until' => null,
    ]);

    $later = CrmTask::factory()->create([
        'customer_id' => $customer->id,
        'title' => 'Task Due Later',
        'due_at' => now()->addDays(3),
        'snoozed_until' => null,
    ]);

    $snoozed = CrmTask::factory()->create([
        'customer_id' => $customer->id,
        'title' => 'Task Snoozed',
        'due_at' => now(),
        'snoozed_until' => now()->addDays(2),
    ]);

    Livewire::test(FollowUps::class)
        ->assertViewHas('dueNow', function ($collection) use ($dueNow) {
            return $collection->count() === 1 && $collection->first()->id === $dueNow->id;
        })
        ->assertViewHas('later', function ($collection) use ($later) {
            return $collection->count() === 1 && $collection->first()->id === $later->id;
        })
        ->assertViewHas('snoozed', function ($collection) use ($snoozed) {
            return $collection->count() === 1 && $collection->first()->id === $snoozed->id;
        })
        ->assertSee('Task Due Now')
        ->assertSee('Task Due Later')
        ->assertSee('Task Snoozed');
});

it('completes a task', function () {
    $customer = Customer::factory()->create();
    $task = CrmTask::factory()->create([
        'customer_id' => $customer->id,
        'status' => CrmTaskStatus::Open,
    ]);

    Livewire::test(FollowUps::class)
        ->call('complete', app(CrmTasks::class), $task->id)
        ->assertHasNoErrors();

    $task->refresh();
    expect($task->status)->toBe(CrmTaskStatus::Done)
        ->and($task->done_at)->not->toBeNull();
});

it('throws ModelNotFoundException when completing unknown task', function () {
    Livewire::test(FollowUps::class)
        ->call('complete', app(CrmTasks::class), 99999);
})->throws(ModelNotFoundException::class);

it('snoozes a task for a day', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15 10:00:00'));
    $customer = Customer::factory()->create();
    $task = CrmTask::factory()->create([
        'customer_id' => $customer->id,
    ]);

    Livewire::test(FollowUps::class)
        ->call('snooze', app(CrmTasks::class), $task->id, 'day')
        ->assertHasNoErrors();

    $task->refresh();
    expect($task->snoozed_until->toDateTimeString())->toBe(now()->addDay()->toDateTimeString());
});

it('snoozes a task for a week', function () {
    Carbon::setTestNow(Carbon::parse('2024-01-15 10:00:00'));
    $customer = Customer::factory()->create();
    $task = CrmTask::factory()->create([
        'customer_id' => $customer->id,
    ]);

    Livewire::test(FollowUps::class)
        ->call('snooze', app(CrmTasks::class), $task->id, 'week')
        ->assertHasNoErrors();

    $task->refresh();
    expect($task->snoozed_until->toDateTimeString())->toBe(now()->addWeek()->toDateTimeString());
});

it('refuses to snooze for a month with 422', function () {
    $customer = Customer::factory()->create();
    $task = CrmTask::factory()->create([
        'customer_id' => $customer->id,
    ]);

    Livewire::test(FollowUps::class)
        ->call('snooze', app(CrmTasks::class), $task->id, 'month')
        ->assertStatus(422);
});

it('refuses to snooze when user has no tenant (403)', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $customer = Customer::factory()->create();
    $task = CrmTask::factory()->create([
        'customer_id' => $customer->id,
    ]);

    $component = Livewire::test(FollowUps::class);

    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::forget();

    $component->call('snooze', app(CrmTasks::class), $task->id, 'day')
        ->assertForbidden();
});
