<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MagicLinkToken;
use App\Services\MagicLinkService;
use App\Services\Mail\MailQuota;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The three limits on the magic-link door (9720).
 *
 * ⛔ **BOTH LEGS CARRIED AN INLINE `throttle:N,1` AND THEY WERE THE ONLY TWO
 * THIS APPLICATION DECLARED — WHICH IS ALSO WHY NO INSTRUMENT COULD SEE THEM.**
 * Every other throttle in `routes/` names a limiter declared in this directory,
 * and `ObservabilityTest` §11's first two arms are built on that name: the
 * helper behind them skipped anything not matching `/^[a-z][a-z0-9\-]*$/` with
 * a bare `continue`. So the two routes carrying the loosest figures in this
 * application were the two routes the figure-checking arms could not read.
 *
 * ⚠️ **"THE ONLY TWO IN THE APPLICATION" IS WHAT THE BRIEF SAID AND IT IS NOT
 * TRUE — THERE IS A THIRD AND IT IS NOT OURS.** `POST `livewire.upload-file``
 * carries `throttle:60,1` on `['web']` and nothing else, from
 * `FileUploadConfiguration::middleware()`'s
 * `config('livewire.temporary_file_upload.middleware') ?: 'throttle:60,1'`, and
 * `config/livewire.php` is not published here so the vendor default stands.
 * ⛔ **A `grep` over `routes/` and `app/` cannot see it**, which is how the
 * count came out at two — the route is declared in `vendor/`, and the only
 * instrument that finds it is one that reads the router. It is left alone and
 * exempted by name in §11, with the argument at the exemption: its real gate is
 * `abort_unless(request()->hasValidSignature(), 401)` inside Livewire's own
 * controller, and replacing its middleware means owning Livewire's upload stack
 * for ever.
 *
 * ⛔ **AND AN INLINE THROTTLE CANNOT BE KEYED AT ALL.** `ThrottleRequests::handle()`
 * only consults a named limiter; with a figure it falls through to
 * `resolveRequestSignature()`, which for a guest returns
 * `sha1($route->getDomain().'|'.$request->ip())` — **the raw address under an
 * unsalted digest**, which is the thing 9620 rejected in writing and which
 * §11's own failure message demands nobody ship. There is no argument to have
 * about it: the shape has no key parameter.
 *
 * ⛔ **THE TWO LEGS ALSO SHARED ONE BUCKET, AND NOTHING SAID SO.** `$prefix`
 * defaults to `''`, so an inline throttle's cache key is the request signature
 * and **nothing else** — no route, no figure, no limiter name. Measured in
 * wt3 on 2026-08-25 by driving real requests: five GETs at
 * `/auth/magic-link/{token}` (`throttle:10,1`) left the very next POST to
 * `/auth/magic-link` (`throttle:5,1`) answering **429 with
 * `X-RateLimit-Remaining: 0`**. `routes/web.php` described *"both legs, for
 * different reasons"*; they were one counter compared against two ceilings, and
 * the consume leg was a denial-of-service lever on the request leg — reachable
 * by the mail scanners {@see MagicLinkService}'s own docblock names. Naming the
 * limiters is what separates them: a named limit keys on
 * `md5($limiterName.$limit->key)`.
 *
 * ---------------------------------------------------------------------------
 * ⛔ THE THIRD LIMIT IS THE ONE THE SIBLING DOOR ALREADY HAD
 * ---------------------------------------------------------------------------
 *
 * ⚠️ **THE HEADLINE COMPARISON WITH `password-reset-request` IS PER-NETWORK,
 * AND THE PER-RECIPIENT ONE IS WORSE.** `config/auth.php` sets
 * `passwords.users.throttle` to 60, and `DatabaseTokenRepository::create()`
 * refuses a second token for the *same user* inside that window **whatever
 * address asked** — which is why 9621 had to handle a `RESET_THROTTLED` arm at
 * all. So the reset door is bounded at 60 messages an hour at any one victim
 * from the entire internet.
 *
 * **This door had no such limit.** `MagicLinkService::request()` minted a fresh
 * token on every call, so from N addresses a victim received N × 300 messages
 * an hour, bounded by nothing in this application. A per-network figure cannot
 * close that — an attacker rotates addresses — so the repair is a second limit
 * at a different granularity, which is 9623's shape with its sign flipped: the
 * register door had the inner limit and needed an outer ceiling, and this door
 * had the outer ceiling and needed the inner limit.
 *
 * ⛔ **WHAT NEITHER FIGURE CLOSES, SAID HERE RATHER THAN DISCOVERED LATER.**
 * Magic-link mail leaves through `PlatformMailer::deliverNow()`, which is gated
 * by {@see MailQuota::hasHeadroom()} — **the whole ceiling**, not
 * `hasCustomerHeadroom()`'s reserve. So a flood on this door eats the platform's
 * 24-hour send allowance and stops every tenant's customer mail *before* it
 * stops itself. The two limits below raise the cost of that by two independent
 * factors and neither ends it. ⛔ **AND A GLOBAL CEILING ON SIGN-IN MAIL IS
 * REFUSED RATHER THAN UNBUILT**: a limit that refuses sign-in links platform-wide
 * is precisely the outage `MailQuota`'s reserve exists to prevent — *"an owner
 * locked out of their account because a review-invite batch ate the quota is
 * the worst version of this failure"* — and building it would be that failure
 * with an attacker holding the switch.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ AND `HashedIp` COLLAPSES BEHIND AN UNCONFIGURED PROXY
 * ---------------------------------------------------------------------------
 *
 * {@see PasswordResetRateLimits}' paragraph applies word for word: nothing
 * configures `trustProxies`, so `X-Forwarded-For` is ignored and these keys
 * cannot be spoofed — and the day a CDN is put in front of this application
 * every visitor shares the proxy's address and both figures below become a
 * platform-wide budget. `.claude/skills/deploying/` carries the check, and
 * `tests/Feature/TrustedProxiesTest.php` fails the build on the copy-paste
 * repair. ⚠️ **The recipient cooldown is the one limit here that survives it**,
 * because it is keyed on the address that was asked about rather than on the
 * address that asked.
 */
