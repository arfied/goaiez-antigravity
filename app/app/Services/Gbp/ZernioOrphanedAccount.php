<?php

declare(strict_types=1);

namespace App\Services\Gbp;

/**
 * An account Zernio bills us for that nothing in this application is using.
 *
 * The 6779(d) shape, named: an owner presses **Connect**, grants
 * `business.manage` at Zernio's consent screen, and never lands back on the
 * redirect. The grant is live, the account is connected, the invoice includes
 * it — and this application holds a `pending` row nothing revisits and no
 * binding at all, so `ZernioSpend::connectedAccounts()`, which counts bindings,
 * cannot see it. **A money path with no counter does not look uncapped, it looks
 * free** (3297), and this is that sentence on the one vendor that bills per
 * connected account per day.
 *
 * ⛔ **IT IS A SUSPICION AND NOT A VERDICT, AND NOTHING MAY ACT ON IT
 * UNATTENDED** (4884, 4888(b)). The same three states arrive here identically —
 * 6766 already says so about the webhook: an abandoned flow, an account
 * connected from Zernio's own dashboard, and a redirect that simply has not
 * landed yet are indistinguishable from out here. **Detect, count, surface.**
 *
 * ⛔ **AND IT CARRIES NO ACCOUNT REFERENCE, WHICH IS 4884's DISCIPLINE RATHER
 * THAN AN OVERSIGHT.** The orphan probe that came before it *"returns business
 * references, never account ids, so its caller never holds the value that
 * decides whose listing a call reaches."* A profile reference is the handle an
 * operator takes to Zernio's own console; it names a tenant grouping and it is
 * not the argument to `DELETE /v1/accounts/{id}`.
 */
final readonly class ZernioOrphanedAccount
{
    public function __construct(
        /**
         * The Zernio profile this account hangs off, when the vendor named one.
         */
        public ?string $profileRef,
        /**
         * The business whose connect flow made that profile, if we recorded it.
         *
         * ⚠️ **NULL MEANS "WE CANNOT SAY", NEVER "NOBODY'S"** — a profile made
         * before the index existed, or one created directly in Zernio's console,
         * lands here. The brief's own rule: attributed to the business that
         * started the flow, **or reported as unattributable — never guessed.**
         */
        public ?int $businessId,
        /**
         * The vendor's slug for the network, when it gave one (6771).
         */
        public ?string $platform,
    ) {}
}
