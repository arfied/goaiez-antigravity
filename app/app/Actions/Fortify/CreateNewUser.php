<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Enums\TermsAcceptanceMethod;
use App\Models\User;
use App\Services\Legal\SignupTerms;
use App\Services\Legal\TermsAcceptances;
use App\Services\TenantProvisioner;
use App\Support\HashedIp;
use App\Support\RegistrationRateLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

final class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(
        private readonly TenantProvisioner $tenants,
        private readonly SignupTerms $terms,
        private readonly TermsAcceptances $acceptances,
    ) {}

    /**
     * The request this registration arrived on, for its client address and
     * nothing else.
     *
     * ⛔ **RESOLVED PER CALL, AND CONSTRUCTOR INJECTION IS A REAL BUG HERE
     * RATHER THAN A STYLE PREFERENCE.** `Fortify::createUsersUsing()` binds this
     * action as a **singleton**, so the container builds it once and caches it
     * for the life of the process — a constructor-held `Request` is therefore
     * whichever request happened to register *first*, forever. The velocity
     * limit keys on that address, so every later registration in the same
     * process would be counted against the first visitor's bucket: one office
     * exhausting its three would lock out the entire internet, and one
     * fraudster's bucket would be spent by strangers.
     *
     * ⚠️ **IT WAS WRITTEN THAT WAY FIRST AND A TEST CAUGHT IT** — "the throttle
     * is per network, so one busy office does not lock out the internet", which
     * is the only test in the file that registers from two different addresses.
     * Under one request per process (production) the stale instance is
     * indistinguishable from a correct one, so nothing else would ever have
     * shown it. `Illuminate\Http\Request` is rebound on the container by the
     * HTTP kernel on every request, so the helper always answers the live one.
     *
     * ⛔ **NOTHING READS THE BODY OFF IT.** The validated `$input` is the only
     * source of what the person typed; a second reader of the raw request is a
     * second, unvalidated idea of what was submitted. `proof()` below reads the
     * request's **transport metadata** — the URL it arrived on, the hashed
     * client address and the user agent — which is what `29` §2 rule 7 requires
     * of a record made on a page we serve, and none of which is submitted input.
     */
    private function request(): Request
    {
        return request();
    }

    /**
     * Validate, create, and provision a newly registered user.
     *
     * PROVISIONING HAPPENS HERE, IN THE SAME TRANSACTION, rather than on the
     * `Registered` event. The listener is the more idiomatic hook and it is the
     * wrong one, because a user and their tenant are not independently useful:
     * ResolveTenant resolves the tenant by finding a Business owned by the user,
     * so a user created without one has no tenant, and every tenant-scoped query
     * on their behalf fails closed — including every path that might repair it.
     * A half-registered account here is not degraded, it is unusable and
     * unrecoverable. Either both rows exist or neither does.
     *
     * If the transaction rolls back, the Postgres session variable Tenancy sets
     * may outlive it — `SET` is not transactional. That is safe rather than
     * merely tolerable: ResolveTenant calls Tenancy::forgetAll() unconditionally
     * as the first thing it does on every web request, precisely so a stale
     * value can never be read by the request that follows.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        // ⛔ **NO ACCOUNT IS OPENED ON TERMS NOBODY HAS PUBLISHED** — see
        // `SignupTerms`, and note that this is a real gate rather than a
        // theoretical one: nothing seeds a *published* legal document, so an
        // admin publishes counsel's text before the first customer arrives.
        //
        // ⛔ **FIRST, BEFORE THE VALIDATOR, AND IT SAT AFTER IT UNTIL 2026-08-26
        // (9881). THE OLD SENTENCE IS KEPT BECAUSE IT WAS THE EVIDENCE:** *"AFTER
        // VALIDATION AND BEFORE THE VELOCITY LIMIT, and both halves are
        // deliberate. After, because the person's own input is theirs to correct
        // first."* ⚠️ **That argument is about a population this arm never
        // serves.** It only decides anything when `isAvailable()` is FALSE — an
        // install that will refuse every registration whatever is typed — so
        // "theirs to correct first" offered a correction that could not work,
        // and charged an unknowable price for it: `Rule::unique(User::class)`
        // ran first and answered **"The email has already been taken."** to a
        // stranger, on an install that has never opened an account.
        //
        // ⛔ **MEASURED THROUGH THE REAL DOOR RATHER THAN REASONED ABOUT**, on a
        // running server with no published terms: a known address answered *"The
        // email has already been taken."* and an unknown one answered *"We
        // cannot open new accounts just now."* — two distinguishable sentences,
        // rendered on `/start` **directly above** that page's own *"We are not
        // opening new accounts at this moment."* ⚠️ **The form is withheld in
        // that state and `curl` never needed the form**; a CSRF token from
        // `/login` shares the session and reaches this action.
        //
        // ⚠️ **THIS IS THE ORDERING ONLY, AND IT DELIBERATELY DOES NOT CLOSE
        // 9628(a).** Whether an OPEN install should keep saying "you already
        // have an account" is the owner's trade and is untouched: on every
        // install that can actually open an account, this arm is false and the
        // validator runs exactly as it did. What changes is that an install
        // which cannot open one no longer answers the question at all.
        //
        // ⚠️ **STILL BEFORE THE VELOCITY LIMIT, AND THAT HALF OF THE OLD
        // ARGUMENT SURVIVES INTACT** — this refusal is ours rather than theirs,
        // and spending one of their three registration attempts on our
        // unpublished paperwork would lock them out for an hour over something
        // they cannot fix.
        //
        // The write path asks again regardless — `TermsAcceptances::record()`
        // resolves the same versions and throws — so this is the courtesy, not
        // the boundary (398: an outer guard that refuses first leaves the inner
        // one unfalsifiable, so both are driven directly in the tests).
        if (! $this->terms->isAvailable()) {
            throw ValidationException::withMessages([
                'terms' => 'We cannot open new accounts just now. Please try again shortly.',
            ]);
        }

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            // The free audit the visitor ran before signing up, carried here by
            // the marketing home's call to action (`/start?audit={token}`).
            // Capped rather than pattern-matched: the lookup is a parameterised
            // where and an unknown token simply finds nothing, so the only thing
            // worth refusing is a string too large to be a token at all.
            'audit' => ['nullable', 'string', 'max:64'],
            // ⚠️ **UNCHECKED BY DEFAULT AND REQUIRED HERE, NOT ON THE PAGE**
            // (T176 P22). `29` §2 wants every agreement recorded as a dated,
            // versioned event rather than a boolean, and the first half of that
            // is a box the person actually ticked — `ConsentProof` refuses any
            // proof blob whose `checkbox_state` says otherwise. Validated in
            // this action because Fortify owns `POST /register` and there is no
            // form request in front of it; `$input` is the only source of what
            // was submitted.
            'terms' => ['accepted'],
        ], [
            // Outcome language, and it names what the person is agreeing to
            // rather than the field that failed.
            'terms.accepted' => 'Please agree to the Terms of Service, the SMS & Communications '
                .'Terms and the Privacy Policy to create your account.',
        ])->validate();

        // ⚠️ AFTER VALIDATION AND BEFORE THE TRANSACTION, AND BOTH HALVES OF
        // THAT ORDERING ARE DELIBERATE (decision 2066's "velocity limits").
        //
        // After validation, because somebody fumbling a password rule three
        // times is not an attacker, and locking them out of the product on their
        // own first minute is the support ticket this control exists to prevent.
        //
        // Before the transaction, because the limiter writes to the cache and a
        // rollback would not take that write back — a refused registration that
        // still consumed its slot is a limiter that punishes the wrong attempt.
        // Outside, the hit only ever follows a registration this action intended
        // to make.
        //
        // ⚠️ AND IT IS ENFORCED HERE RATHER THAN IN MIDDLEWARE ON 398's RULE:
        // an outer guard refuses first and leaves the inner one unfalsifiable.
        // Fortify offers no limiter hook for its register route in any case —
        // see RegistrationRateLimits for what that left unthrottled.
        RegistrationRateLimits::enforce($this->request());

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);

            $this->tenants->provision($user, $input['audit'] ?? null);

            // ⚠️ **AFTER PROVISIONING, INSIDE THE SAME TRANSACTION.**
            // `provision()` is what establishes the tenant, and `business_id`
            // here is filled from that context — so this cannot run before it,
            // and it must not run outside the transaction: an account whose
            // acceptance failed to write is an account with no agreement behind
            // it, which is precisely the state this record exists to make
            // impossible.
            $this->acceptances->record(
                TermsAcceptanceMethod::Checkbox,
                $this->proof(),
                'user:'.$user->getKey(),
            );

            return $user;
        });
    }

    /**
     * What makes the acceptance provable rather than merely recorded.
     *
     * ⚠️ **NEVER `$request->ip()`.** `29` §2 rule 21 forbids storing a raw
     * address, and `ConsentProof` refuses one at any depth — the guard rather
     * than the habit. `AuthorizeNetCheckoutController::proof()` builds the same
     * three keys for the other account-holder record, and the shape is
     * deliberately identical.
     *
     * @return array<string, mixed>
     */
    private function proof(): array
    {
        $request = $this->request();

        return [
            'url' => $request->url(),
            'ip_hash' => HashedIp::of($request),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
        ];
    }
}
