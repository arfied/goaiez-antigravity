<?php

declare(strict_types=1);

namespace App\Services\ShortLinks;

use App\Enums\ClickDeviceClass;
use App\Enums\ClickDiscardReason;
use Illuminate\Http\Request;

/**
 * Was this a person opening a link, or a machine looking at it?
 *
 * ⛔ **THE QUESTION MATTERS MORE THAN IT SOUNDS.** Carriers and mail providers
 * fetch every URL we send — that is what link filtering *is*, and T137 §5 treats
 * it as a given. Counting those produces a campaign report showing near-total
 * engagement within seconds of sending, which is both wrong and extremely
 * convincing. Worse, on a CRM timeline it becomes a sentence about a named
 * person: *"they opened your message."* Decision 113 refuses exactly this shape
 * for review destinations — **a click is never evidence of the thing it would
 * suggest.**
 *
 * ## Only what the request says about itself
 *
 * Every signal below is a declaration: the HTTP method, a prefetch header, a
 * self-identifying user agent. ⚠️ **Nothing here infers from behaviour**, and
 * that is a rule rather than a limitation of effort. "Clicked suspiciously soon
 * after delivery" and "same device class twice in a minute" are the shape
 * `CLAUDE.md` bans — device signals concatenated into something that follows a
 * person — and they would be the obvious next commit.
 *
 * ## What it cannot do
 *
 * A scanner presenting a browser's user agent over GET is a person as far as
 * this class is concerned, and there is no honest way around that from inside a
 * redirect. **This removes the traffic that announces itself.** Said plainly
 * because a filter described as "bot detection" invites somebody to trust the
 * number as clean — 352/397/565's rule that a claim must be sized to its
 * mechanism.
 */
final class FetchClassifier
{
    /**
     * Agents that say what they are. Matched case-insensitively as substrings.
     *
     * ⚠️ **DELIBERATELY SHORT AND DELIBERATELY GENERIC.** A long vendor list
     * goes stale silently — a scanner renames itself and every fetch starts
     * counting — while these five tokens appear in the overwhelming majority of
     * self-identifying agents and do not need maintaining. `preview` and `fetch`
     * are here for messaging apps that render link cards, which are the ones
     * that hit a link the instant a message is delivered.
     *
     * @var list<string>
     */
    private const array DECLARED_BOT_TOKENS = ['bot', 'crawler', 'spider', 'preview', 'fetch'];

    /**
     * Null when this fetch should count as a person opening the link.
     */
    public function discardReasonFor(Request $request): ?ClickDiscardReason
    {
        // Checked first because it is the only signal that is not a heuristic at
        // all: nobody reads a page with HEAD. Ordering matters for the recorded
        // reason rather than the outcome — a HEAD from a named bot should read
        // as the cheaper, more certain fact.
        if (! $request->isMethod('GET')) {
            return ClickDiscardReason::NotAGet;
        }

        if ($this->isPrefetch($request)) {
            return ClickDiscardReason::Prefetch;
        }

        if ($this->declaresItselfABot($request)) {
            return ClickDiscardReason::DeclaredBot;
        }

        return null;
    }

    /**
     * A coarse bucket, from the user agent, which is then dropped.
     *
     * ⚠️ **THE STRING NEVER LEAVES THIS METHOD.** It is high-entropy enough to
     * pair with a timestamp and follow one person between two links, which is
     * precisely what `CLAUDE.md` forbids assembling.
     */
    public function deviceClassFor(Request $request): ClickDeviceClass
    {
        $agent = mb_strtolower($request->userAgent() ?? '');

        if ($agent === '') {
            return ClickDeviceClass::Unknown;
        }

        // Tablet before phone: an iPad's agent contains neither "mobile" nor
        // "phone" but an Android tablet's contains "android", and every Android
        // *phone* agent contains "mobile". Reversing these two mislabels every
        // tablet as a phone, which is the kind of wrong that never gets noticed
        // because the number still looks plausible.
        if (str_contains($agent, 'ipad') || (str_contains($agent, 'android') && ! str_contains($agent, 'mobile'))) {
            return ClickDeviceClass::Tablet;
        }

        if (str_contains($agent, 'iphone') || str_contains($agent, 'mobile') || str_contains($agent, 'android')) {
            return ClickDeviceClass::Phone;
        }

        if (str_contains($agent, 'macintosh') || str_contains($agent, 'windows') || str_contains($agent, 'x11')) {
            return ClickDeviceClass::Desktop;
        }

        return ClickDeviceClass::Unknown;
    }

    /**
     * The browser told us it was fetching without showing.
     */
    private function isPrefetch(Request $request): bool
    {
        // `Purpose: prefetch` is Chrome's, `X-Purpose: preview` is Safari's, and
        // `Sec-Purpose: prefetch;prerender` is the newer standard header. All
        // three mean the same thing: nobody is looking at this yet.
        foreach (['purpose', 'x-purpose', 'sec-purpose', 'x-moz'] as $header) {
            $value = mb_strtolower((string) $request->header($header, ''));

            if ($value !== '' && (str_contains($value, 'prefetch') || str_contains($value, 'preview') || str_contains($value, 'prerender'))) {
                return true;
            }
        }

        return false;
    }

    private function declaresItselfABot(Request $request): bool
    {
        $agent = mb_strtolower($request->userAgent() ?? '');

        if ($agent === '') {
            // ⚠️ **AN ABSENT AGENT IS NOT COUNTED AS A BOT, AND THAT IS A
            // CHOICE THAT COSTS SOMETHING.** Privacy-hardened browsers and some
            // corporate proxies strip it, so discarding on absence would quietly
            // stop counting a small group of real people — and they are exactly
            // the people least likely to be a scanner. The cost is that a
            // crawler which sends nothing is counted.
            return false;
        }

        foreach (self::DECLARED_BOT_TOKENS as $token) {
            if (str_contains($agent, $token)) {
                return true;
            }
        }

        return false;
    }
}
