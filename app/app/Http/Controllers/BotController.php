<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Fetch\RobotsPolicy;
use Illuminate\Contracts\View\View;

/**
 * The page `RobotsPolicy::USER_AGENT` promises to every webmaster we fetch from.
 *
 * ⚠️ THIS IS NOT DOCUMENTATION, IT IS THE SECOND HALF OF A PROMISE ALREADY BEING
 * MADE. `40` §6.1 requires an honest identity, and slice D shipped one:
 * `GoAiEzBot/1.0 (+https://goaiez.com/bot)` goes out on every robots.txt request
 * and every page fetch. The `+URL` convention means "here is who this is and how
 * to stop them" — a bot advertising a URL that 404s is worse than one
 * advertising none, because the webmaster has now been told there is an answer
 * and found that there is not. CLAUDE.md carried it as a known gap with the
 * condition attached: the page "must [exist] before the crawler runs against
 * live sites".
 *
 * ⚠️ THE USER-AGENT STRING IS PASSED IN, NEVER RETYPED IN THE TEMPLATE. The one
 * thing a webmaster copies off this page is the token they will put in their
 * robots.txt, and a hand-copied string that drifts from the constant produces a
 * disallow rule that matches nothing — a block that looks applied and is not.
 * `BotPageTest` additionally parses the `+URL` back out of the constant and
 * asserts it resolves here, so moving this route without moving the constant
 * fails the build rather than re-opening the gap.
 *
 * SIGNED-OUT AND INDEXABLE. Every other public page that opts out of indexing
 * does so because it is about one business (`29` §6.1's audit result, the
 * feedback page). This one is about us, and a webmaster who searches the token
 * out of their access log should find it.
 */
final class BotController extends Controller
{
    public function __invoke(): View
    {
        return view('marketing.bot', [
            'userAgent' => RobotsPolicy::USER_AGENT,
            'token' => RobotsPolicy::userAgentToken(),
        ]);
    }
}
