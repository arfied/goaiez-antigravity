<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\GbpRequestFailed;
use App\Services\Gbp\GbpReplyReceipt;
use App\Services\Gbp\GbpReviewPage;

/**
 * Reading a location's Google reviews, whoever we are reading them through.
 *
 * WHY AN INTERFACE, WHEN THERE IS ONE IMPLEMENTATION. Google Business Profile
 * API access is an application with a lead time — the retired direct Google Business client (deleted in SIXTY-56)'s
 * docblock records the terms: a new Cloud project sits at **0 QPM** until
 * approval, and there is no sandbox. Ours is applied for and pending. Zernio
 * holds its own approved project, so a tenant can OAuth into *their* app today
 * and we read reviews while our own application clears.
 *
 * ⚠️ **THIS IS NOT A SEAM WITH AN EXPIRY DATE, AND AN EARLIER VERSION OF THIS
 * DOCBLOCK SAID IT WAS.** It read *"when our approval lands, the retired direct Google Business client (deleted in SIXTY-56)
 * implements this interface and the swap is a container binding"*. The owner
 * settled decision 530 the other way on 2026-08-04 (decisions 546–548): **both
 * implementations stay live, permanently**, Zernio as the fallback and direct
 * access as the destination, and a location sits on one or the other. So the
 * choice is **per location, resolved at call time from a stored provider** — not
 * a binding chosen once in a service provider. A reader who implements the
 * container-swap version builds something that cannot express the state the
 * business has actually asked for: two cohorts at once. The provider is a column
 * on the same per-location store decision 531 says has to exist before this
 * client gets a caller — that store now carries two things, not one.
 *
 * ⚠️ **AND THE MIGRATION IS NOT OURS TO PERFORM.** A tenant's connection lives in
 * Zernio's OAuth app, not ours; moving to direct access means that tenant
 * re-authorises Google. That is a consent event only the tenant can carry out,
 * so migration is a **button they press**, one at a time, and never a bulk flip
 * on our side. Two consequences worth stating because neither is obvious from
 * the interface: the Zernio subprocessor relationship is **permanent rather than
 * transitional** (`docs/SUBPROCESSOR-INVENTORY.md` §2 is not a temporary row),
 * and its per-account cost is ongoing opex rather than a bridging expense.
 *
 * ## Replying — decision 534's condition is met, and this is the evidence
 *
 * ✅ **THE ENDPOINT IS CONFIRMED AND `replyToReview()` BELOW IS THE RESULT
 * (decision 2330).** 534 refused to guess one and said what would lift the
 * refusal: *"confirm the endpoint, then add it to the interface and both
 * implementations in one change."* It has been confirmed against the raw
 * artefact rather than a summary or a marketing page — Zernio's published
 * OpenAPI document, read 2026-08-11:
 *
 *     POST /v1/inbox/reviews/{reviewId}/reply
 *       path   reviewId  required, "URL-encoded for Google Business"
 *       body   { accountId (required), message (required) }
 *       200    { status, reply:{ id, text, created }, platform }
 *       401    Unauthorized       403  "Inbox addon required"
 *
 * ⚠️ **534 WAS RIGHT ON THE FACTS IT HAD AND IS SUPERSEDED RATHER THAN
 * CORRECTED.** The endpoint sits under `reviews`, not under
 * `platforms/google-business/*` — the same place decision 533 already found the
 * *read* hiding, one namespace away from where the capability logically belongs.
 * Whoever wrote 534 looked where a Google reply would live and found a delete
 * and nothing else, which is exactly the shape 533 warned about. The lesson is
 * not "read harder"; it is that this vendor files Google capabilities under
 * `inbox` and the next missing one is probably there too.
 *
 * ⚠️ **THERE IS A SECOND, WRONGER ENDPOINT AND IT LOOKS LIKE THE RIGHT ONE
 * (2331).** `POST /v1/accounts/{accountId}/gmb-reviews/{reviewId}/reply` is
 * documented under *Google Business Profile*, takes a plain `comment`, and
 * reads as the obviously Google-shaped choice. Two things make it wrong here,
 * and both fail silently:
 *
 *   - It wants *"the review ID portion (e.g. `AIe9_BGx1234567890`), not the
 *     full resource name"*, while `GET /inbox/reviews` documents its `id` as
 *     **the full resource name** `accounts/*​/locations/*​/reviews/*` — which is
 *     what `reviews.google_review_id` therefore holds. Feeding a stored id
 *     straight in is a 404; splitting it is a parser over somebody else's
 *     identifier format.
 *   - The reply lands on *"the account's currently selected location (set via
 *     `/v1/accounts/{accountId}/gmb-locations`)"* — **server-side state we
 *     never set**. For a tenant with more than one location that is a reply
 *     published on whichever listing was selected last, under their name, with
 *     nothing in our logs to show it went to the wrong one.
 *
 * The inbox endpoint has neither problem: it takes the same id our reader
 * already stores, and the id encodes the location.
 *
 * ## What is deliberately still not here
 *
 * **Deleting a reply.** Both providers document it — Zernio as
 * `DELETE /v1/inbox/reviews/{reviewId}/reply`, Google as `deleteReply` on the
 * same v4 path. Nothing in this application removes a published reply, so it
 * would be decision 272's shape on the eleventh count: a client method with no
 * caller. It arrives with the screen that needs it.
 *
 * **Posting to the profile, media, attributes, services.** Zernio exposes all
 * of them. None has a caller in this application yet, and a client method with
 * no caller is the shape this codebase has now hit eleven times (decision 272's
 * family, most recently `plugins` at 399). They arrive with the automation that
 * needs them.
 *
 * ## What a caller must know
 *
 * Google reviews are a **separate pipeline** from first-party feedback and the
 * two are never merged (`29` §2 rule 1). Nothing read through here may be held,
 * hidden, approved or moderated: `AnalyzeReviewJob` refuses a Google row by
 * construction, and `ReviewDisplay` refuses one in both directions (decision
 * 408). Ingest sets `display_on_website` directly — decision 409 is explicit
 * that routing Google through the decision path is the wrong repair.
 */
