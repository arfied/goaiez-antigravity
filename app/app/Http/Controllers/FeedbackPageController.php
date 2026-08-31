<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ReviewDestination;
use App\Http\Middleware\ResolveFeedbackPage;
use App\Http\Requests\StoreFeedbackRequest;
use App\Models\FeedbackPage;
use App\Models\Location;
use App\Models\Review;
use App\Services\Destinations\ReviewInvites;
use App\Services\Feedback\ConsentDisclosure;
use App\Services\Feedback\FeedbackInput;
use App\Services\Feedback\FeedbackSubmission;
use App\Services\Links\BookingLink;
use App\Services\Reviews\PhiAnalysisConsent;
use App\Services\ShortLinks\FetchClassifier;
use App\Support\HashedIp;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The hosted feedback page (`17` FPR-01).
 *
 * Not Livewire, and for the same reason MarketingController is not (decision
 * 259): this page has no server-held state worth a round trip, and FPR-01 gates
 * it on Core Web Vitals. It goes further than the marketing pages, though —
 * there is no JavaScript here at all. Slice F's picker must render with
 * JavaScript disabled (BUILD-PLAN 2.6.3), the picker lives on the post-submit
 * screen, so the path to that screen has to work without it too.
 */
final class FeedbackPageController extends Controller
{
    /**
     * Long enough for every real browser's user agent string, short enough to
     * bound what an attacker-controlled header can put into a proof blob.
     */
    private const int MAX_USER_AGENT_LENGTH = 500;

    /**
     * The session key holding which review the picker is offering destinations
     * for, per slug — the same per-slug shape as `feedback.started_at`, for the
     * same reason: a POST on tenant A must never furnish tenant B's thanks
     * screen in the same session.
     */
    private const string INVITE_KEY = 'feedback.invite.';

    /**
     * How long after submitting a customer may still tap through.
     *
     * ⚠️ THIS IS A SHARED-DEVICE BOUND, NOT A CONVENIENCE ONE. The flagship
     * install for this page is a QR code on a restaurant table or a tablet at a
     * counter, and the whole of §2.3.3's reasoning is that customers arrive,
     * submit, and leave. Without an expiry the invite sits in that browser
     * profile indefinitely, so the *next* person to reach the thanks screen on a
     * shared tablet would be offered — and could click through on — a stranger's
     * review. show() clearing the key on every fresh form load is the primary
     * guard; this bounds the case where nobody loads the form again.
     *
     * Two hours is far longer than one visit and far shorter than one shift.
     */
    private const int INVITE_MINUTES = 120;

    public function show(Request $request): View
    {
        $page = $this->page($request);
        $location = $this->location($request);
        $businessName = $this->businessName($location);

        // The clock the timing check reads on the POST. In the session rather
        // than a hidden field, so a client that never loaded the form cannot
        // forge it — and keyed on the slug, so two tabs on two businesses do not
        // overwrite each other's start time.
        $request->session()->put('feedback.started_at.'.$page->slug, now()->getTimestamp());

        // A fresh form load is a fresh customer. On a shared device the previous
        // person's invite is still sitting in this session, and leaving it there
        // would let whoever fills the form next be handed a stranger's review to
        // click through on. Cleared here rather than only on submit, because the
        // person who abandons the form is exactly the one who never reaches it.
        $request->session()->forget(self::INVITE_KEY.$page->slug);

        [$termsUrl, $privacyUrl] = $this->legalUrls();

        return view('feedback.show', [
            'page' => $page,
            'location' => $location,
            'businessName' => $businessName,
            'smsLabel' => ConsentDisclosure::smsLabel(),
            'smsText' => ConsentDisclosure::smsHtml($businessName, $termsUrl, $privacyUrl),
            'emailLabel' => ConsentDisclosure::emailLabel(),
            'emailText' => ConsentDisclosure::emailHtml($businessName, $termsUrl, $privacyUrl),

            // ⚠️ RENDERED ONLY BY A COVERED ENTITY (2079-2081, 2937). Asking
            // every reviewer of every restaurant to promise not to mention their
            // treatment is noise that teaches people to tick boxes without
            // reading them, which is how a consent surface stops meaning
            // anything. The classification is read through `PhiAnalysisConsent`
            // rather than off the business here, so this page and the gate
            // cannot disagree about who is a covered entity.
            //
            // ⚠️ A HIDDEN BOX IS NOT A TICKED ONE. An ordinary tenant's reviews
            // were never withheld, so this absence permits nothing new; and a
            // forged POST carrying the field writes a record that grants
            // nothing, because `withholds()` answers false for that tenant
            // before it ever looks for one.
            'showPhiAnalysisConsent' => app(PhiAnalysisConsent::class)
                ->handlesHealthInformation($location->business_id),
            'phiAnalysisLabel' => ConsentDisclosure::phiAnalysisLabel(),
            'phiAnalysisText' => ConsentDisclosure::phiAnalysisText(),
        ]);
    }

