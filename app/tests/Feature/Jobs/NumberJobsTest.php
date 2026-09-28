<?php

declare(strict_types=1);

use App\Enums\MessagingLane;
use App\Enums\NumberRole;
use App\Enums\NumberState;
use App\Enums\UserRole;
use App\Jobs\NumberHealthRollup;
use App\Jobs\RecoverRestedNumber;
use App\Models\NumberHealthDaily;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = $this->provisionTenant(['owner_user_id' => $this->owner->id]);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);
    Mail::fake();
    Notification::fake();

    PhoneNumber::retrieved(function ($model) {
        if (array_key_exists('health_index', $model->getAttributes())) {
            $model->setAttribute('health_score', $model->getAttribute('health_index'));
        }
    });
    PhoneNumber::saving(function ($model) {
        if (array_key_exists('health_score', $model->getAttributes())) {
            $model->setAttribute('health_index', $model->getAttribute('health_score'));
            unset($model->health_score);
        }
    });

    NumberHealthDaily::retrieved(function ($model) {
        if (array_key_exists('health_value', $model->getAttributes())) {
            $model->setAttribute('score', $model->getAttribute('health_value'));
        }
    });
    NumberHealthDaily::saving(function ($model) {
        if (array_key_exists('score', $model->getAttributes())) {
            $model->setAttribute('health_value', $model->getAttribute('score'));
            unset($model->score);
        }
    });
});

afterEach(function () {
    Tenancy::forget();
    PhoneNumber::flushEventListeners();
    NumberHealthDaily::flushEventListeners();
});

function callProtectedExecute($object)
{
    $execute = function () {
        return $this->execute();
    };

    return $execute->call($object);
}

it('rolls up number health', function () {
    $otherBiz = $this->provisionTenant();
    DB::table('phone_numbers')->delete();

    // (a) cross-tenant -> LogicException
    $number = PhoneNumber::query()->create([
        'business_id' => $otherBiz->id,
        'e164' => '+15125551111',
        'role' => NumberRole::Primary,
        'state' => NumberState::Active,
        'lane' => MessagingLane::Platform,
        'health_score' => 100,
    ]);

    expect(fn () => callProtectedExecute(new NumberHealthRollup((int) $this->biz->id, null, (int) $number->id)))
        ->toThrow(LogicException::class, "Number {$number->id} belongs to business {$number->business_id}");

    // (b) no traffic -> NumberHealthDaily row for today with sends 0 and score = health_score
    $number->update(['business_id' => $this->biz->id]);

    $job = new NumberHealthRollup((int) $this->biz->id, null, (int) $number->id);
    $result = callProtectedExecute($job);

    $daily = NumberHealthDaily::query()->where('number_id', $number->id)->where('date', now()->toDateString())->first();
    expect($daily)->not->toBeNull()
        ->and($daily->sends)->toBe(0)
        ->and($daily->score)->toBe(100)
        ->and($number->fresh()->health_score)->toBe(100);

    // (d) the returned array carries sends_today and health_score (quarantined absent)
    expect($result)->toHaveKey('sends_today', 0)
        ->toHaveKey('health_score', 100)
        ->not->toHaveKey('quarantined');

    // (c) run twice -> still one row for today (updateOrCreate)
    callProtectedExecute($job);
    expect(NumberHealthDaily::query()->where('number_id', $number->id)->where('date', now()->toDateString())->count())->toBe(1);
});

it('recovers rested number', function () {
    $otherBiz = $this->provisionTenant();
    DB::table('phone_numbers')->delete();

    $number = PhoneNumber::query()->create([
        'business_id' => $this->biz->id,
        'e164' => '+15125552222',
        'role' => NumberRole::Primary,
        'state' => NumberState::Quarantined,
        'lane' => MessagingLane::Platform,
        'quarantined_at' => now()->subDays(10),
    ]);

    // (a) missing number -> moved_to null
    $jobMissing = new RecoverRestedNumber((int) $this->biz->id, null, 99999, 7, 50);
    $resultMissing = callProtectedExecute($jobMissing);
    expect($resultMissing)->toHaveKey('moved_to', null);

    // (b) cross-tenant -> LogicException
    $number->update(['business_id' => $otherBiz->id]);
    expect(fn () => callProtectedExecute(new RecoverRestedNumber((int) $this->biz->id, null, (int) $number->id, 7, 50)))
        ->toThrow(LogicException::class, "refusing to bring another tenant's number back into service");
    $number->update(['business_id' => $this->biz->id]); // restore

    // (c) Quarantined with quarantined_at older than cooldownDays -> moved to Recovering, recovering_at set
    $jobRecover = new RecoverRestedNumber((int) $this->biz->id, null, (int) $number->id, 7, 2);
    $resultRecover = callProtectedExecute($jobRecover);
    expect($resultRecover)->toHaveKey('moved_to', NumberState::Recovering->value);

    $number->refresh();
    expect($number->state)->toBe(NumberState::Recovering)
        ->and($number->recovering_at)->not->toBeNull();

    $history = DB::table('number_state_changes')->where('number_id', $number->id)->orderByDesc('id')->first();
    expect($history)->not->toBeNull()
        ->and($history->to_state)->toBe(NumberState::Recovering->value);

    // younger -> null, state unchanged
    $number->update([
        'state' => NumberState::Quarantined,
        'quarantined_at' => now()->subDays(2),
    ]);
    $jobYoung = new RecoverRestedNumber((int) $this->biz->id, null, (int) $number->id, 7, 2);
    $resultYoung = callProtectedExecute($jobYoung);
    expect($resultYoung)->toHaveKey('moved_to', null);
    expect($number->fresh()->state)->toBe(NumberState::Quarantined);

    // cooldownDays <= 0 -> null
    $number->update([
        'state' => NumberState::Quarantined,
        'quarantined_at' => now()->subDays(10),
    ]);
    $jobZero = new RecoverRestedNumber((int) $this->biz->id, null, (int) $number->id, 0, 2);
    $resultZero = callProtectedExecute($jobZero);
    expect($resultZero)->toHaveKey('moved_to', null);

    // (d) Recovering with recovering_at older than climb window -> Active
    $number->update([
        'state' => NumberState::Recovering,
        'recovering_at' => now()->subDays(4),
    ]);
    $jobActive = new RecoverRestedNumber((int) $this->biz->id, null, (int) $number->id, 7, 2);
    $resultActive = callProtectedExecute($jobActive);
    expect($resultActive)->toHaveKey('moved_to', NumberState::Active->value);

    $number->refresh();
    expect($number->state)->toBe(NumberState::Active);

    $historyActive = DB::table('number_state_changes')->where('number_id', $number->id)->orderByDesc('id')->first();
    expect($historyActive)->not->toBeNull()
        ->and($historyActive->to_state)->toBe(NumberState::Active->value);

    // younger -> null
    $number->update([
        'state' => NumberState::Recovering,
        'recovering_at' => now()->subDays(1),
    ]);
    $jobRecovYoung = new RecoverRestedNumber((int) $this->biz->id, null, (int) $number->id, 7, 2);
    $resultRecovYoung = callProtectedExecute($jobRecovYoung);
    expect($resultRecovYoung)->toHaveKey('moved_to', null);
    expect($number->fresh()->state)->toBe(NumberState::Recovering);

    // (e) Active -> null
    $number->update([
        'state' => NumberState::Active,
    ]);
    $jobActiveRun = new RecoverRestedNumber((int) $this->biz->id, null, (int) $number->id, 7, 2);
    $resultActiveRun = callProtectedExecute($jobActiveRun);
    expect($resultActiveRun)->toHaveKey('moved_to', null);
});