interface GbpClient
{
    /**
     * One page of a location's Google reviews, newest first.
     *
     * Cursor-paginated rather than offset-paginated because both providers are:
     * Zernio returns an opaque `cursor`, Google a `nextPageToken`. Neither is
     * seekable, so a caller resumes by storing the cursor, never by counting.
     *
     * @param  string  $accountRef  Opaque to us. For Zernio it is their
     *                              `accountId` for the tenant's connected Google
     *                              Business account; for direct access it will
     *                              be a `locations/{id}` resource name. **It is
     *                              not a `place_id`** and the two must never be
     *                              interchanged — `place_id` comes from Places,
     *                              needs no approval, and is what makes the
     *                              whole no-GBP-access path possible.
     * @param  string|null  $cursor  Null for the first page.
     * @param  int  $limit  Reviews per page. Clamped by the implementation to
     *                      whatever the provider actually accepts.
     *
     * @throws GbpRequestFailed
     */
    public function reviews(string $accountRef, ?string $cursor = null, int $limit = 25): GbpReviewPage;

    /**
     * Whether the tenant's connection can currently be read from.
     *
     * A connection dies quietly: the tenant revokes access in their Google
     * account, or changes the password, and the next read returns an
     * authorisation failure that looks exactly like a misconfigured key. This
     * exists so an automation can tell "the tenant unplugged us" from "we are
     * broken" **before** it decides between `execute()` and `handoff()`.
     *
     * Returns false rather than throwing on a dead connection — that is the
     * answer, not a failure. It still throws when the question could not be
     * asked at all.
     *
     * @throws GbpRequestFailed
     */
    public function connectionHealthy(string $accountRef): bool;

    /**
     * Publish the owner's reply to one review, creating or replacing it.
     *
     * ⚠️ **REPLACING, NOT ONLY CREATING, AND BOTH VENDORS SAY SO IN THE SAME
     * WORDS.** Google's `updateReply` is *"updates the reply to the specified
     * review. A reply is created if one does not exist"*; Zernio's own note is
     * *"calling this endpoint a second time on the same review overwrites the
     * previous reply (PUT semantics on Google's side)"*. That is what makes a
     * queue retry safe — a redelivered job overwrites its own reply with the
     * same text rather than stacking a second one — and it is also why nothing
     * may call this without a recorded decision behind the text: there is no
     * *"only if absent"* mode to fall back on, so a careless second call
     * silently replaces whatever the owner last published.
     *
     * ⚠️ **A SUCCESSFUL RETURN IS AN ACKNOWLEDGEMENT, NOT A PUBLICATION.**
     * Google moderates replies — its `ReviewReply.reviewReplyState` is
     * `PENDING`, `REJECTED` or `APPROVED` — and Zernio's relay carries none of
     * those states back. Callers must not render "published" as a fact about
     * the listing. The receipt records what the provider actually said.
     *
     * ⚠️ **THIS IS THE ONE METHOD ON THIS INTERFACE THAT WRITES TO SOMEBODY
     * ELSE'S PUBLIC PROPERTY**, so `29` §2 rule 42's audit obligation attaches
     * to its caller rather than to it: a client cannot know who authorised the
     * text it was handed, and a client that recorded an actor it inferred would
     * be worse than one that records nothing.
     *
     * @param  string  $accountRef  As {@see reviews()} — from the connection
     *                              store, never from a request or a job payload.
     * @param  string  $externalReviewId  The provider's own review id, exactly
     *                                    as the read path returned it and as
     *                                    `reviews.google_review_id` stores it.
     *                                    ⚠️ For Google through Zernio this is
     *                                    the **full resource name**
     *                                    `accounts/*​/locations/*​/reviews/*` and
     *                                    therefore contains slashes; escaping it
     *                                    for whatever transport is used is the
     *                                    implementation's job, never the
     *                                    caller's.
     * @param  string  $comment  The reply text, already decided and already
     *                           through the guardrails. Never empty.
     *
     * @throws GbpRequestFailed
     */
    public function replyToReview(string $accountRef, string $externalReviewId, string $comment): GbpReplyReceipt;
}
