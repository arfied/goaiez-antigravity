<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sms;

use App\Enums\PlatformHealthSignal;
use App\Http\Controllers\Controller;
use App\Services\Ops\PlatformHealth;
use App\Services\Sms\DeliveryReceipts;
use App\Services\Sms\InfobipWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /webhooks/infobip/delivery` — what happened to the messages we sent.
 *
 * ⚠️ **A SECOND ENDPOINT RATHER THAN A BRANCH INSIDE THE INBOUND ONE**, and the
 * two payloads are the reason: an inbound message and a delivery receipt both
 * arrive as `results[]` and both carry `messageId`, so one handler would have to
 * tell them apart by *guessing from which fields are present*. That guess is
 * wrong the first time Infobip adds a field, and being wrong means either
 * treating a customer's reply as a receipt — dropping a STOP — or treating a
 * receipt as a reply. Two URLs configured in two notification profiles cannot be
 * confused.
 *
 * ⚠️ **THE SAME VERIFIER, AND IT MUST BE.** Receipts and inbound messages are
 * delivered through the same subscription mechanism and signed by the same
 * account key, so a second scheme here would be a second thing to configure and
 * a second thing to get wrong. {@see InfobipWebhookVerifier} carries the
 * citations, including the one that matters most: the signing header name is
 * account-specific, so it is configuration rather than a constant.
 *
 * ⚠️ **THIS ENDPOINT IS LESS DANGEROUS THAN THE INBOUND ONE AND IS GUARDED
 * IDENTICALLY ANYWAY.** A forged receipt can mark somebody's message delivered
 * or failed; a forged inbound message can suppress any phone number on the
 * platform. Relaxing the guard here because the blast radius is smaller would
 * mean two verification paths, and the weaker one is the one somebody copies
 * next time.
 *
 * Every branch is a decision about whether Infobip should try again:
 *
 *   401  the signature did not verify, or none is configured.
 *   422  the payload was not a shape we can read.
 *   200  applied, or deliberately not applied. Both are finished — see
 *        {@see DeliveryReceipts} for the four ordinary reasons a receipt has
 *        nowhere to land, none of which a retry would fix.
 */
final class InfobipDeliveryController extends Controller
{
    public function __invoke(
        Request $request,
        InfobipWebhookVerifier $verifier,
        DeliveryReceipts $receipts,
        PlatformHealth $health,
    ): JsonResponse {
        // Before the body is read as anything but bytes — the signature covers
        // the exact bytes Infobip sent.
        if (! $verifier->verify($request)) {
            // Counted (T176 P23). Refusing every genuine receipt leaves every
            // delivery rate reading zero — a failure that shows up on the
            // sending dashboard as a quiet week rather than as an error.
            //
            // ⛔ **THIS SAID THE ROWS SIT AT `Queued` AND THEY SIT AT `Sent` —
            // CORRECTED 2026-08-22 (7481).** `SendSettlement::settle()` moves
            // the row when the carrier names the message, long before any
            // receipt arrives, so a webhook that verifies nothing leaves a
            // `MessageLog` full of ordinary "Sent" rows. **Nobody looking for a
            // stuck queue would find this.** The same false sentence was written in
            // five other artefacts, three of them about a different cause; see
            // `InfobipClient` and 7481 for the enumeration.
            //
            // ⛔ **AND THE COST IS LARGER THAN THE DASHBOARD**: with `delivered`
            // frozen at zero the automatic complaint trip and 2102's
            // platform-wide halt cannot fire at all. A misconfigured signing key
            // disables the containment, not just the reporting.
            $health->recordFailure(PlatformHealthSignal::WebhookSignature, 'infobip_delivery');

            return response()->json(['error' => 'unverified'], 401);
        }

        $reports = $request->input('results');

        if (! is_array($reports)) {
            return response()->json(['error' => 'unreadable payload'], 422);
        }

        $applied = 0;

        foreach ($reports as $report) {
            if (! is_array($report)) {
                continue;
            }

            $id = $report['messageId'] ?? null;

            if (! is_string($id) || $id === '') {
                // One unreadable entry must not cost the receipts beside it in
                // the same batch — the inbound endpoint's rule, for its reason.
                continue;
            }

            $group = $report['status']['groupName'] ?? null;
            $reference = $report['callbackData'] ?? null;
            $error = $report['error']['name'] ?? null;

            $moved = $receipts->apply(
                providerMessageId: $id,
                groupName: is_string($group) ? $group : null,
                reference: is_string($reference) ? $reference : null,
                errorName: is_string($error) ? $error : null,
            );

            if ($moved) {
                $applied++;
            }
        }

        return response()->json(['applied' => $applied]);
    }
}
