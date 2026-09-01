<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Auth\SecondFactor;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

/*
|--------------------------------------------------------------------------
| POST /two-factor-challenge must never 500
|--------------------------------------------------------------------------
|
| Two production requests on 2026-08-31 did: a stored secret that decrypted to
| `dummysecret` (05:42, Google2FA refused it), and a challenge session posted
| after the user's secret had been cleared (05:45, `decrypt(null)`). The
| response to those was to stub `SecondFactor::required()` to `false`, which
| turned mandatory staff 2FA off platform-wide. These tests pin the guarded
| paths in App\Http\Requests\Auth\TwoFactorLoginRequest so the rule can stay on.
|
*/

function staffUser(array $attributes = []): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin] + $attributes);
}

test('a challenge for a user whose secret was removed sends them back to sign in', function (): void {
    $user = staffUser();

    $this->withSession(['login.id' => $user->id, 'login.remember' => false])
        ->post(route('two-factor.login.store'), ['code' => '123456'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email')
        ->assertSessionMissing('login.id');

    $this->assertGuest();
});

test('a stored secret that Google2FA refuses is reported as unusable', function (): void {
    $user = staffUser();
    $user->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt('dummysecret'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->withSession(['login.id' => $user->id])
        ->from(route('two-factor.login'))
        ->post(route('two-factor.login.store'), ['code' => '916612'])
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('a stored secret that cannot be decrypted is reported as unusable', function (): void {
    $user = staffUser();
    $user->forceFill([
        'two_factor_secret' => 'not-a-ciphertext',
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->withSession(['login.id' => $user->id])
        ->from(route('two-factor.login'))
        ->post(route('two-factor.login.store'), ['code' => '123456'])
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('a wrong code against a usable secret is still just a wrong code', function (): void {
    $user = staffUser();
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
    $user->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->withSession(['login.id' => $user->id])
        ->from(route('two-factor.login'))
        ->post(route('two-factor.login.store'), ['code' => '000000'])
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('a valid code against a usable secret completes the sign-in', function (): void {
    $user = staffUser();
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
    $user->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->withSession(['login.id' => $user->id, 'login.remember' => false])
        ->post(route('two-factor.login.store'), ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

test('the setup screen offers a fresh start when the enrolment key cannot be read', function (): void {
    $user = staffUser();
    $user->forceFill(['two_factor_secret' => 'not-a-ciphertext'])->save();

    $this->actingAs($user)
        ->get(route('two-factor.setup'))
        ->assertOk()
        ->assertSee('name="force"', false)
        ->assertDontSee('Finish turning it on');
});

test('the setup screen still shows the QR code for an enrolment in progress', function (): void {
    $user = staffUser();
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
    $user->forceFill(['two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret)])->save();

    $this->actingAs($user)
        ->get(route('two-factor.setup'))
        ->assertOk()
        ->assertSee('Finish turning it on')
        ->assertSee($secret)
        ->assertDontSee('name="force"', false);
});

test('staff must hold a second factor and tenants need not', function (): void {
    expect(SecondFactor::required(staffUser()))->toBeTrue();
    expect(SecondFactor::required(User::factory()->create(['role' => UserRole::CsReadonly])))->toBeTrue();
    expect(SecondFactor::required(User::factory()->create(['role' => UserRole::Owner])))->toBeFalse();
});

test('usable() tells a working secret from a dead one', function (): void {
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();

    expect(SecondFactor::usable(staffUser()))->toBeFalse();
    expect(SecondFactor::usable(staffUser(['two_factor_secret' => 'not-a-ciphertext'])))->toBeFalse();
    expect(SecondFactor::usable(staffUser(['two_factor_secret' => Fortify::currentEncrypter()->encrypt('dummysecret')])))->toBeFalse();
    expect(SecondFactor::usable(staffUser(['two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret)])))->toBeTrue();
});
