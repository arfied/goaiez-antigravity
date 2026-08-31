<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\PasswordResetLink;
use App\Services\Mail\PlatformMailer;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use LogicException;
use SensitiveParameter;

/**
 * A person. Deliberately NOT tenant-owned.
 *
 * On the ArchitectureTest allowlist, and it has to be: a user exists before a
 * business does — `businesses.owner_user_id` points here — so a tenant scope on
 * this model would make signup unresolvable. The tenant boundary reaches users
 * through `businesses`, guarded by the SELECT-only `owner_lookup` policy.
 *
 * FOUR WAYS IN, and the model carries all four so none of them is special:
 * password (Fortify), passkey (Fortify + laravel/passkeys), SSO (Socialite), and
 * magic link (first-party). `password` is nullable because three of the four
 * never set one.
 *
 * @property-read int $id
 * @property string $email
 * @property string $name
 * @property UserRole $role
 * @property ?string $password
 * @property ?string $avatar_url
 * @property ?string $locale
 * @property ?string $timezone
 * @property ?Carbon $last_login_at
 * @property ?Carbon $email_verified_at
 */
#[Fillable(['name', 'email', 'password', 'avatar_url', 'locale', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    // Fortify's traits, not laravel/passkeys' directly: Fortify wraps the
    // package and configures it from config/fortify.php, and using the package's
    // own trait would work while reading its config from the wrong file.
    use PasskeyAuthenticatable;
    use TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * The businesses this person owns.
     *
     * Readable only with `app.user_id` established — the `owner_lookup` policy —
     * or with that business already the tenant in context. Outside both, this
     * returns nothing rather than everything, which is the correct failure.
     *
     * @return HasMany<Business, $this>
     */
    public function ownedBusinesses(): HasMany
    {
        return $this->hasMany(Business::class, 'owner_user_id');
    }

    /**
     * Whether this person holds one of the given roles.
     *
     * The whole of "roles" without a permissions package. Gates and policies
     * express intent in terms of what a role may *do* (see UserRole), and this
     * is the only place that compares role identity.
     */
    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * The password reset email, routed through the one thing that meters it.
     *
     * ⛔ **THE FRAMEWORK'S VERSION OF THIS METHOD IS THE ONE PATH IN THIS
     * APPLICATION THAT SENT EMAIL WITHOUT PASSING
     * {@see PlatformMailer}, AND BOTH CHOKEPOINT LINTS WERE STRUCTURALLY
     * BLIND TO IT** (9600). `Illuminate\Auth\Passwords\CanResetPassword`
     * — inherited through `Illuminate\Foundation\Auth\User`, which this
     * class extends — implements it as a bare
     * `$this->notify(new ResetPasswordNotification($token))`. That call is in
     * `vendor/`, and `MailTest`'s *"only the platform mailer sends email"*
     * walks `app_path()`, so the shortest path around the chokepoint was one
     * this codebase never wrote and could not see. `POST /forgot-password` is
     * unauthenticated, so it was also the **only** such path a stranger could
     * fire.
     *
     * What it skipped, in order: `assertDeliverable()`, the per-mailer
     * 24-hour sending ceiling `MailQuota` holds — whose entire design is that
     * an unset row makes the `smtp` mailer send nothing at all (4603, 4604) —
     * `MailSendRate::pace()` (4648), the CAN-SPAM classification, the
     * `platform_mail_sends` meter, `EmailCredits`, and
     * `DeliverPlatformMail::failed()`'s bell.
     *
     * ⚠️ **THE CEILING ROW IS NAMED BY PROPERTY RATHER THAN BY KEY ON
     * PURPOSE.** `MailTest`'s *"the sending ceiling is read through MailQuota
     * and nowhere else"* matches the literal **anywhere in a file, docblocks
     * included**, and it is right to: a key spelled out by hand goes stale the
     * next time its shape moves. `MailQuota` is the one place it is written.
     *
     * ⚠️ **`send()` AND NOT `deliverNow()`, DELIBERATELY.** `send()` queues and
     * cannot throw at its caller (702, 709, 9500) — this method is called from
     * an unauthenticated endpoint that already answers *"does this address
     * have an account"* by validation error (9505), and a synchronous throw
     * would add a second oracle that survives closing the first.
     *
     * ⚠️ **THE URL IS BUILT HERE BECAUSE THIS IS WHERE THE USER IS.**
     * `deliverNow()` routes an `AnonymousNotifiable`, so a notification that
     * called `getEmailForPasswordReset()` on what it was handed would fatal.
     * The shape is `Illuminate\Auth\Notifications\ResetPassword::resetUrl()`'s
     * — same route name, same two parameters — because
     * `resources/views/auth/reset-password.blade.php` reads the address out of
     * the query string.
     *
     * ⚠️ **THE PARAMETER CANNOT TAKE A `string` TYPE HINT.**
     * `Illuminate\Contracts\Auth\CanResetPassword` declares it untyped, and
     * PHP's parameter types are contravariant — narrowing one in an override
     * is a fatal error, not a style choice. The type is stated in the docblock
     * and read by Larastan instead.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[SensitiveParameter] $token): void
    {
        $minutes = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60,
        );

        app(PlatformMailer::class)->send(
            $this->getEmailForPasswordReset(),
            new PasswordResetLink(
                url(route('password.reset', [
                    'token' => $token,
                    'email' => $this->getEmailForPasswordReset(),
                ], false)),
                $minutes,
            ),
        );
    }

    /**
     * Refused, loudly, rather than sent around the chokepoint.
     *
     * ⛔ **THIS IS THE SECOND METHOD THIS CLASS INHERITS THAT SENDS EMAIL FROM
     * `vendor/`** — `Illuminate\Auth\MustVerifyEmail`, also flattened in
     * through `Illuminate\Foundation\Auth\User` — and it is refused instead
     * of routed, which is a judgement rather than an omission (9603).
     *
     * **Email verification is deliberately off.** `Features::emailVerification()`
     * is commented out in `config/fortify.php`, which is what registers
     * `verification.notice`, `verification.verify` and `verification.send`; and
     * this class does not implement `Illuminate\Contracts\Auth\MustVerifyEmail`,
     * so `UpdateUserProfileInformation`'s `instanceof` guard is false and
     * nothing reaches this method today. ⛔ **A first-party verification
     * notification built now could not even render**: the framework's builds its
     * URL from `route('verification.verify')`, and that route does not exist
     * while the feature is off. So the honest options were a notification with
     * no reachable caller and no reviewed copy, or a refusal that names what to
     * build — and this codebase has recorded the first shape often enough to
     * pick the second.
     *
     * ⚠️ **WHAT THIS CONVERTS, AND WHAT IT DOES NOT.** Turning the feature on
     * without writing the notification used to produce mail that worked and
     * bypassed every gate; it now produces a 500 on an **authenticated**
     * endpoint, naming the class to write. That is `PlatformMailer`'s own
     * argument for refusing an unclassified notification, applied one layer
     * out: the refusal is what forces the conversation.
     *
     * @throws LogicException always
     */
    public function sendEmailVerificationNotification(): void
    {
        throw new LogicException(
            'Email verification mail has no sender on this platform. The framework would send it '
            .'straight from Illuminate\Auth\MustVerifyEmail, which bypasses PlatformMailer and '
            .'therefore the sending ceiling, the pacer, the meter and the CAN-SPAM classification. '
            .'Enabling Features::emailVerification() means writing an App\Notifications class that '
            .'implements ClassifiesUnderCanSpam and overriding this method the way '
            .'sendPasswordResetNotification() above is overridden (9603).'
        );
    }
}
