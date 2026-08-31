<?php

declare(strict_types=1);

namespace App\Services\Actuation;

/**
 * Everything the T3 module will do on one tenant's site, as it goes on the wire.
 *
 * ---------------------------------------------------------------------------
 * ⛔ WHY THE SNAPSHOT AND THE ROLLBACK ARE STRUCTURAL AT THIS TIER
 * ---------------------------------------------------------------------------
 * `29` §2 rule 32 — *every site change snapshots its prior state and is
 * reversible* — is enforced at T1 by `SiteChanges::open()` refusing an empty
 * before-snapshot and by the adapter writing the prior values back. **At T3
 * neither of those is what makes the promise true**, and pretending otherwise
 * would be 5528's placeholder-snapshot failure with the sign flipped.
 *
 * Nothing is written to the tenant's site at this tier. **The page is the
 * page**: this payload is applied by a script in the visitor's browser, after
 * the document has loaded, on every view. So:
 *
 *   - **The snapshot is the payload version served**, {@see self::version()} —
 *     a digest of the exact bytes this endpoint answered with, which is the
 *     complete description of what any visitor's page had done to it.
 *   - **The rollback is to stop serving.** A change set that is reverted, a
 *     business that is paused, a suspended account, a `Phi` reclassification —
 *     each of them removes operations from the next response, and the next page
 *     load is byte-for-byte the page the tenant's own CMS produced, because
 *     nothing of ours was ever persisted into it.
 *
 * ⚠️ **THE HONEST LIMIT, STATED RATHER THAN LEFT TO BE ASSUMED** (352, 397): the
 * property is *"a reload without the payload is the prior page"*, and it holds
 * for the visitor's next load rather than instantly for a page already open. A
 * tab left open overnight keeps whatever it applied until it is reloaded. That
 * is a weaker claim than T1's server-side revert and a stronger one than any
 * restore routine — there is nothing to restore.
 *
 * ---------------------------------------------------------------------------
 * WHY THE BYTES ARE FIXED HERE RATHER THAN BY THE CONTROLLER
 * ---------------------------------------------------------------------------
 * §19.7's no-cloaking gate is *"byte-identical HTML across user agents"*, and
 * the only way to be sure of that is for there to be exactly one serialisation,
 * derived from the payload and from nothing else — no request, no header, no
 * clock. {@see self::json()} is it, and {@see self::version()} is its digest,
 * which the controller sends as the `ETag`. A response that varied by caller
 * could not have a content-addressed validator at all, so the two properties
 * hold each other up.
 *
 * ⚠️ **A FLOAT LEAF IS THE ONE THING WHOSE BYTES DEPEND ON THE MACHINE** —
 * `serialize_precision`, which is 4877's caveat about replay in a different
 * costume. It is a real limit on the claim *"the same payload serialises the
 * same way everywhere"*; it is not a limit on the no-cloaking claim, which is
 * about two responses from one process.
 */
final readonly class T3Payload
{
    /**
     * @param  array<string, array<string, list<T3Operation>>>  $pages  Website host, then
     *                                                                  path on it, to the
     *                                                                  operations for that
     *                                                                  page.
     */
    public function __construct(private array $pages) {}

    public function isEmpty(): bool
    {
        return $this->pages === [];
    }

    /**
     * The payload as a plain array, in one fixed order.
     *
     * ⚠️ **SORTED BY WEBSITE THEN BY PATH, AND THE OPERATIONS WITHIN A PAGE KEEP
     * THE ORDER THE CHANGE SETS WERE OPENED IN.** Two orderings, both
     * deliberate: hosts and paths sort so that the digest does not move when a
     * row is inserted, and operations stay in change-set order because a later
     * change set on the same page is meant to win.
     *
     * ⛔ **`h` IS WHAT STOPS ONE OF A TENANT'S WEBSITES RECEIVING ANOTHER'S
     * STRUCTURED DATA** (6044). One public key covers a business; a change set
     * belongs to a location's website; and until this key existed a tenant with
     * two sites had each of them applying the other's operations wherever the
     * paths coincided. It is first in each entry because it is the first thing
     * the module compares.
     *
     * @return array{p: list<array{h: string, u: string, o: list<array<string, mixed>>}>}
     */
    public function toArray(): array
    {
        $pages = $this->pages;
        ksort($pages);

        $out = [];

        foreach ($pages as $host => $paths) {
            ksort($paths);

            foreach ($paths as $path => $operations) {
                $out[] = [
                    'h' => (string) $host,
                    'u' => (string) $path,
                    'o' => array_map(static fn (T3Operation $op): array => $op->toPayload(), $operations),
                ];
            }
        }

        return ['p' => $out];
    }

    /**
     * The exact bytes served. **The only serialisation of this payload.**
     */
    public function json(): string
    {
        return json_encode(
            $this->toArray(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * The version of this payload — the T3 snapshot, and the response's `ETag`.
     *
     * ⚠️ **DERIVED FROM THE CONTENT AND FROM NOTHING ELSE**, so two tenants with
     * identical payloads share a version and a redeploy does not invent a new
     * one. `PixelDelivery`'s `sha` is the same idea about the bundle; the
     * reasoning there about *why a build token cannot also be the hash* does not
     * apply, because nothing is stamped into these bytes.
     */
    public function version(): string
    {
        return substr(hash('sha256', $this->json()), 0, 32);
    }
}