    /**
     * Post/Redirect/Get, so a refresh cannot resubmit.
     *
     * The form is a plain HTML POST rather than a fetch, and that is two
     * constraints agreeing. BUILD-PLAN §2.6.3 requires slice F's picker to render
     * with JavaScript disabled and the picker lives on the screen after this
     * redirect, so the path to it has to work without JavaScript. And a
     * JavaScript-rendered thank-you would be decision 258's two-renderers problem
     * on the page that tells a customer what happened to their own feedback.
     */
    public function store(StoreFeedbackRequest $request, FeedbackSubmission $submission): RedirectResponse
    {
        $page = $this->page($request);

        [$termsUrl, $privacyUrl] = $this->legalUrls();

        $review = $submission->submit($page, $this->location($request), new FeedbackInput(
            rating: (int) $request->validated('rating'),
            comment: $request->string('comment')->value() ?: null,
            name: $request->string('name')->value() ?: null,
            email: $request->string('email')->value() ?: null,
            phone: $request->string('phone')->value() ?: null,
            smsConsent: $request->boolean('sms_consent'),
            emailConsent: $request->boolean('email_consent'),
            phiAnalysisConsent: $request->boolean('phi_analysis_consent'),
            proof: [
                'url' => $request->url(),

                // Never the address itself (`29` §2 rule 21). ConsentCapture
                // refuses a raw IP at any depth anyway, which is the layer that
                // makes this a rule rather than a habit.
                'ip_hash' => HashedIp::of($request),
                'user_agent' => $this->safeUserAgent($request),
                'locale' => app()->getLocale(),

                // `24` §3.2's non-negotiable is the *live links*, not the label
                // text — and which URLs they actually pointed at when this
                // person consented was captured nowhere. Both routes are
                // allowlisted and stable, so recording them is exact rather
                // than reconstructed from `legal.document`'s current behaviour
                // at read time, months later.
                'terms_url' => $termsUrl,
                'privacy_url' => $privacyUrl,
            ],
        ));

        // The clock is consumed, so a second POST on the same page load is
        // refused by the timing check rather than merely deduplicated.
        $request->session()->forget('feedback.started_at.'.$page->slug);

        // WHICH REVIEW THE PICKER IS FOR. Kept out of the URL, where it would be
        // a public, guessable handle on a tenant-owned row.
        //
        // ⚠️ PUT, NOT FLASH — AND THAT IS A CHANGE SLICE F HAD TO MAKE. The
        // original `->with()` survives exactly one request, which is the thanks
        // GET. Every destination tap after that is a *second* request, so a
        // flashed id is gone before the first button can be pressed. Persisting
        // it is also what `24` §2.3.3 actually wants: a customer who opens
        // Google, comes back and picks a second destination must find the picker
        // still there.
        //
        // Bounded by an expiry and cleared by show(); see INVITE_MINUTES.
        $request->session()->put(self::INVITE_KEY.$page->slug, [
            'review_id' => (int) $review->id,
            'expires_at' => now()->addMinutes(self::INVITE_MINUTES)->getTimestamp(),
        ]);

        return redirect()->route('feedback.thanks', ['slug' => $page->slug]);
    }