final class MagicLinkRateLimits
{
    /**
     * Sign-in links one network may ask for in an hour.
     *
     * ⚠️ **AN ENGINEERING RAIL, MINE TO SET** — T137 §3 puts rate limiting under
     * "no owner decisions needed", and unlike an allowance or a price nothing
     * about this figure is a promise to a customer.
     *
     * ⛔ **TEN, WHICH IS DELIBERATELY *NOT* {@see PasswordResetRateLimits::REQUESTS_PER_HOUR}'s
     * FIVE.** Copying the sibling figure is the move that looks consistent and
     * is wrong, because three structural differences make a legitimate person
     * ask again here and not there:
     *
     *   the link lives {@see MagicLinkService::LIFETIME_MINUTES} minutes and a
     *   reset token lives sixty (`config/auth.php`, `passwords.users.expire`),
     *   so somebody who reads mail on a phone twenty minutes later must ask
     *   again here and need not there;
     *
     *   the link is **single use on GET**, and `MagicLinkService`'s own docblock
     *   names the actor that spends it — *"mail scanners that visit every URL
     *   they see"*. A scanned link is a dead link and the person asks again. The
     *   reset door's GET renders a form and spends nothing, so it has no
     *   equivalent;
     *
     *   this is a **primary** door rather than a recovery path. Three of this
     *   application's four sign-in methods establish no password at all (9628),
     *   so for those accounts this is the only way in — which makes
     *   `PasswordResetRateLimits`' own sizing sentence, *"being unable to get
     *   back into an account you own is a worse outcome"*, apply here more
     *   strongly rather than less.
     *
     * ⛔ **AND IT IS DELIBERATELY NOT MORE THAN TWICE**, because every request
     * that finds an account mails a message against the platform's own send
     * ceiling and against the reserve-free half of it — see the class docblock.
     * A person needs one; two on a slow morning; three where a scanner ate two.
     * Four colleagues each having that morning behind one NAT is eight.
     *
     * ⚠️ **IT CAN REFUSE A PERSON, AND THAT IS WRITTEN DOWN RATHER THAN
     * GLOSSED** — `PasswordResetRateLimits`' rule. The eleventh ask in an hour
     * from one office is refused for an hour, it costs nobody an account, and
     * the recipient cooldown below is what keeps that refusal from being the
     * only thing standing between one mailbox and a flood.
     */
    public const int REQUESTS_PER_HOUR = 10;

    /**
     * Link clicks one network may make in a minute.
     *
     * ⚠️ **THE FIGURE IS UNCHANGED FROM THE INLINE `throttle:10,1` IT REPLACES,
     * AND THAT IS THE POINT.** What was wrong with the consume leg was its key
     * and its shared bucket, not its number — `routes/web.php` argued ten a
     * minute as *"what makes brute-forcing 64 characters pointless rather than
     * merely impractical"*, and that argument is still right. ⚠️ **Naming it
     * therefore WIDENS this leg slightly**, from ten shared with the request
     * leg to ten of its own, and that is the intended direction: the coupling
     * was an accident of `$prefix` defaulting to an empty string and its only
     * effect was to let a mail scanner deny somebody else's sign-in requests.
     *
     * ⚠️ **PER MINUTE WHERE {@see self::REQUESTS_PER_HOUR} IS PER HOUR, AND THE
     * WINDOWS ARE CHOSEN RATHER THAN INHERITED.** A click is bursty — a scanner
     * prefetch, a browser prefetch and the person are three GETs in a second for
     * one sign-in — so an hourly bucket would punish one burst for an hour.
     * Harassment, which is what the request leg defends against, is the opposite
     * shape and needs the long window.
     *
     * ⚠️ **GUESSING IS NOT WHAT THIS BOUNDS**, {@see PasswordResetRateLimits::ATTEMPTS_PER_HOUR}'s
     * point: the token is 64 characters of `Str::random()` stored hashed, so any
     * figure at all defeats guessing and a figure sized against it would be
     * arbitrary. What it bounds is work — here a SHA-256 and one indexed
     * conditional UPDATE, which is cheap, which is why this is the generous one.
     */
    public const int CONSUMES_PER_MINUTE = 10;

