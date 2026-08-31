<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\Auth\FailedSignIns;
use App\Support\HashedIp;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Laravel\Fortify\Fortify;

/**
 * Record that a credential check failed — the other half of
 * {@see RecordSuccessfulLogin}, missing until 2026-08-26 (9860–9879).
 *
 * ⛔ **NOTHING IN THIS APPLICATION LISTENED FOR THIS EVENT, AND THE CONSEQUENCE
 * WAS MEASURED RATHER THAN ASSERTED.** 9723 drove 240 POSTs at `/login` across
 * 40 addresses from one source in one minute: 200 accepted, and **no row, no
 * counter, no alert and no log line anywhere.** A successful credential-stuffing
 * run left nothing behind at all.
 *
 * ⛔ **SYNCHRONOUS, AND `ShouldQueue` HERE WOULD BE A DEFECT RATHER THAN A
 * TUNING CHOICE.** `Illuminate\Auth\Events\Failed::$credentials` carries the
 * **plaintext password the attempter typed** — read the framework's own
 * constructor, where the parameter is `#[\SensitiveParameter]`, which redacts
 * stack traces and nothing else. A queued listener serialises the event into
 * `jobs`, which `CLAUDE.md`'s Schema row exempts from row-level security and
 * which no erasure, no tenant predicate and no crypto-shred reaches
 * (`QueuePayloadTest` is the census). **Queueing this would write every wrong
 * password on the platform into the least protected table in the schema** — and
 * on a spray, at the attacker's chosen rate. The work is one upsert.
 *
 * ⛔ **AND THE PASSWORD IS NEVER READ HERE.** Only the username key is taken
 * from `$credentials`, by name, through `Fortify::username()`.
 *
 * ⚠️ **ON THE EVENT RATHER THAN IN A CONTROLLER, FOR
 * `RecordSuccessfulLogin`'s REASON POINTED THE OTHER WAY** (661): the failure
 * is thrown from inside Fortify's login pipeline, where there is no controller
 * of ours to edit.
 *
 * ⚠️ **WHICH FAILURES THIS ACTUALLY SEES, CHECKED AGAINST `vendor/` RATHER THAN
 * ASSUMED.** Exactly three places construct this event: `SessionGuard::
 * fireFailedEvent()`, `Fortify\Actions\AttemptToAuthenticate::fireFailedEvent()`
 * and `Fortify\Actions\RedirectIfTwoFactorAuthenticatable::fireFailedEvent()`.
 * With two-factor enabled in `config/fortify.php` the third runs **first** in
 * the pipeline and throws, so a bad password reaches here once and from there.
 * ⛔ **A WRONG SECOND FACTOR AND A REFUSED PASSKEY FIRE NOTHING OF THIS KIND** —
 * `TwoFactorAuthenticatedSessionController` raises Fortify's own
 * `TwoFactorAuthenticationFailed`, and `laravel/passkeys` raises neither. Both
 * are a person who has already proved a password, which is a different signal
 * with a different remedy; **this register is about credentials and says so.**
 * ⛔ **`Illuminate\Auth\Events\Lockout` FIRES NOWHERE IN THIS APPLICATION AT
 * ALL** — its only source is `EnsureLoginIsNotThrottled`, which Fortify drops
 * from the pipeline whenever `fortify.limiters.login` is set, and it is. A
 * listener on it would have been 256's vacuity.
 */
final class RecordFailedSignIn
{
    public function __construct(private readonly FailedSignIns $register) {}

    public function handle(Failed $event): void
    {
        /** @var array<string, mixed> $credentials */
        $credentials = (array) $event->credentials;

        $address = $credentials[Fortify::username()] ?? '';

        // ⚠️ TOTAL RATHER THAN GUARDED. Fortify's own `LoginRequest` requires
        // the username, so an empty one cannot reach here through the route —
        // but a listener that throws on a shape it did not expect turns a 422
        // into a 500 on the busiest door in the application, and the fingerprint
        // of the empty string is a perfectly consistent bucket.
        $address = is_scalar($address) ? (string) $address : '';

        $this->register->record(
            address: $address,
            // ⛔ NULL IS A REAL ANSWER AND IS STORED AS ONE (`HashedIp`). It also
            // collapses to one value platform-wide behind an unconfigured CDN,
            // which `trustProxies` still is (336) — the table's own column
            // comment carries that and the report leads with the address tried
            // rather than the source because of it.
            ipHash: HashedIp::of(request()),
            // ⛔ WHETHER THE SUBMITTED ADDRESS NAMED AN ACCOUNT OF OURS, TAKEN
            // FROM THE EVENT AND NOT LOOKED UP AGAIN. `retrieveByCredentials()`
            // has already answered it, and re-asking would be a second query
            // that can disagree with the first.
            //
            // ⚠️ THIS IS NOT AN ORACLE AND MUST NOT BECOME ONE. 9600–9639 spent
            // a slice making the *responses* on this surface indistinguishable;
            // nothing here reaches a response, and the attacker was the party
            // that supplied the address.
            accountExisted: $event->user !== null,
            at: CarbonImmutable::now(),
        );
    }
}