    /**
     * The post-submit screen, and FPR-04b's picker.
     *
     * Deliberately tolerant of an empty session: a refresh after the window, a
     * direct visit, or a browser that dropped the cookie renders a plain
     * thank-you rather than an error. The customer's feedback is already saved,
     * and FPR-04b's own acceptance criterion is that abandoning after this point
     * still leaves the first-party review intact — so there is nothing here
     * worth failing a page over.
     *
     * BOTH BLOCKS CAN RENDER AT ONCE, and that combination is the interesting
     * one rather than an edge case. A 3-star review at a tenant with Trustpilot
     * enabled is InvitedAndTriaged: Trustpilot's terms require inviting every
     * customer (decision 305), so the recovery message and the picker are both
     * true at the same time. Recovery goes first — the low rating is the thing
     * that most needs answering — and the picker sits under it rather than being
     * suppressed, because suppressing it would quietly withhold an invitation
     * that platform requires us to extend.
     *
     * THE BOOKING BLOCK IS T176 P7, AND IT IS THE ONE THING ON THIS SCREEN THAT
     * IS WITHHELD AFTER TRIAGE. `BookingLink` is asked for it only on the
     * ordinary path: a customer who has just told this business their visit went
     * badly is not the customer to ask to book another one, and `22`'s outcome
     * rule cuts against a "book again" invitation sitting under "we hear you".
     * ⚠️ **THAT IS NOT THE PICKER'S REASONING AND MUST NOT BE READ AS IT.** The
     * picker survives triage because another platform's terms require it to; a
     * booking link is the tenant's own, nobody's terms compel it, and the
     * conservative choice is ours to make.
     *
     * ⚠️ **NO BOOKING LINK SET MEANS NO BLOCK AT ALL (R13)** — no placeholder and
     * no invented address. `BookingLink` answers `null` and this screen renders
     * exactly what it rendered before P7.
     *
     * ⚠️ **AND IT IS A PLAIN ANCHOR, NOT A SHORT LINK (R14).** The customer is
     * already on this page; nothing was sent, so there is no send to mint a token
     * per. `BookingLink`'s docblock carries the whole argument.
     */
    public function thanks(Request $request, ReviewInvites $invites, BookingLink $booking): View
    {
        $page = $this->page($request);
        $location = $this->location($request);
        $review = $this->invitedReview($request, $page);
        $triaged = $review?->routing_decision?->triaged() ?? false;

        return view('feedback.thanks', [
            'businessName' => $this->businessName($location),
            'triaged' => $triaged,
            'options' => $review === null ? [] : $invites->offerFor($review, $location, $page),
            'booking' => $triaged ? null : $booking->forPublicPage(),
        ]);
    }

