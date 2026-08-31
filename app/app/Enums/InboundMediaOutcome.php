<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Sms\InboundMediaFetcher;
use App\Services\Storage\StorageRetention;

/**
 * What happened to one media part of an inbound MMS — T176 P10, skill 12.
 *
 * ⛔ **EVERY CASE IS A ROW, INCLUDING EVERY REFUSAL, AND THAT IS THE WHOLE
 * DESIGN.** A refusal that writes nothing is indistinguishable from an MMS that
 * carried no media at all, and the two need opposite responses: the first is a
 * customer whose photograph we chose not to keep, the second is an ordinary
 * text. `inbound_media` therefore records the refusals as first-class rows with
 * no bytes behind them, which is what makes *"a customer sent you something we
 * did not store"* an answerable question a year later.
 *
 * ⚠️ **NO CASE CARRIES A URL AND THE TABLE HAS NO COLUMN FOR ONE.** The address
 * arrives in a webhook body a stranger could have written; it is used once,
 * inside {@see InboundMediaFetcher}, and never persisted.
 * Storing it would put an attacker-chosen URL in a durable row that a later
 * screen, export or job could be tempted to re-fetch.
 *
 * A string cast to a PHP backed enum, never a database enum — `CLAUDE.md`. The
 * creating migration also closes the set with a CHECK, because this column
 * decides whether `storage_path` may be null.
 */
enum InboundMediaOutcome: string
{
    /** The bytes were fetched, checked and written to the object store. */
    case Stored = 'stored';

    /**
     * ⛔ **THE TENANT HANDLES HEALTH INFORMATION AND THIS PLATFORM CANNOT YET
     * PROTECT A PHOTOGRAPH** (4166). `29` §2 rule 24 puts PHI in a separate
     * schema, under a separate role, under a separate KMS key; `CLAUDE.md`
     * records that the key and the collector enforcement wait on Stage 3. An
     * inbound MMS is the one inbound surface where the *content* is whatever a
     * customer chose to photograph, so a dental or medical tenant's inbound
     * picture is PHI until proven otherwise and there is nowhere lawful to put
     * it. **Nothing is fetched at all** — the refusal is decided before a socket
     * opens, so the bytes never enter this process.
     */
    case RefusedHealthTenant = 'refused_health_tenant';

    /**
     * The address was not on the configured media allowlist, was not `https`,
     * or resolved to a private, loopback or link-local address.
     *
     * ⚠️ **THE THREE ARE ONE CASE ON PURPOSE.** They are all *"we will not open
     * a socket to that"*, they are all decided before one is opened, and
     * splitting them would put a description of our SSRF posture into a row an
     * operator could be tempted to read back to whoever supplied the URL.
     */
    case RefusedUntrustedHost = 'refused_untrusted_host';

    /**
     * The response was not one of the image types this platform keeps, or its
     * first bytes disagreed with the type it claimed.
     *
     * ⚠️ **A DECLARED TYPE IS NOT A TYPE.** The header is written by whoever
     * served the file; the magic bytes are the thing being stored. Both are
     * checked, and a disagreement is refused rather than resolved in favour of
     * either.
     */
    case RefusedContentType = 'refused_content_type';

    /** The response was larger than the ceiling, by its header or by its bytes. */
    case RefusedTooLarge = 'refused_too_large';

    /**
     * ⛔ **THE SENDER IS THIS BUSINESS'S OWN ACCOUNT HOLDER AND THEY HAVE ASKED
     * US TO STOP TEXTING THEM** — wave 41 lane E, decision 11100.
     *
     * ⛔ **IT EXISTS BECAUSE THE TWO HALVES OF ONE MESSAGE DISAGREED.** 10830
     * stopped storing the **words** of a stopped account holder's text —
     * `App\Services\Sms\InboundMessages::recordOwnerReply()` returns before
     * `owner_replies` is written — and nothing changed on the attachment path,
     * which runs after the `match` for every keyword. So the one class of person
     * who has explicitly withdrawn from this channel was the one class whose
     * photographs were still being fetched and kept, **and filed into the
     * customer-photo store** beside pictures members of the public sent (10842).
     * One MMS, two answers, and the flattering one applied to the picture.
     *
     * ⚠️ **THE PREDICATE IS NARROW ON PURPOSE AND IS NOT "THE SENDER SAID
     * STOP".** It is *this receiving number's own business has registered this
     * sender as its account holder, and that registration is stopped*. An
     * account holder of business A who is also a customer of business B keeps
     * every photograph they send to B — refusing on the sender alone would
     * silently drop a genuine customer's picture for a tenant who never heard of
     * the stop.
     *
     * ⚠️ **NOTHING IS FETCHED**, so the bytes never enter this process and the
     * URL never enters a queue payload — {@see self::RefusedHealthTenant}'s
     * property, reached for a different reason.
     *
     * ⛔ **WHAT IS NOT DECIDED HERE IS WHETHER AN UN-STOPPED ACCOUNT HOLDER'S
     * PHOTOGRAPH BELONGS IN THIS TABLE AT ALL.** 10842 raised that and nobody
     * has ruled; it is a product question about where an owner's own attachment
     * should live, and it is restated at 11103 rather than answered by a lane.
     */
    case RefusedOwnerStopped = 'refused_owner_stopped';

