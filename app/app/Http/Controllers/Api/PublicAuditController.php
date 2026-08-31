<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartPublicAuditRequest;
use App\Http\Resources\PlaceCandidateResource;
use App\Http\Resources\PublicAuditResource;
use App\Models\PublicAudit;
use App\Services\Audit\PublicAuditStarter;
use App\Services\TurnstileVerifier;
use App\Support\HashedIp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * The free instant audit's public API (`29` §6.2).
 *
 * Unauthenticated by design — the entire premise of row 2 is that this runs
 * before anyone signs up. What stands in for authentication is four controls,
 * and `BUILD-PLAN` §6 is explicit that they are "all in the gate, none
 * optional": the rate limit (routes/api.php), Turnstile after the first audit
 * (here), the 24h place cache and the daily budget (PlacesSpend).
 *
 * The tenant middleware runs on this path and resolves nothing, which is
 * correct and worth knowing: ResolveTenant returns early when `Auth::id()` is
 * null, so no tenant is established and none is needed — `public_audits` is the
 * one table in the schema with no tenant key (decision 177).
 */
final class PublicAuditController extends Controller
{
    /**
     * `29` §6.2: "rate limit 3/hr/IP + captcha after 1".
     *
     * The captcha threshold and the window are stated here together because
     * they are one rule. The window matches the rate limiter's own hour, so a
     * visitor never meets a captcha for an audit that the limiter has already
     * forgotten about.
     */
    private const int AUDITS_BEFORE_CAPTCHA = 1;

    private const int CAPTCHA_WINDOW_MINUTES = 60;

    /**
     * Start an audit, or ask which business was meant.
     */
    public function store(
        StartPublicAuditRequest $request,
        PublicAuditStarter $starter,
        TurnstileVerifier $turnstile,
    ): JsonResponse {
        $ipHash = HashedIp::of($request);

        if ($this->captchaRequired($ipHash) && ! $turnstile->verify($request->turnstileToken())) {
            // 422 rather than 403: nothing here is forbidden, the request is
            // simply incomplete, and the client's job is to supply the missing
            // field and send it again. The flag is what tells it which field —
            // it cannot know in advance whether this visitor has been here
            // before, and telling it up front would leak that.
            return response()->json([
                'captcha_required' => true,
                'message' => 'Confirm you are not a robot, then try again.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $outcome = $starter->start($request->placeQuery(), $request->placeId(), $ipHash);

        if ($outcome->isAmbiguous()) {
            // 200, not 201: nothing was created. The visitor picks one of these
            // and comes back with its place_id, which costs no further search.
            return response()->json([
                'candidates' => PlaceCandidateResource::collection($outcome->candidates)->resolve(),
                'message' => 'More than one business matches that name. Which one is yours?',
            ]);
        }

        if (! $outcome->isStarted()) {
            return $this->unavailable((string) $outcome->reason);
        }

        return (new PublicAuditResource($outcome->audit))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Poll one audit.
     *
     * Findings accumulate in the row as each check completes (AuditEngine), so
     * repeated calls to this stream a partial result rather than blocking on a
     * finished one. That is the whole of `29` §6.2's "findings appear as
     * computed" — there is no SSE and no websocket, because polling a row that
     * is already being written is enough.
     */
    public function show(Request $request, string $token): JsonResponse
    {
        $audit = PublicAudit::query()
            ->live()
            ->where('token', $token)
            ->first();

        if (! $audit instanceof PublicAudit) {
            // One response for "never existed", "expired" and "pruned". They are
            // the same fact from the visitor's side, and distinguishing them
            // would confirm that a given token was once real.
            return response()->json([
                'message' => 'That audit link has expired. Run a new one — it takes a few seconds.',
            ], Response::HTTP_NOT_FOUND);
        }

        return (new PublicAuditResource($audit))->response();
    }

    /**
     * Whether this visitor has already had their one free pass.
     *
     * Counted from `public_audits` rather than from the rate limiter, because
     * the limiter's counter is a cache entry that a restart clears — and a
     * captcha that a deploy switches off is not a control. The (ip_hash,
     * created_at) index added by this slice is what makes it cheap.
     *
     * A null hash — no client address at all — is treated as "not yet", because
     * failing the other way would put a captcha in front of every visitor the
     * moment a proxy stopped forwarding addresses.
     */
    private function captchaRequired(?string $ipHash): bool
    {
        if ($ipHash === null) {
            return false;
        }

        return PublicAudit::query()
            ->where('ip_hash', $ipHash)
            ->where('created_at', '>', Carbon::now()->subMinutes(self::CAPTCHA_WINDOW_MINUTES))
            ->count() >= self::AUDITS_BEFORE_CAPTCHA;
    }

    /**
     * A reason code from the resolver, as a sentence and a status.
     *
     * The two 503s are temporary and true: the budget resets at midnight and a
     * vendor outage ends. The 422 is about the input, and says the one useful
     * thing — try a different name — rather than explaining our internals.
     */
    private function unavailable(string $reason): JsonResponse
    {
        return match ($reason) {
            'budget_exhausted' => response()->json([
                'message' => 'We have run today\'s free audits. Try again tomorrow.',
            ], Response::HTTP_SERVICE_UNAVAILABLE),
            'search_failed' => response()->json([
                'message' => 'We could not reach Google just now. Try again in a minute.',
            ], Response::HTTP_SERVICE_UNAVAILABLE),
            default => response()->json([
                'message' => 'We could not find that business on Google. Check the name and try again.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY),
        };
    }
}
