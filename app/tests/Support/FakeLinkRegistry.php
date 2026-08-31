<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Links\LinkRegistry;
use App\Enums\TenantLinkKind;
use App\Models\Conversation;
use App\Services\Links\BookingLink;
use App\Services\Links\TenantLink;
use App\Services\Links\TenantLinks;
use App\Support\Tenancy;
use LogicException;

/**
 * A links registry for P7's tests, standing in for P6's real one.
 *
 * ⚠️ **IT IS KEYED ON `Tenancy::id()`, AND THAT IS THE WHOLE VALUE OF IT.** A
 * fake that answers the same link to every caller makes a cross-tenant test
 * vacuous — 256's shape — because tenant B's page would show tenant A's link
 * whether or not the surface asked for the current tenant's. Keying it means
 * *"tenant A's booking link never appears on tenant B's surface"* is a claim
 * that can fail, and it is the one P7 most needs to be able to fail.
 *
 * ⛔ **`shortLinkFor()` THROWS, DELIBERATELY.** Neither of P7's two surfaces is a
 * *send*, so neither may mint a per-send token (R14, and see
 * {@see BookingLink} for the argument). A future change that
 * routes the thanks screen or the reply composer through the short-link service
 * reddens every test in `BookingLinkSurfacesTest` rather than passing quietly.
 *
 * ⚠️ **IT WAS NOT A REPLACEMENT FOR `UnbuiltLinkRegistry`, AND THAT CLASS NO
 * LONGER EXISTS** (P6, decisions 3989–3994; this correction is 4046). The
 * paragraph here said the container still held the refusing implementation and
 * that a test at the bottom of `BookingLinkSurfacesTest` re-bound it — both
 * ceased to be true in the commit that built P6, which deleted the class and
 * that test with it. What this fake is remains exactly what it was: a
 * collaborator a test binds on purpose, never a fallback.
 */
final class FakeLinkRegistry implements LinkRegistry
{
    /** @var array<int, TenantLink> */
    private array $booking = [];

    /**
     * Bind this registry with nothing in it, for a test whose subject is not links.
     *
     * ⚠️ **THIS IS WHAT THREE PRE-EXISTING FILES NEEDED THE DAY P7 LANDED, AND
     * IT IS NOT A WORKAROUND FOR P6 BEING UNBUILT.** `FeedbackPageController` and
     * `ReplyQueue` acquired a new collaborator; a test that renders either now has
     * to supply one, exactly as it already supplies a fake queue and a fake
     * mailer. With nothing set, both screens render precisely what they rendered
     * before P7 — R13's "no booking link" path — so nothing those files assert
     * changes meaning.
     *
     * ✅ **THE "P7 IS NOT SHIPPABLE WITHOUT P6" WARNING IS SPENT AND IS REMOVED
     * RATHER THAN LEFT TO READ AS LIVE** (decision 4046). It said the container
     * in a real request still holds `UnbuiltLinkRegistry`, that both screens
     * still raise `UnbuiltPatch`, and that *"a green suite is not evidence that
     * these two screens work today"*. P6 landed and every clause of that is now
     * false — the class is deleted, the binding is {@see TenantLinks}, and the
     * suite **is** evidence. ⛔ **Leaving it would have been the worse half of
     * `CLAUDE.md` 2505's shape**: a note telling the next reviewer to distrust a
     * suite that had since become trustworthy, which buys exactly the same thing
     * a false reassurance does — nobody looks.
     */
    public static function bindWithNothingSet(): void
    {
        app()->instance(LinkRegistry::class, new self);
    }

    public function giveBooking(int $businessId, string $label, string $destination): void
    {
        $this->booking[$businessId] = TenantLink::booking($label, $destination);
    }

    public function booking(): ?TenantLink
    {
        $businessId = Tenancy::id();

        return $businessId === null ? null : ($this->booking[$businessId] ?? null);
    }

    public function payment(): ?TenantLink
    {
        return null;
    }

    /**
     * @return array<string, TenantLink>
     */
    public function documents(): array
    {
        return [];
    }

    public function document(string $slug): ?TenantLink
    {
        return null;
    }

    public function shortLinkFor(TenantLink $link, Conversation $conversation): string
    {
        throw new LogicException(
            'P7 surfaces a booking link on a web page and in a published review reply. '
            .'Neither is a send, so neither mints a per-send short link — if this fired, '
            .'something on one of those two paths started treating a page view as a message.'
        );
    }

    /**
     * @return array<string, bool>
     */
    public function grounded(): array
    {
        return [
            TenantLinkKind::Booking->value => $this->booking() !== null,
            TenantLinkKind::Payment->value => false,
            TenantLinkKind::Document->value => false,
        ];
    }
}
