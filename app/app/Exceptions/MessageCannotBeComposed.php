<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a message cannot be assembled inside the rules it has to obey.
 *
 * ⚠️ **A THROW RATHER THAN A SHORTER MESSAGE, AND THAT IS THE WHOLE POINT.**
 * Every refusal `ReactComposer` raises has an obvious, tempting alternative that
 * produces *something* — truncate to 159, drop the opt-out sentence, keep the
 * link and lose the greeting, send the template with `{name}` still in it. Each
 * of those is a message that goes out, is delivered, and is wrong: decision
 * 1570's *"a helpful `substr($body, 0, 160)` is the composer built by accident,
 * in the layer least able to say what a message may say"*, met one layer up
 * where the composer actually is.
 *
 * ⛔ **THE TAIL IS THE PART A LENGTH LIMIT EATS FIRST, AND THE TAIL IS THE
 * OPT-OUT.** That is why over-length is an exception and not a trim: a
 * reactivation SMS without `Reply STOP to opt out` is a compliance failure that
 * reports as a successful send, over the GOAIEZ 10DLC brand, from our own number
 * pool, against a list whose only sending basis is somebody else's attestation
 * (decisions 2098–2102).
 *
 * ⚠️ **CAUGHT BY THE CAMPAIGN RUNNER, NOT BY THE QUEUE.** A campaign whose
 * template does not fit fails for every recipient in exactly the same way, so
 * letting this escape would retry an unfixable job three times per contact.
 * `RunCampaignJob` marks the recipient and stops the run.
 *
 * The messages are written for an operator, never a customer.
 */
final class MessageCannotBeComposed extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function because(string $message): self
    {
        return new self($message);
    }
}
