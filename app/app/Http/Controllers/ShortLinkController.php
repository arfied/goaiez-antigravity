<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ShortLinks\FetchClassifier;
use App\Services\ShortLinks\ShortLinkClicks;
use App\Services\ShortLinks\ShortLinks;
use App\Support\ShortLinkRateLimits;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The redirector — T137 `SL-5`.
 *
 * One route, one token, one 302. Everything interesting about it is what it
 * refuses to do.
 *
 * ## The tenant arrives with the row, not with the request
 *
 * ⚠️ **THIS IS THE ONLY CONTROLLER IN THE APPLICATION THAT ESTABLISHES A TENANT
 * FROM A URL SEGMENT ANYBODY CAN TYPE.** `ResolveFeedbackPage` does the same
 * from a slug and `ResolveTenant` from a session; here it is a random token
 * presented by a phone with no session at all. The order below is therefore
 * load-bearing: **resolve, then establish, then everything else** — and nothing
 * before the resolve may touch a tenant-scoped table, because there is no tenant
 * to scope it by.
 *
 * ## A miss and a hit are answered identically and logged differently
 *
 * ⛔ **NO 404 BODY, NO "THIS LINK HAS EXPIRED" PAGE.** A distinguishable response
 * tells a guesser which of their tokens exist, which is the one thing the 71
 * bits are protecting. Expired, revoked and never-existed all produce the same
 * status; only the rows differ.
 *
 * ## The click is recorded before the redirect, and that ordering costs something
 *
 * ⚠️ **A WRITE ON THE HOT PATH OF SOMEBODY WAITING FOR A PAGE.** The alternative
 * — queue it — loses the click whenever the queue is behind or the worker is
 * down, and a click that arrives on a timeline ten minutes late is worse than
 * useless on a screen somebody is watching during a campaign. One indexed insert
 * is affordable; **if it ever stops being, the fix is a queue with an ordering
 * guarantee, not dropping the write.**
 */
final class ShortLinkController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        ShortLinks $links,
        ShortLinkClicks $clicks,
        FetchClassifier $classifier,
    ): RedirectResponse|Response {
        // ⚠️ **THE HOST IS CHECKED HERE RATHER THAN IN `Route::domain()`, AND
        // THE REASON IS THAT THERE MUST BE ONE SOURCE OF TRUTH FOR THE DOMAIN.**
        // `Route::domain()` is evaluated when routes are registered, which is
        // before a database read is affordable, so it would have to come from
        // config — and then the domain a link is *minted* with (the registry,
        // 2116) and the domain the redirector *answers on* would be two values
        // that drift, with the failure appearing only as links that resolve
        // nowhere. Reading the registry here keeps the seed authoritative for
        // both.
        if ($request->getHost() !== $links->domain()) {
            return $this->refuse();
        }

        // Checked before the lookup: a visitor who has spent their misses is
        // answered without a query, so the guessing costs them and not us.
        if (ShortLinkRateLimits::missesExhausted($request)) {
            return $this->refuse();
        }

        $link = $links->resolve($token);

        if ($link === null) {
            ShortLinkRateLimits::recordMiss($request);

            return $this->refuse();
        }

        // ⚠️ **EVERY LINE BELOW RUNS INSIDE THE TENANT THE ROW NAMED**, including
        // the `isLive` branch — an expired link still belongs to somebody, and
        // its fetch is still their event.
        return Tenancy::actingAs((int) $link->business_id, function () use (
            $request,
            $link,
            $clicks,
            $classifier,
        ): RedirectResponse|Response {
            if (! $link->isLive(now())) {
                // ⚠️ **NOT RECORDED AS A MISS.** A stale link is a real message
                // this platform sent, and counting it against the guesser budget
                // would let an old campaign lock a real customer out of a live
                // link they open next.
                return $this->refuse();
            }

            $clicks->record(
                $link,
                $classifier->discardReasonFor($request),
                $classifier->deviceClassFor($request),
            );

            // 302, not 301. ⚠️ **A permanent redirect is cached by the browser
            // and by every intermediary**, so the second open of the same link
            // never reaches this application — the click disappears, and a
            // revoked link keeps working for whoever already has it cached.
            return redirect()->away($link->target_url, HttpResponse::HTTP_FOUND);
        });
    }

    /**
     * The one answer every failure gives.
     */
    private function refuse(): Response
    {
        return response('', HttpResponse::HTTP_NOT_FOUND);
    }
}