    /**
     * We could not obtain and keep the bytes, after every retry.
     *
     * ⚠️ **THIS IS THE ONLY OUTCOME WRITTEN AFTER RETRIES RATHER THAN AT THE
     * FIRST ATTEMPT**, and it deliberately covers the whole family of *"it did
     * not get here"* — a connection that failed, a 5xx, a timeout, or an object
     * store that would not take the write. The three refusals above are
     * decisions about the response and are final the moment they are made;
     * retrying one would be asking the same question again and getting the same
     * answer while a customer waits.
     */
    case RefusedUnreachable = 'refused_unreachable';

    /**
     * We stored it, kept it for the period an operator stated, and then deleted
     * it — {@see StorageRetention}, decision 4944.
     *
     * ⛔ **THE ONLY CASE THAT IS NOT A DECISION ABOUT THE RESPONSE, AND THE ONLY
     * ONE WRITTEN LONG AFTER THE MMS ARRIVED.** Every case above is settled
     * within seconds of a webhook; this one is written by a scheduled sweep
     * months later. It belongs here rather than in a column of its own because
     * the question this column answers is *"what happened to this attachment"*,
     * and *"we deleted it on the retention schedule"* is one of the answers.
     *
     * ⛔ **AND IT IS NOT A REFUSAL, WHICH IS WHY IT IS NOT NAMED `Refused…`.**
     * Reusing one of the refusals would have avoided a migration and would have
     * been a lie in the durable record: `RefusedTooLarge` says we never took the
     * file, and a customer whose photograph we *did* hold for six months has a
     * different history from one whose we never had. This enum's opening rule is
     * that a refusal writing nothing is indistinguishable from an MMS carrying
     * no media; the same argument one step later says a prune must not be
     * indistinguishable from a refusal.
     *
     * ⚠️ **EVERY BYTE COLUMN GOES NULL WITH THE OBJECT, THE CHECKSUM INCLUDED.**
     * This case lands in the creating migration's `outcome <> 'stored'` arm and
     * therefore carries no path, no disk, no content type, no size and no
     * checksum — so no widening of *that* CHECK was needed, only of the one that
     * closes the set of names. Keeping the checksum was considered and refused:
     * a SHA of a customer's photograph is a fingerprint of the thing we told
     * them we deleted, and `CLAUDE.md`'s tiebreaker ranks less stored personal
     * data above every convenience it would have bought. What survives is that
     * they sent something and that we no longer hold it.
     */
    case Pruned = 'pruned';

    /**
     * Whether this outcome has bytes behind it.
     *
     * A `match` rather than a comparison so a seventh case cannot inherit an
     * answer nobody chose — and this one decides whether the row is allowed to
     * name a storage path at all, which the migration's CHECK enforces beneath
     * it.
     *
     * ⚠️ **{@see self::Pruned} ANSWERS `false`, AND ROUTING IT THROUGH THIS
     * METHOD RATHER THAN COMPARING TO `Stored` IS WHY THE ANSWER IS RIGHT.** A
     * pruned row held bytes once and holds none now; every reader of this method
     * is asking the present-tense question, and the CHECK beneath it enforces
     * exactly that reading.
     */
    public function isStored(): bool
    {
        return match ($this) {
            self::Stored => true,
            self::RefusedHealthTenant,
            self::RefusedUntrustedHost,
            self::RefusedContentType,
            self::RefusedTooLarge,
            self::RefusedOwnerStopped,
            self::RefusedUnreachable,
            self::Pruned => false,
        };
    }
}
