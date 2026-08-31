<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\VerifiesWebhookSenders;
use App\Enums\PlatformHealthSignal;
use App\Enums\WebhookVerification;
use App\Services\Mail\GooglePushTokenVerifier;
use App\Services\Mail\SnsMessageVerifier;
use App\Services\Ops\WebhookMaterialCensus;
use InvalidArgumentException;

/**
 * What one webhook verifier judges its callers with — declared by the verifier,
 * never listed anywhere else (11640–11651).
 *
 * ## ⛔ Why this exists at all: the absence was knowable at rest and nothing asked
 *
 * ⛔ **EVERY VERIFIER BEHIND A `webhooks/` ROUTE FAILS CLOSED ON MISSING
 * MATERIAL, AND EVERY ONE OF THEM ONLY FINDS OUT ON A REQUEST.** With
 * `infobip_webhook_secret` unset — the state of the running install until
 * 2026-08-29, when it was set (12276) —
 * `InfobipWebhookVerifier::verify()` returns false before the body is read,
 * every genuine inbound delivery is answered 401, and **a customer who texts
 * STOP is never recorded and never suppressed**. Nothing counted it, because
 * `CredentialStore::resolve()` records
 * {@see PlatformHealthSignal::CredentialAbsent} on a **read**, and
 * the only thing in `app/` that reads that key is the verifier itself. So
 * `PlatformHealthChecks::checkCredentialFaults()` iterated zero sources and the
 * five-minute sweep raised nothing, for ever, until an inbound message arrived.
 *
 * ⚠️ **THE FIRST PERSON TO TEXT STOP IS THE ONE WHO PAYS**, and that is true of
 * all four credential-backed endpoints rather than only that one.
 *
 * ## ⛔ Three shapes, not one, and a check written against `credentials.*`
 * alone would cover half of them
 *
 * The endpoints do not verify with one kind of thing:
 *
 *   - **Four `CredentialStore` rows** — `stripe_webhook_secret`,
 *     `authorize_net_signature_key`, `infobip_webhook_secret` (three endpoints)
 *     and `zernio_webhook_secret`.
 *   - **Two `config()`-only gates** — {@see SnsMessageVerifier}
 *     fails closed on an empty `platform_mail.sns.topic_arns`, and
 *     {@see GooglePushTokenVerifier} on a missing audience or
 *     service account. **Neither is a credential row and neither ever will be**;
 *     both fetch their verifying keys over the network, and what an operator
 *     supplies is the identity those keys are checked *against*.
 *
 * ⛔ **SO THIS CARRIES BOTH AND A CENSUS ASKS THE VERIFIER RATHER THAN THE
 * STORE.** An instrument whose population is narrower than the question it
 * appears to answer is this codebase's own recurring shape: a check over the
 * four credential rows would print a clean board while the two mail endpoints
 * refused everything.
 *
 * ## ⚠️ What it deliberately does not carry
 *
 * ⛔ **NO CONSEQUENCE SENTENCE, AND THAT IS 8460 RATHER THAN BREVITY.**
 * {@see CredentialManifest}'s `degradation` line already says what a person
 * meets when each of the four credential rows is absent, and it is **rendered to
 * an operator on the Ops board**. A second sentence here would be a copy that
 * drifts, and the copy nobody exercises is the one that goes wrong
 * (`CredentialStore::sourceOf()`'s own history). The one thing that is true of
 * every endpoint in this population — *every delivery is refused before its body
 * is read, including genuine ones* — is stated once, by the census that prints
 * it.
 *
 * ⛔ **AND NO "IS THIS VENDOR IN USE" FLAG.** Whether a deployment has connected
 * a vendor is a fact about that deployment and not about a verifier, and
 * {@see WebhookVerification} is right that a fresh install with no
 * Stripe account is not a fault. The census reports state and makes no claim of
 * fault; see {@see WebhookMaterialCensus} for why the bell
 * that would need such a flag was refused rather than guessed at.
 */
final readonly class WebhookMaterial
{
    /**
     * @param  list<string>  $credentials  {@see CredentialManifest} keys
     * @param  list<string>  $configuration  `config()` paths that must resolve
     *                                       to a non-empty string or array
     */
    private function __construct(
        public array $credentials,
        public array $configuration,
    ) {}

    /**
     * Material this platform holds itself — a shared secret an HMAC is computed
     * with.
     *
     * @param  list<string>  $keys
     */
    public static function credentials(array $keys): self
    {
        return new self(self::refuseAnEmptyDeclaration($keys), []);
    }

    /**
     * Material an operator configures but which is not a secret of ours — an
     * allowlisted topic, an audience, the identity of a service account.
     *
     * @param  list<string>  $paths
     */
    public static function configuration(array $paths): self
    {
        return new self([], self::refuseAnEmptyDeclaration($paths));
    }

    /**
     * ⛔ **A VERIFIER THAT DECLARES NOTHING IS THE VACUITY THIS WHOLE
     * DECLARATION EXISTS TO REFUSE, AND IT IS REFUSED AT CONSTRUCTION RATHER
     * THAN BY A LINT.** An empty {@see WebhookMaterial} would make a census row
     * read *"nothing is missing"* about an endpoint that verifies with nothing —
     * the reassuring direction, which is the direction these failures always
     * take. Refusing it here means no {@see VerifiesWebhookSenders} can exist in
     * that state at all, so the lint over the population does not have to be the
     * thing that catches it.
     *
     * @param  list<string>  $names
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    private static function refuseAnEmptyDeclaration(array $names): array
    {
        $named = array_values(array_filter($names, static fn (string $name): bool => trim($name) !== ''));

        if ($named === []) {
            throw new InvalidArgumentException(
                'A webhook verifier declared no verifying material. Every verifier behind a '
                .'webhooks/ route judges callers with something an operator has to supply; a '
                .'declaration of nothing would report as configured on every install.'
            );
        }

        return $named;
    }
}
