<?php

declare(strict_types=1);

namespace App\Services\Gbp;

/**
 * One connected account as Zernio reports it — a billable unit, in their words.
 *
 * ⚠️ **THIS IS THE BILLING BASIS AND NOT A LIST OF LISTINGS.** 4720 read their
 * `/billing` page: *"Every day, our system records which accounts are connected
 * and reports them to the billing engine, one event per active account per
 * day."* So the set these come from is precisely what the invoice is computed
 * over, which is what makes {@see ZernioReconciliation} reconcilable against it
 * at all.
 *
 * ⛔ **THREE FIELDS, AND THE OMISSIONS ARE THE DESIGN.** `SocialAccount` also
 * carries `username` and `displayName` — a Google Business account's own name,
 * and on a sole trader's listing that is a person's name. Neither answers any
 * question this reconciliation asks, and `GbpReview`'s rule applies unchanged:
 * an unused field on a vendor payload is stored personal data waiting for
 * somebody to persist it. Nothing here is persisted in any case; this object
 * lives for the length of one report.
 */
final readonly class ZernioAccount
{
    public function __construct(
        /**
         * The vendor's own account id — `_id`, declared required (6603).
         *
         * ⛔ **THE ONE VALUE THAT DECIDES WHOSE GOOGLE LISTING A CALL REACHES**,
         * and `ZernioGbpClient`'s own docblock says it is opaque and
         * unvalidated. It is compared against `gbp_account_bindings` and is
         * never rendered on a screen — 4884's discipline, whose orphan probe
         * *"returns business references, never account ids, so its caller never
         * holds the value that decides whose listing a call reaches."*
         */
        public string $accountRef,
        /**
         * The profile this account hangs off — `profileId`, declared required.
         *
         * Zernio's own multi-tenant guide calls a profile *"the tenant
         * boundary"*, and it is the only field here that can be attributed back
         * to a business of ours.
         *
         * ⚠️ **NULLABLE EVEN THOUGH THE VENDOR DECLARES IT REQUIRED**, on
         * 6605's rule that a documented parameter set is a moving contract —
         * theirs gained a member on 4 August 2026. An account we cannot
         * attribute is still an account we are billed for, so it is counted
         * and reported as unattributable rather than dropped for being
         * inconvenient to describe.
         */
        public ?string $profileRef,
        /**
         * Their slug for the network — `googlebusiness`, or something else.
         *
         * ⚠️ **NULLABLE, AND AN UNREADABLE VALUE IS NEVER READ AS GOOGLE**
         * (6771). A Zernio team holds every profile and every platform, so an
         * account for a network this application never connects can legitimately
         * appear in this list — it is still billed, so it is still counted, and
         * it is reported as what it is rather than as ours.
         */
        public ?string $platform,
    ) {}
}
