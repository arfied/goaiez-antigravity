<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\PlatformCredentials;
use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Cloudflare Turnstile, server-side (decision 194).
 *
 * `29` §6.2 requires a captcha after a visitor's first free audit. Turnstile was
 * chosen because it is free, cookieless, and needs **no new composer package** —
 * a script tag on the page and one POST from here.
 *
 * Endpoint, parameters and error codes read from Cloudflare's live documentation
 * on 2026-07-31.
 *
 * NO INTERFACE, DELIBERATELY. Every other vendor in this codebase sits behind
 * one, and this does not, because the reasons that justify the others are absent
 * here: there is no second implementation, no tenant credential to vary, and
 * `Http::fake()` already covers the tests. One concrete class is the honest
 * amount of structure; if a second captcha vendor ever appears, extracting an
 * interface from a class this small is a five-minute job. See `CLAUDE.md`:
 * prefer simple over clever.
 *
 * WE DO NOT SEND `remoteip`, AND THAT IS A PRIVACY DECISION RATHER THAN AN
 * OVERSIGHT. Cloudflare documents it as optional. Sending it would ship a raw
 * visitor IP address to a third party from a page where our own rules forbid us
 * from even storing one — `29`'s "never store raw IP" is about the address being
 * a personal identifier, and handing it to someone else is not a loophole in
 * that. The parameter only sharpens Cloudflare's own risk scoring; the token
 * validates without it.
 *
 * FAILS CLOSED, IN BOTH DIRECTIONS. A missing secret is a refusal rather than a
 * pass, because a captcha that silently stops verifying is indistinguishable
 * from no captcha — and the endpoint it guards spends money. A Cloudflare outage
 * is also a refusal, for the same reason. Both are loud in the logs.
 *
 * AND SO IS A REJECTION, WHICH IT WAS NOT UNTIL NOW (decision 413). The fourth
 * outcome — HTTP 200 carrying `success: false` — was the quiet one: `timed()`
 * recorded a successful call, `verify()` returned false, and Cloudflare's stated
 * reason was discarded. A wrong secret, a hostname the widget is not allowlisted
 * for, and a replayed token are indistinguishable in our logs from a visitor who
 * simply failed the challenge, and the first two are deployment faults that
 * present as "the captcha is broken".
 */
final class TurnstileVerifier
{
    private const string ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * The credential key, in PlatformCredentials' namespace (doc 38 D-149).
     */
    public const string SECRET_KEY = 'turnstile_secret';

    /**
     * The error codes Cloudflare documents, read from its live documentation on
     * 2026-08-02.
     *
     * AN ALLOWLIST, NOT A PASSTHROUGH, and the reason is VendorLog's own thesis:
     * it builds log context from an allowlist and never from a vendor response
     * body, because a denylist "fails open the first time a provider adds a
     * field, and the failure is invisible". `error-codes` is a response body like
     * any other. Nothing outside this list is ever written to the log — an
     * unrecognised code is recorded as the fact that there was one.
     *
     * @var list<string>
     */
    private const array ERROR_CODES = [
        'missing-input-secret',
        'invalid-input-secret',
        'missing-input-response',
        'invalid-input-response',
        'bad-request',
        'timeout-or-duplicate',
        'internal-error',
    ];

    /**
     * Whether a token is genuine and unspent.
     *
     * A token is single-use. Cloudflare returns `timeout-or-duplicate` for a
     * replay, which is exactly the attack this guards against — someone solving
     * one challenge and reusing the token for every subsequent audit — so it is
     * treated as a failure like any other and is worth naming here so nobody
     * later reads it as a transient error and adds a retry.
     */
    public function verify(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        if (! PlatformCredentials::has(self::SECRET_KEY)) {
            // Fail closed. The alternative — passing when unconfigured — turns a
            // missing environment variable into a silently disabled abuse
            // control on a money-spending public endpoint.
            VendorLog::failure('turnstile', 'POST', self::ENDPOINT, 'secret_not_configured');

            return false;
        }

        try {
            $response = VendorLog::timed(
                'turnstile',
                'POST',
                self::ENDPOINT,
                fn () => Http::asForm()
                    ->timeout((int) config('services.turnstile.timeout', 5))
                    ->post(self::ENDPOINT, [
                        'secret' => PlatformCredentials::get(self::SECRET_KEY),
                        'response' => $token,
                        // remoteip omitted on purpose — see the class docblock.
                    ]),
            );
        } catch (ConnectionException) {
            VendorLog::failure('turnstile', 'POST', self::ENDPOINT, ConnectionException::class);

            return false;
        }

        if ($response->failed()) {
            VendorLog::failure('turnstile', 'POST', self::ENDPOINT, 'http_'.$response->status());

            return false;
        }

        if ($response->json('success') !== true) {
            VendorLog::failure(
                'turnstile',
                'POST',
                self::ENDPOINT,
                'rejected_'.self::rejection($response->json('error-codes')),
            );

            return false;
        }

        return true;
    }

    /**
     * Cloudflare's stated reason, reduced to codes it documents.
     *
     * THE VERDICT DOES NOT DEPEND ON THIS. Every branch below returns a label for
     * the log and the caller is refused either way — which is the opposite of
     * decision 353, where an unreadable vendor body collapsed into a permissive
     * verdict. A reason we cannot read is still a rejection.
     *
     * The three non-code answers are distinct on purpose: `unstated` is
     * Cloudflare declining to say, `unrecognised` is Cloudflare saying something
     * this list has not seen — a new code, or a typo in ours — and `unreadable`
     * is a body that is not the documented shape at all. They want different
     * responses from whoever reads the log, and one shared label would hide that.
     */
    private static function rejection(mixed $codes): string
    {
        if (! is_array($codes)) {
            return 'unreadable';
        }

        if ($codes === []) {
            return 'unstated';
        }

        $recognised = [];

        foreach ($codes as $code) {
            if (is_string($code) && in_array($code, self::ERROR_CODES, true)) {
                $recognised[] = $code;
            }
        }

        return $recognised === [] ? 'unrecognised' : implode(',', $recognised);
    }
}
