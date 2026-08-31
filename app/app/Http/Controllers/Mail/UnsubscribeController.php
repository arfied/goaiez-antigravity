<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mail;

use App\Enums\OptOutScope;
use App\Enums\SuppressionReason;
use App\Http\Controllers\Controller;
use App\Services\Consent\ConsentService;
use App\Services\Mail\UnsubscribeClaim;
use App\Services\Mail\UnsubscribeLinks;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The recipient-facing opt-out — T176 P21, RFC 8058.
 *
 * ## Two verbs, and the difference between them is the whole design
 *
 * ⛔ **A GET MUST NEVER UNSUBSCRIBE ANYBODY.** Every URL in an email is fetched
 * by things that are not the recipient: mailbox providers scanning for malware,
 * corporate link rewriters, browser prefetchers, and the recipient's own client
 * warming a preview. A GET that wrote would opt people out of mail they wanted
 * and it would be **invisible in testing** — every one of those fetches looks
 * exactly like a person clicking. Decision 2920 records this application paying
 * that exact cost on `/f/{slug}/to/{destination}`, where a link checker's HEAD
 * minted a full destination-click record; the mitigation there was a fetch
 * classifier, because the GET could not be given up. Here it can.
 *
 * ⚠️ **AND THE POST MUST NOT REQUIRE A LOGIN, A SESSION, A CSRF TOKEN OR A
 * CONFIRMATION SCREEN.** RFC 8058 §3.1: the mail client posts
 * `List-Unsubscribe=One-Click` **unattended**, from its own infrastructure, with
 * no cookie and nothing to confirm with. A confirmation step is precisely what
 * the RFC exists to remove, and CAN-SPAM §7704(a)(3)(A) independently forbids
 * requiring anything of the recipient beyond sending the reply.
 *
 * So: `GET` renders a page with a button, for the human who clicked the footer
 * link; `POST` is the endpoint, for the human's button and for the mail client's
 * unattended request alike. They are the same URL, which is what lets one token
 * serve the header and the footer.
 *
 * ## The tenant arrives with the token
 *
 * `ShortLinkController`'s situation and its ordering: resolve, then establish,
 * then everything else. `suppression_list` is tenant-owned and RLS-`FORCE`d, and
 * there is nothing on this request — no host, no slug, no session — to resolve a
 * tenant from except the sealed claim itself.
 *
 * ## Every failure gives the same answer
 *
 * ⛔ **NO "THIS LINK HAS EXPIRED" PAGE AND NO DISTINGUISHABLE ERROR.** Forged,
 * expired, edited and never-valid are one response, for `ShortLinkController`'s
 * reason. The POST goes further: **it answers 200 whether or not anything was
 * suppressed**, so holding a token is not a way to ask whether an address is on
 * a list — and a client retrying a delivery it did not see the answer to gets
 * the same 200 the first attempt got.
 */
final class UnsubscribeController extends Controller
{
    /**
     * The page a person reaches by clicking the footer link.
     *
     * ⚠️ **IT READS NOTHING AND WRITES NOTHING.** Opening the token is a
     * decryption, not a query, so a prefetch of this URL costs one MAC check —
     * and, more importantly, cannot suppress anybody by accident.
     */
    public function show(string $token, UnsubscribeLinks $links): View
    {
        return view('mail.unsubscribe', [
            'token' => $token,
            'isLive' => $links->open($token) instanceof UnsubscribeClaim,
        ]);
    }

    /**
     * The RFC 8058 endpoint.
     *
     * ⚠️ **THE BODY IS DELIBERATELY NOT INSPECTED.** RFC 8058 says the client
     * sends `List-Unsubscribe=One-Click`; requiring it would refuse the form
     * post from our own confirmation page, and requiring either would make the
     * opt-out conditional on a field the recipient has no way to correct. The
     * token is the authorisation and the intent, and nothing else is asked for.
     */
    public function store(string $token, UnsubscribeLinks $links, ConsentService $consent): Response
    {
        $claim = $links->open($token);

        if (! $claim instanceof UnsubscribeClaim) {
            // Same answer as a token that resolved, so a probe learns nothing
            // — and a 200 rather than a 404 because a mail client treats a 4xx
            // as "the unsubscribe failed" and may surface that to a recipient
            // whose problem is a link from 2029, not a broken sender.
            return $this->done();
        }

        Tenancy::actingAs($claim->businessId, function () use ($claim, $consent): void {
            // ⛔ **THROUGH `ConsentService::suppress()`, NEVER A WRITE OF OUR
            // OWN.** It is the one path that writes the tenant's
            // `suppression_list` row, the platform `opt_outs` hash and the
            // `consent.withdrawn` audit entry together, and it is idempotent by
            // construction — a `firstOrCreate` on the live generation, so a
            // retried POST writes nothing and audits nothing. A second store
            // that disagreed with the first would be decision 286's
            // `customers.is_suppressed` rebuilt.
            $consent->suppress(
                identifier: $claim->identifier,
                channel: $claim->channel,
                // ⚠️ **`Stop`, AND THE ENUM NAMES THIS CASE ITSELF**: *"a
                // carrier STOP keyword, an unsubscribe click, or a withdrawal
                // recorded on their behalf"*. It is the liftable class, which
                // is correct — a person who unsubscribes and later gives fresh
                // consent through an operator-recorded START is not a
                // complaint and not a dead mailbox.
                class: SuppressionReason::Stop,
                reason: 'One-click unsubscribe from an email (RFC 8058).',
                // ⚠️ **THE ACTOR IS THE MECHANISM, NOT A PERSON.** `audit_log`
                // wants who did it; the honest answer is that a request holding
                // a valid token did, and naming the recipient here would put a
                // customer identity in the actor column of every opt-out.
                actor: 'mail.one_click_unsubscribe',
                // ⛔ **PLATFORM SCOPE, WHICH IS THE WIDER OF THE TWO AND IS
                // ARGUED RATHER THAN INHERITED FROM THE DEFAULT** (decision
                // 4021). `OptOutScope`'s own rule is that reach follows the
                // sending identity: Lane A's one shared number is platform
                // scope, a tenant's own number is tenant scope. **Every tenant
                // sends from the one platform sending domain** (`goaieasy.net`,
                // decision 5500, superseding 2114's `mail.goaiez.com`), so
                // email today is Lane A's
                // shape exactly — and a complaint about one tenant's message
                // lands on the domain reputation all of them share (2101).
                // Over-suppressing costs a message that was not sent;
                // under-suppressing costs that reputation for everybody at
                // once. ⚠️ **When a tenant sends from their own connected
                // mailbox (2072), that send is Lane B and its opt-out is
                // `Tenant` — the token already carries the business to decide
                // it with.**
                scope: OptOutScope::Platform,
            );
        });

        return $this->done();
    }

    /**
     * The one answer, whatever happened.
     *
     * ⚠️ **A PAGE RATHER THAN A BARE 200, BECAUSE A PERSON MAY BE LOOKING AT
     * IT.** The same response serves the mail client, which ignores the body
     * entirely, and the recipient who pressed the button on the confirmation
     * page — and there is no way to tell the two apart, so there is one answer
     * that has to work for both.
     */
    private function done(): Response
    {
        return response(
            view('mail.unsubscribed')->render(),
            HttpResponse::HTTP_OK,
        );
    }
}
