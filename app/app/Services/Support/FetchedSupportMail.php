<?php

declare(strict_types=1);

namespace App\Services\Support;

use Carbon\CarbonImmutable;

/**
 * One email read out of the goaiez support mailbox, **before** anybody has
 * worked out whose it is (T176 P24).
 *
 * ⛔ **THE DELIBERATE DIFFERENCE FROM {@see InboundSupportMessage} IS THE
 * MISSING `businessId`.** That type refuses to guess a tenant and says so in
 * capitals; this one is what the transport holds *while it is still guessing*.
 * Collapsing the two would put the sender's address on a type the desk consumes,
 * and the address is the one field that must not survive the resolution — see
 * below.
 *
 * ⚠️ **`fromAddress` IS A CORRESPONDENT'S EMAIL ADDRESS AND IT IS NEVER
 * STORED.** It exists for exactly one comparison — is this an account owner we
 * know? — performed by {@see AccountDirectory} in memory,
 * after which the id is kept and the address is dropped. `MailInboxCursor`'s
 * migration draws the same line in the same words: one of *our* addresses is
 * acceptable on a platform-scoped row, a correspondent's is not.
 */
final readonly class FetchedSupportMail
{
    /**
     * @param  string  $externalRef  the provider's own id, prefixed with the
     *                               transport that minted it, so a second
     *                               transport reading the same mailbox cannot
     *                               collide with this one's numbering
     * @param  string  $body  the plain-text part, already decoded; trimmed to the
     *                        desk's own limit when it is recorded
     * @param  bool  $senderIsAuthenticated  whether the mailbox provider's own
     *                                       `Authentication-Results` proves the
     *                                       `From` domain (4606). ⛔ **NOTHING
     *                                       WITH THIS FALSE MAY BE ROUTED TO A
     *                                       TENANT**, and
     *                                       `AccountDirectory::accountOfSender()`
     *                                       takes this whole object rather than
     *                                       the address so that there is no call
     *                                       shape which resolves an
     *                                       unauthenticated sender. ⚠️ It
     *                                       defaults **false**: a caller that
     *                                       constructs one of these without
     *                                       having checked has not authenticated
     *                                       anybody, and the fail-closed
     *                                       direction is the one where nothing
     *                                       routes.
     */
    public function __construct(
        public string $externalRef,
        public string $fromAddress,
        public string $subject,
        public string $body,
        public CarbonImmutable $receivedAt,
        public bool $senderIsAuthenticated = false,
    ) {}
}