    /**
     * Seconds one address must wait before a second link is issued to it.
     *
     * ⛔ **THE LIMIT THIS DOOR DID NOT HAVE AND THE RESET DOOR ALWAYS DID**, and
     * the figure is `config/auth.php`'s `passwords.users.throttle` deliberately
     * unchanged, because the argument for parity is the whole argument: these
     * are two doors that mail a sign-in credential to an address the caller
     * chose, and the one bounded per-recipient at sixty seconds is the one
     * nobody has ever called too strict.
     *
     * ⚠️ **NOT LONGER, AND THE MAIL SCANNER IS WHY.** Fifteen minutes — one
     * link per link lifetime — is the tightest coherent figure and it strands
     * the one case this door has and the reset door does not: a scanner
     * consumes the link, the person clicks a dead one, and asking again is the
     * only repair available to them. Sixty seconds costs a person one pause and
     * costs a flood ninety-eight per cent of its volume at any single mailbox.
     *
     * ⚠️ **IT DOES NOT MAKE THE ENDPOINT SLOWER TO ANSWER OR DIFFERENT TO READ.**
     * {@see MagicLinkService::request()} returns void on every arm, so a cooled
     * address is indistinguishable from an address with no account — and 9504's
     * open timing channel is *narrowed* by this rather than widened, because the
     * cooled arm skips the send that channel measures.
     */
    public const int RECIPIENT_COOLDOWN_SECONDS = 60;

    /**
     * The limiter guarding `POST /auth/magic-link`.
     */
    public const string REQUEST_LIMITER = 'magic-link-request';

    /**
     * The limiter guarding `GET /auth/magic-link/{token}`.
     */
    public const string CONSUME_LIMITER = 'magic-link-consume';

    public static function register(): void
    {
        RateLimiter::for(self::REQUEST_LIMITER, fn (Request $request): Limit => Limit::perHour(self::REQUESTS_PER_HOUR)
            ->by(self::key($request, self::REQUEST_LIMITER))
            ->response(self::refusal()));

        RateLimiter::for(self::CONSUME_LIMITER, fn (Request $request): Limit => Limit::perMinute(self::CONSUMES_PER_MINUTE)
            ->by(self::key($request, self::CONSUME_LIMITER))
            ->response(self::refusal()));
    }

    /**
     * Whether this address was sent a link too recently to be sent another.
     *
     * ⚠️ **READS THE TOKEN TABLE RATHER THAN THE CACHE, WHICH IS
     * `DatabaseTokenRepository::recentlyCreatedToken()`'s SHAPE AND ITS
     * REASON.** The question is *"was a link issued?"*, and the row is the
     * issue; a cache counter is a separate record of the same event that can
     * come apart from it — a `RateLimiter::hit()` skipped by an exception
     * between the write and the hit fails **open**, and this cannot. It costs
     * one indexed read on `magic_link_tokens.email` per request.
     *
     * ⚠️ **A ROW WITH A NULL `created_at` IS INVISIBLE TO THIS AND THE FALLBACK
     * IS ONE EXTRA MESSAGE**, never a refusal — the column is nullable in the
     * creating migration and `Model::create()` always fills it, so the case is
     * a hand-written fixture rather than anything the application produces.
     *
     * ⚠️ **NO TENANT IS ESTABLISHED AND NONE IS NEEDED** — `magic_link_tokens`
     * carries no `business_id` and is named in `TenancyTest`'s `$exempt`
     * census, which is what lets a pre-authentication read match rows here at
     * all. {@see MagicLinkService::prune()} carries the measurement.
     */
    public static function recentlyIssuedTo(string $email): bool
    {
        return MagicLinkToken::query()
            ->where('email', Str::lower(trim($email)))
            ->where('created_at', '>', Carbon::now()->subSeconds(self::RECIPIENT_COOLDOWN_SECONDS))
            ->exists();
    }

    /**
     * A per-visitor key that is not an address.
     *
     * {@see PasswordResetRateLimits::key()}'s rule, and its argument for the
     * address alone rather than the address and the email: the population this
     * bounds is one source asking about many addresses, and a bucket per
     * submitted address is a bucket a stranger walking a list never fills. The
     * per-address half of the control is {@see self::recentlyIssuedTo()}, where
     * it belongs and where an attacker's own addresses cannot dilute it.
     *
     * The fallback gives a request with no resolvable client address one shared
     * bucket rather than a free pass.
     */
    private static function key(Request $request, string $limiter): string
    {
        return HashedIp::of($request) ?? $limiter.':unknown-origin';
    }

    /**
     * @return callable(): Response
     */
    private static function refusal(): callable
    {
        // {@see PasswordResetRateLimits::refusal()}, word for word and for its
        // reasons: a refusal naming the window tells a script how long to
        // sleep, and one naming the address would be an oracle beside a door
        // whose whole design is that it answers everybody identically.
        return fn (): Response => response(
            'Too many requests. Try again shortly.',
            Response::HTTP_TOO_MANY_REQUESTS,
        );
    }
}
