<?php

declare(strict_types=1);

namespace App\Services\Pixel;

use App\Models\Business;
use App\Models\PixelKey;
use App\Scopes\TenantScope;
use App\Support\Tenancy;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The only reader and writer of `pixel_keys` — the pixel's public key.
 *
 * ⚠️ **IT REPLACES `businesses.pixel_tenant_id`, WHICH HAD NO WRITER IN `app/`
 * AT ALL** — decision 272's shape, and this makes at least the seventeenth
 * instance `CLAUDE.md` records. That column existed from the Stage 0 schema,
 * `BusinessFactory` filled it, `plugins.embed_key`'s docblock cited it as the
 * pattern to copy — and nothing in the application ever set it. So every real
 * tenant carried `NULL`, and §11 row 1's *"Validate `data-k` → tenant"* would
 * have resolved **nothing, for everybody, forever**, while every test that built
 * a business through the factory passed.
 *
 * ⛔ **AND BUILDING THE WRITER IS WHAT SHOWED THE COLUMN COULD NOT DO THE JOB.**
 * `businesses` is FORCE ROW LEVEL SECURITY under one policy keyed on its own id,
 * so with no tenant established the row is invisible to the one query whose
 * purpose is to say which tenant this is — and `plugins`' fix, a `public_read`
 * policy, would expose every business's name, EIN, address and
 * `data_classification` to an unauthenticated connection, because a policy
 * filters rows and not columns. The creating migration carries the full
 * argument.
 *
 * ⛔ **AND THE FAILURE WOULD HAVE BEEN INVISIBLE FROM BOTH ENDS.** The pixel
 * swallows every transport error deliberately — *"the right behaviour on
 * somebody else's website is to say nothing at all"* — and the collector answers
 * `204` to an unknown key by design (see [[\App\Enums\PixelRefusal::UnknownKey]]).
 * A tenant would have installed the script, seen no error anywhere, and
 * collected nothing.
 *
 * ---------------------------------------------------------------------------
 * WHY `resolve()` DROPS THE TENANT SCOPE, AND WHY THAT IS THE WHOLE SURFACE
 * ---------------------------------------------------------------------------
 * `WidgetPlugins::resolve()`'s reasoning exactly, on a third public key:
 * decision 318's circularity says a scope calling `Tenancy::idOrFail()` cannot
 * run on the query whose answer *establishes* the tenant. `PixelKey` keeps its
 * scope — `idOrFail()` throws rather than filtering to nothing, so a scoped model
 * fails loudly on any path that forgot a tenant, while an unscoped one quietly
 * returns whatever `public_read` permits, which is every row — and the opt-out
 * lives in one method that is deliberately incapable of being anything but a
 * single-row lookup by exact key: no `where` a caller can influence, no ordering,
 * no list, and a value that is not a UUID never reaches the database.
 */
final class PixelKeys
{
    /**
     * Give a business its public pixel key, or hand back the one it has.
     *
     * IDEMPOTENT, because `TenantProvisioner` calls it inside the registration
     * transaction and a retry must not mint a second key — the same reasoning
     * `WidgetPlugins::provisionFor()` records for `embed_key`, and it bites
     * harder here: this key is what a customer pastes into their website, so two
     * of them means one live archive nobody can find and one they installed.
     *
     * ⚠️ **RUNS AS THE BUSINESS IT NAMES.** `businesses` is FORCE ROW LEVEL
     * SECURITY and its policy is keyed on its own id, so this asserts the tenant
     * rather than switching it — a service that silently re-points the tenant
     * hands every future caller cross-tenant write access without saying so.
     *
     * @throws InvalidArgumentException when the business is not the acting tenant
     */
    public function ensureFor(Business $business): string
    {
        if ((int) $business->getKey() !== Tenancy::idOrFail()) {
            throw new InvalidArgumentException(
                'That business is not the tenant in context. A pixel key is a permanent public '
                .'identifier for one tenant\'s archive, so minting it under another\'s session '
                .'would point a stranger\'s script at the wrong business. Wrap the call in '
                .'Tenancy::actingAs().',
            );
        }

        $existing = $this->forBusiness($business);

        if ($existing !== null) {
            return $existing;
        }

        // ⚠️ ALWAYS RANDOM, NEVER DERIVED FROM TENANT DATA. The creating
        // migration and `Plugin`'s docblock both say so, for the reason that
        // governs every public key in this schema: it is presented by an
        // anonymous browser before any tenant is known, so anything recoverable
        // from it is something a stranger learns for free.
        $key = (string) Str::uuid();

        // forceFill(), because `key` and `business_id` are guarded on the model
        // and must stay guarded — a permanent public identifier is not something
        // a request body may reach.
        //
        // ⚠️ THE IDEMPOTENCY ABOVE IS A LOOKUP AND THE GUARANTEE IS THE UNIQUE
        // INDEX ON `business_id`. Decision 350's shape: two simultaneous calls
        // both see nothing and both insert, and the database is what refuses the
        // second.
        $row = new PixelKey;

        $row->forceFill([
            'business_id' => $business->getKey(),
            'key' => $key,
            'created_at' => now(),
        ])->save();

        return $key;
    }

    /**
     * The business behind a public pixel key, or null.
     *
     * ⚠️ **CALLED WITH NO TENANT ESTABLISHED**, because this is the query whose
     * answer establishes it. [[PixelCollector]] calls it and sets the tenant from
     * what comes back.
     *
     * ⛔ **NO `Tenancy::forgetAll()` HERE, AND ITS ABSENCE IS DELIBERATE.**
     * `ResolveWidget` clears unconditionally because it is middleware and owns
     * the whole request; this is a lookup, and one that silently discarded its
     * caller's tenant would be an unpleasant thing to call from anywhere else.
     * The clearing belongs to [[PixelCollector::receive()]], which does own the
     * request, and it happens on that method's first line.
     */
    public function resolve(string $key): ?Business
    {
        $key = trim($key);

        // Checked before querying, so a malformed key is a cheap null rather
        // than a database round trip — and so Postgres never has to reject a
        // non-UUID cast on a public, unauthenticated path.
        if ($key === '' || ! Str::isUuid($key)) {
            return null;
        }

        $row = PixelKey::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('key', $key)
            ->first();

        if (! $row instanceof PixelKey) {
            return null;
        }

        // ⚠️ **THE BUSINESS IS READ *INSIDE* THE TENANT THE KEY NAMED**, which is
        // what keeps `businesses` fully scoped. The public row disclosed one
        // integer; everything after that is an ordinary tenant-scoped read with
        // row-level security underneath it, so a key that named a business the
        // reader has no claim to would return nothing rather than a row.
        return Tenancy::actingAs(
            (int) $row->business_id,
            static fn (): ?Business => Business::query()->find($row->business_id),
        );
    }

    /**
     * The key a business already holds, or null — a plain scoped read.
     *
     * ⚠️ **READ-ONLY ON PURPOSE, AND NOT `ensureFor()`.** An install screen
     * renders on a `GET`, and minting a permanent public identifier as a side
     * effect of somebody looking at a page is how a key ends up in a browser
     * history and nowhere else. `WidgetPlugins::forLocation()` is the precedent.
     */
    public function forBusiness(Business $business): ?string
    {
        $row = PixelKey::query()->where('business_id', $business->getKey())->first();

        return $row instanceof PixelKey ? $row->key : null;
    }
}
