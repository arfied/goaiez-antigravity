<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Mail\GooglePushTokenVerifier;
use App\Services\Mail\SnsMessageVerifier;

/**
 * What happened when this platform tried to establish who sent a webhook
 * (9380–9394).
 *
 * ⛔ **THREE OUTCOMES BECAUSE THERE ARE THREE, AND THE THIRD WAS BEING REPORTED
 * AS THE SECOND.** Both verifiers that return this fetch signing material over
 * the network before they can judge anything, and both used to answer a bare
 * `bool` — so *"the sender's signature does not match"* and *"we could not
 * obtain the key to check it with"* arrived at the caller as the same value,
 * were counted as {@see PlatformHealthSignal::WebhookSignature}, and reached an
 * operator under the sentence *"the usual cause is a missing or rotated signing
 * secret"*. **A vendor outage wearing the remedy for our own misconfiguration,
 * at three in the morning.**
 *
 * ⚠️ **FAIL-CLOSED IS STRUCTURAL RATHER THAN REMEMBERED.** Only
 * {@see self::Verified} is a pass, and it is the only case a caller may act on;
 * a `match` that forgets an arm is an `UnhandledMatchError` rather than a silent
 * acceptance, which is what a nullable `bool` would have been.
 *
 * ⛔ **THE TWO IMPLEMENTATIONS DO NOT ANSWER {@see self::KeysUnavailable} FOR
 * THE SAME SET OF FAILURES, AND THAT ASYMMETRY IS DELIBERATE — IT IS ABOUT WHO
 * CHOSE THE URL.** {@see GooglePushTokenVerifier} fetches an address held in
 * **our** configuration, so nothing a caller sends can influence it and every
 * failure of that fetch is ours or Google's. {@see SnsMessageVerifier} fetches
 * the address **written inside the message being checked** — constrained to an
 * AWS host and a `.pem`, and still chosen by whoever posted it — so a stranger
 * can name a well-formed URL that does not resolve and manufacture the fault.
 * **What a sender cannot manufacture is a 5xx from AWS's own host**, which is
 * why that is the only arm SNS reports here. See each class for the residual it
 * accepts as a result.
 *
 * ⚠️ **ONLY THE TWO VERIFIERS THAT FETCH RETURN THIS, AND THAT IS NOT AN
 * OMISSION.** Every other verifier compares an HMAC over the request body
 * against a secret this platform already holds — no fetch, no third outcome, nothing that
 * could be unavailable. A verifier that gains a fetch gains this return type
 * with it.
 */
enum WebhookVerification
{
    /**
     * The sender is who they say they are. The only case a caller may act on.
     */
    case Verified;

    /**
     * We checked, and it did not verify — a bad signature, an unknown topic, a
     * claim that is not ours, or a configuration this install does not have.
     *
     * ⚠️ **THE UNCONFIGURED INSTALL IS HERE RATHER THAN IN
     * {@see self::KeysUnavailable}**, and both verifiers say why: an empty topic
     * allowlist or a missing audience is a decision this deployment has not made
     * yet, not an outside service failing. Counting it as a vendor fault would
     * ring a bell on every fresh install about a vendor that is working.
     */
    case Refused;

    /**
     * We could not obtain the material needed to check it at all, so no
     * judgement was made about the sender.
     *
     * ⛔ **THE MESSAGE MAY WELL HAVE BEEN GENUINE, WHICH IS WHY THE CALLER ASKS
     * FOR IT AGAIN RATHER THAN REFUSING IT.** Both endpoints answer a `401` on
     * {@see self::Refused} precisely so a forgery is not retried for days; a
     * message nobody judged is the opposite case, and discarding it is how a
     * genuine bounce, complaint or mailbox notification is lost for good.
     */
    case KeysUnavailable;
}
