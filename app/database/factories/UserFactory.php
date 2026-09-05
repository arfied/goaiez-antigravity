<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * The parent declares a Larastan-narrowed return type; inheriting it keeps
     * the two in sync rather than restating a weaker one here.
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => Str::lower(Str::random(8)).'.'.fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Owner,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A user holding a specific role.
     *
     * Named rather than passing `['role' => …]` at each call site so the
     * authorization tests read as what they assert: `User::factory()->role(
     * UserRole::Staff)` rather than an attribute nobody notices.
     */
    public function role(UserRole $role): static
    {
        return $this->state(fn (array $attributes): array => ['role' => $role]);
    }

    /**
     * Someone who signed in through SSO, a passkey, or a magic link.
     *
     * Three of the four login methods never establish a password, so the
     * password-less user is the normal case rather than an edge one — and a
     * factory that always sets one hides every place the code assumes it.
     */
    public function passwordless(): static
    {
        return $this->state(fn (array $attributes): array => ['password' => null]);
    }

    /**
     * Someone who has finished enrolling a second factor (`28` §9.1).
     *
     * ⚠️ **Deliberately not the default, and not folded into `role()`.** Every
     * internal account must hold a factor before it may use the console, so it
     * is tempting to have the factory hand one to every staff role — and that
     * would make `RequiresTwoFactor` untestable everywhere except in the file
     * written for it, because no test would ever produce the state it refuses.
     * A regression would then be invisible in 1,400 tests. Staff fixtures say
     * this out loud instead, which is also what makes each one read as *"and
     * this account has complied"*.
     *
     * The columns are written directly rather than by driving the enrolment
     * screen: what the fixture needs is the finished state, and driving three
     * requests to reach it would make every test that uses one partly a test of
     * that screen.
     *
     * `Fortify::currentEncrypter()`, never the bare `encrypt()` helper — the
     * same reason `SecondFactor` gives for the read side.
     */
    public function withSecondFactor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt(
                (new Google2FA)->generateSecretKey(),
            ),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(
                (string) json_encode(['aaaaaaaaaa-bbbbbbbbbb']),
            ),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
