<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AnswerFixThenAskCheckInRequest;
use App\Jobs\SendConfirmedFixInviteJob;
use App\Models\TriageConversation;
use App\Services\Reviews\ReviewRouter;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * "Did we get that sorted?" — the page the customer actually sees — T546
 * §37.3(1), wave 38 lane C (10590–10609).
 *
 * ⚠️ **`signed` IS THE WHOLE AUTHORISATION AND IT RUNS BEFORE THIS CLASS.**
 * `campaign.media` and `content.growth-page.hold` answer the identical
 * problem the identical way and their reasoning transfers verbatim: the
 * tenant travels inside the signature because there is nothing on an
 * unauthenticated request to resolve one from, and `triage_conversations` is
 * FORCE row-level secured, so a signed URL naming the wrong tenant finds
 * nothing — the database answers it, not a comparison somebody could delete.
 *
 * ⚠️ **THE ANSWER IS A POST, DELIBERATELY, WHERE THE OTHER TWO PRECEDENTS ARE
 * A STATE-CHANGING GET.** Both of those are safe as a bare fetch because their
 * conservative direction is "hold" — a scanner that prefetches
 * `content.growth-page.hold`'s link costs a publish delayed by a day. A
 * scanner that prefetched a bare "yes, that's sorted" link here would falsely
 * record a customer's confirmation they never gave, and that confirmation is
 * what {@see self::answer()} uses to offer a public review — the wrong
 * direction to be wrong in. So `show()` only ever renders the two buttons,
 * and only a genuine form submission — `@csrf`, on this route's own session,
 * a real POST — can change anything.
 *
 * ⚠️ **THIS CLASS NEVER QUERIES `TriageConversation` DIRECTLY.**
 * `Architecture\ReviewsTest` holds that model reachable from `ReviewRouter`
 * alone; both actions below load and write through it.
 */
final class FixThenAskCheckInController extends Controller
{
    public function show(Request $request, int $business, int $conversation): View|HttpResponse
    {
        return Tenancy::actingAs($business, function () use ($request, $conversation): View|HttpResponse {
            $row = app(ReviewRouter::class)->findConversation($conversation);

            if (! $row instanceof TriageConversation || $row->fix_then_ask_offered_at === null) {
                return response('', HttpResponse::HTTP_NOT_FOUND);
            }

            // ⚠️ **ANSWERS THE SAME WAY TWICE**, on `Content\HoldGrowthPageController`'s
            // own posture for the identical shape of problem: a link already
            // answered is not an error, it is a person (or a mail scanner)
            // revisiting a page that already did its job.
            return view('reviews.fix-then-ask-checkin', [
                'alreadyAnswered' => $row->fix_then_ask_responded_at !== null,
                'formAction' => $request->fullUrl(),
            ]);
        });
    }

    public function answer(AnswerFixThenAskCheckInRequest $request, int $business, int $conversation): View|HttpResponse
    {
        return Tenancy::actingAs($business, function () use ($request, $conversation): View|HttpResponse {
            $row = app(ReviewRouter::class)->findConversation($conversation);

            if (! $row instanceof TriageConversation || $row->fix_then_ask_offered_at === null) {
                return response('', HttpResponse::HTTP_NOT_FOUND);
            }

            $reviewToInvite = app(ReviewRouter::class)->recordFixThenAskResponse(
                $row,
                $request->answer(),
                actor: 'customer',
            );

            // ⚠️ **DISPATCHED HERE, NEVER INSIDE `ReviewRouter`.** That class
            // decides and persists; it does not send messages or queue jobs —
            // `ReinviteDeferredReviews`' own separation, kept.
            //
            // ⚠️ **`SendConfirmedFixInviteJob`, NOT `SendReviewInviteJob` —
            // SEE ITS OWN DOCBLOCK.** `SendReviewInviteJob`'s idempotency claim
            // is already permanently spent for every review this feature
            // exists to help, by the unconditional dispatch
            // `FeedbackSubmission::store()` makes at submission time. Both
            // jobs call the same {@see \App\Services\Messaging\ReviewInviteSender::attempt()}
            // — nothing about the compliance path is duplicated, only the
            // dispatch's idempotency namespace.
            if ($reviewToInvite !== null) {
                SendConfirmedFixInviteJob::dispatch(
                    (int) $reviewToInvite->business_id,
                    (int) $reviewToInvite->location_id,
                    (int) $reviewToInvite->id,
                );
            }

            return view('reviews.fix-then-ask-checkin', [
                'alreadyAnswered' => true,
                'formAction' => null,
                'answer' => $request->answer(),
            ]);
        });
    }
}
