<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why the T3 payload endpoint served nothing — `BUILD-PLAN` §2.11.3 slice I.
 *
 * ⚠️ **THE WIRE ANSWER IS THE SAME FOR EVERY CASE AND THAT IS DELIBERATE**,
 * `PixelRefusal`'s own rule: the caller is a script on somebody else's page, so
 * an empty payload is the only thing it may learn. A refusal that told a
 * stranger *"this key exists, but that business is a healthcare tenant"* would
 * disclose a `data_classification` to anybody who can read a page's source.
 * **This enum is what the service returns to its callers and to its tests**, so
 * each refusal can be driven at the service rather than inferred from an empty
 * body (398, and 4965's lesson that the route is not where a refusal is proven).
 */
enum T3PayloadRefusal: string
{
    /**
     * The key was absent, malformed, or names no business.
     */
    case UnknownKey = 'unknown_key';

    /**
     * ⛔ **RULE 24, AT THIS ENDPOINT, FOR THE COLLECTOR'S REASON AND ONE MORE.**
     * `PixelCollector` refuses a `Phi` business's *inbound* traffic (4965); this
     * refuses the *outbound* half. Injected FAQ content is written from the
     * questions a business's customers actually ask, and on a healthcare tenant
     * those questions are the thing rule 24 fences — so a payload built for one
     * would carry patient-derived text out of this application, onto a page on
     * the open internet, with no BAA and no KMS key behind it.
     *
     * ⚠️ **AND IT REFUSES BEFORE IT READS ANY CHANGE SET**, so nothing about a
     * covered entity's pages is loaded at all to answer a stranger's request.
     */
    case HealthTenant = 'health_tenant';

    /**
     * The account is stopped — `businesses.paused_at`.
     *
     * ⚠️ **THIS IS ROW 5's "ALL SENDING + ACTUATION OFF" REACHING THE ACTUATION
     * IT WAS WAITING FOR**, and at T3 it is unusually complete: a paused tenant
     * stops being served a payload, so on the next page load the injected
     * content is simply not there. Stopping *is* the rollback here, rather than
     * a request for one.
     */
    case AccountPaused = 'account_paused';

    /**
     * The account is suspended — nothing is served for a stopped subscription
     * either, on the same reasoning as the pause.
     */
    case AccountSuspended = 'account_suspended';

    /**
     * There is nothing live to serve.
     *
     * ⚠️ **THE ORDINARY CASE TODAY, FOR EVERY TENANT.** Nothing in `app/` applies
     * a T3 change set yet — the publishing pipeline is slice D and the live
     * adapter is slice G — so this is what every real key resolves to, and a
     * test that could not tell this apart from a leak would be worthless. It is
     * a distinct case rather than an empty payload for exactly that reason.
     */
    case NothingLive = 'nothing_live';
}