    /**
     * A customer tapped a destination: record it and send them there.
     *
     * A SERVER-SIDE REDIRECT RATHER THAN A DIRECT LINK PLUS A BEACON, because
     * BUILD-PLAN §2.6.3 requires this picker to work with JavaScript disabled
     * and a `fetch()` beacon records nothing when it is. That hole would be
     * silent: the customer's journey is identical either way, so the only
     * evidence would be a click count quietly missing everyone who blocks
     * scripts.
     *
     * `{destination}` IS A BACKED ENUM, SO THE URL SPACE IS THE CATALOGUE.
     * Laravel's implicit binding 404s any value that is not a case, so
     * `/f/{slug}/to/tripadvisor` is a 404 with no code of ours involved and no
     * future "add more review sites" change can arrive through a URL.
     *
     * ⚠️ **`/to/yelp` USED TO 404 HERE AND NO LONGER DOES** — the owner reversed
     * decision 112 at 1160 and Yelp is a case. **That is not a hole**: it lands
     * on `eligible()`'s four gates like every other destination, so a Yelp
     * hand-off happens only if Yelp was routed at submission *and* is currently
     * enabled with a confirmed link. Guessing the URL gets a redirect to the
     * thanks screen and no click row, which is what the replaced build-failing
     * test now asserts (1161).
     *
     * EVERY FAILURE LANDS ON THE THANKS SCREEN, never an error page. No session,
     * an expired window, a destination the rating never cleared, one the owner
     * has since disabled, a link that will not resolve — all of them mean the
     * customer goes back to a page that thanks them, and no click is recorded,
     * because no hand-off happened.
     *
     * ⛔ **A MACHINE'S FETCH IS REDIRECTED AND NOT RECORDED** (2920–2925). This is
     * a GET that writes, so a carrier link scanner, a mail provider's link
     * checker or the customer's own browser prefetching the button mints a
     * `destination_clicks` row and the owner-facing sentence "A customer opened
     * Google to post a review" for somebody who did nothing. FetchClassifier —
     * the same one the short-link redirector uses, T137 §3 rail 4 — answers only
     * what the request declared about itself, and a reason means the hand-off
     * resolves its URL through every ordinary gate and writes none of the three
     * rows.
     *
     * ⚠️ **THE SESSION WAS TAKEN FOR A GUARD HERE AND IS NOT ONE.** The route
     * comment reasoned that this URL is reachable only behind a session the
     * visitor must have submitted the form to hold. That is true and it does not
     * help: a prefetch or a prerender is issued by the customer's *own* browser
     * with the customer's *own* cookie, and an in-browser scanner sees the same
     * session. A HEAD from a link checker on a shared link is the other half.
     *
     * ⛔ **AND A CLASSIFIED FETCH IS NEVER REFUSED.** No 4xx, no challenge, no
     * blank page. A link checker answered 4xx can mark the URL bad and suppress
     * delivery of the whole message, so the customer never sees the invite —
     * strictly worse than the spurious row this removes. The redirect and the
     * `Referrer-Policy` header are identical to a person's.
     *
     * ⚠️ `$slug` IS DECLARED BECAUSE IT MUST BE, NOT BECAUSE IT IS READ. Laravel
     * splices container-resolved arguments into the *positional* list of route
     * parameters, so an undeclared `{slug}` still occupies a slot: with only
     * `$request` and `$destination` declared, this method receives the slug
     * string as argument two and dies with a TypeError naming the enum. It looks
     * exactly like implicit enum binding having failed, and it is not — the
     * binding is fine, the alignment is not. show() and thanks() get away with
     * omitting it only because a trailing extra argument to a PHP function is
     * discarded, and this route is the first here with two parameters. The page
     * that follows is the authority on the slug; this parameter is alignment.
     */
    public function destination(
        Request $request,
        string $slug,
        ReviewDestination $destination,
        ReviewInvites $invites,
        FetchClassifier $classifier,
    ): RedirectResponse {
        $page = $this->page($request);
        $location = $this->location($request);
        $review = $this->invitedReview($request, $page);

        $url = $review === null
            ? null
            : $invites->handOff(
                $review,
                $location,
                $page,
                $destination,
                // Null for a person, and then this call has changed nothing.
                $classifier->discardReasonFor($request),
            );

        if ($url === null) {
            return redirect()->route('feedback.thanks', ['slug' => $page->slug]);
        }

        // The destination platform has no business learning which business's
        // feedback page this customer came from — the slug is a stable, permanent
        // identifier for a tenant's location, and this is a cross-origin
        // navigation to a third party we have no agreement with.
        return redirect()->away($url)->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * The review this session is allowed to offer destinations for, if any.
     *
     * THREE THINGS ARE CHECKED AND NONE OF THEM IS TRUST IN THE SESSION VALUE.
     * The id is a hint about which row to look at, never proof it may be used:
     * the window must still be open, the row must still exist, and it must load
     * *under the tenant global scope* that ResolveFeedbackPage established from
     * the slug. ReviewInvites then re-checks tenant, location and page before it
     * will offer or hand off anything — the id alone opens no door.
     *
     * The key is forgotten once expired rather than merely ignored, so a stale
     * entry does not sit in the session for the life of the cookie.
     */
    private function invitedReview(Request $request, FeedbackPage $page): ?Review
    {
        $key = self::INVITE_KEY.$page->slug;
        $invite = $request->session()->get($key);

        if (! is_array($invite) || ! isset($invite['review_id'], $invite['expires_at'])) {
            return null;
        }

        if (! is_int($invite['review_id']) || ! is_int($invite['expires_at'])) {
            return null;
        }

        if ($invite['expires_at'] < now()->getTimestamp()) {
            $request->session()->forget($key);

            return null;
        }

        return Review::query()->find($invite['review_id']);
    }

    /**
     * The page ResolveFeedbackPage put on the request.
     *
     * Typed accessors rather than reading the attribute bag inline, because the
     * bag returns mixed and Larastan level 8 is right to insist. The exception
     * is unreachable while the middleware is attached; it exists so that
     * detaching the middleware fails loudly rather than rendering a page with no
     * tenant.
     */
    private function page(Request $request): FeedbackPage
    {
        $page = $request->attributes->get(ResolveFeedbackPage::PAGE);

        return $page instanceof FeedbackPage
            ? $page
            : throw new RuntimeException('ResolveFeedbackPage did not run on this route.');
    }

    private function location(Request $request): Location
    {
        $location = $request->attributes->get(ResolveFeedbackPage::LOCATION);

        return $location instanceof Location
            ? $location
            : throw new RuntimeException('ResolveFeedbackPage did not run on this route.');
    }

    /**
     * The name shown to the customer, wherever this controller renders a view.
     *
     * Extracted so show() and thanks() read the location's name the same way
     * rather than one inlining it and the other drifting.
     */
    private function businessName(Location $location): string
    {
        return $location->businessName();
    }

    /**
     * Terms, then Privacy — the pair show() and store() both need, read the
     * same way so the URL a customer saw and the one recorded in their consent
     * proof cannot drift apart.
     *
     * @return array{0: string, 1: string}
     */
    private function legalUrls(): array
    {
        return [
            route('legal.document', ['doc' => 'terms']),
            route('legal.document', ['doc' => 'privacy']),
        ];
    }

    /**
     * The visitor's user agent, made safe to carry into a consent record.
     *
     * ConsentCapture::rejectRawIp() walks every string in the proof at every
     * depth and throws on anything that validates as an IP address — and
     * throws *inside* DB::transaction() on this public, unauthenticated POST,
     * which loses the customer's feedback along with the request. A
     * `User-Agent` header is attacker-controlled input, so `User-Agent:
     * 1.2.3.4` or `::1` reaches that check. Truncated to a sane length for the
     * same reason: nothing bounds what a client sends in this header.
     *
     * NULL IS HANDLED TOO. `(string) $request->userAgent()` on a missing
     * header is `''`, which satisfies ConsentCapture's key-presence check and
     * stores an empty field that looks like proof rather than the absence it
     * actually is.
     */
    private function safeUserAgent(Request $request): string
    {
        $agent = $request->userAgent();

        if ($agent === null || trim($agent) === '') {
            return '(no user agent sent)';
        }

        $agent = mb_substr(trim($agent), 0, self::MAX_USER_AGENT_LENGTH);

        return filter_var($agent, FILTER_VALIDATE_IP) !== false
            ? '(user agent looked like an IP address; not stored)'
            : $agent;
    }
}
