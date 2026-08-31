<?php

declare(strict_types=1);

namespace App\Services\Links;

use App\Contracts\Links\LinkRegistry;
use App\Enums\TenantLinkKind;
use App\Support\Money;
use InvalidArgumentException;

/**
 * One link a business has given its assistant to hand out (T176 P6).
 *
 * ⛔ **THE DESTINATION IS DELIBERATELY NOT A PUBLIC PROPERTY.** R14: *"every
 * agent-sent link rides the short-link service — per-send tokens, click → CRM
 * timeline."* A DTO exposing `->url` invites `"Book here: {$link->url}"` at some
 * call site, which sends a link that is untracked, un-tokenised, absent from the
 * timeline, and — since the composer's ≤159-character law counts what is
 * actually sent — usually too long as well. The raw value is reachable only
 * through {@see destination()}, whose name says what it is for, and the thing a
 * message is built from is a short link minted per send by
 * {@see LinkRegistry::shortLinkFor()}.
 *
 * ⚠️ **AND `$label` IS THE TENANT'S WORDS, WHICH MAKES IT UNTRUSTED.** It is
 * typed by a business into a settings screen and then travels into a model
 * prompt beside a member of the public's message. Rail 1 fences untrusted
 * fields; a label is one, and it is the one that looks trustworthy because it
 * came from the customer's own supplier.
 */
final readonly class TenantLink
{
    /**
     * @param  TenantLinkKind  $kind  What the assistant may use it for.
     * @param  string  $label  What the business calls it — "Book a visit", "Our
     *                         price sheet". Shown to the customer and given to
     *                         the model. Untrusted (see above).
     * @param  string  $destination  The business's own URL. Never sent directly.
     * @param  ?string  $slug  Stable handle for a document, so a reply can name
     *                         one of several. `null` for the single-valued kinds.
     * @param  ?int  $feeCents  The call-out fee, for {@see TenantLinkKind::Payment}
     *                          only. Integer cents per `18` §Money handling.
     *                          `null` means the business set no fee, which under
     *                          R13 switches skill 6 off entirely rather than
     *                          defaulting it to zero — an assistant telling
     *                          somebody a visit is free is worse than one that
     *                          does not mention visits.
     * @param  ?string  $feeCovers  The what-it-covers line §2.4 asks for beside
     *                              the fee, and skill 6 states with it. Untrusted,
     *                              exactly as `$label` is. `null` is ordinary and
     *                              does **not** switch skill 6 off — see
     *                              {@see groundsFeeCollection()}.
     * @param  ?string  $feeCurrency  ISO 4217, travelling with `$feeCents` because
     *                                `18` §Money handling makes the currency part
     *                                of the value rather than context around it.
     */
    private function __construct(
        public TenantLinkKind $kind,
        public string $label,
        private string $destination,
        public ?string $slug = null,
        public ?int $feeCents = null,
        public ?string $feeCovers = null,
        public ?string $feeCurrency = null,
    ) {}

    public static function booking(string $label, string $destination): self
    {
        return new self(TenantLinkKind::Booking, $label, $destination);
    }

    /**
     * ⚠️ **THE THREE FEE ARGUMENTS ARE OPTIONAL AND TRAILING, WHICH IS WHAT KEEPS
     * THIS A DAY-0 CONTRACT RATHER THAN A BREAKING CHANGE.** P6 added
     * `$feeCovers` and `$feeCurrency` when it built the store behind this DTO:
     * §2.4's editor asks for the what-it-covers line and skill 6 states it, so
     * without it the column would have been one nothing reads — CLAUDE.md's
     * 272 shape, in the slice that introduces it. Every existing call site and
     * the day-0 test pass neither and are unaffected.
     */
    public static function payment(
        string $label,
        string $destination,
        ?int $feeCents = null,
        ?string $feeCovers = null,
        ?string $feeCurrency = null,
    ): self {
        return new self(TenantLinkKind::Payment, $label, $destination, null, $feeCents, $feeCovers, $feeCurrency);
    }

    public static function document(string $label, string $destination, string $slug): self
    {
        return new self(TenantLinkKind::Document, $label, $destination, $slug);
    }

    /**
     * The business's own URL.
     *
     * ⚠️ **FOR MINTING A SHORT LINK AND FOR SHOWING THE BUSINESS ITS OWN
     * SETTINGS — NOT FOR COMPOSING A MESSAGE.** See the class docblock: a
     * message carries the short link, always, and P19's link-invention eval is
     * what proves it.
     */
    public function destination(): string
    {
        return $this->destination;
    }

    /**
     * Whether skill 6 (call-out fee) has everything it needs: a payment link
     * *and* a fee to name (R13).
     *
     * ⚠️ **THE COVERS LINE IS NOT PART OF THE GROUNDING, DELIBERATELY.** R13
     * names the grounding for skill 6 as *"fee amount + payment URL"* and
     * nothing else. Requiring the sentence as well would switch the skill off
     * for a business that set a figure and did not feel like explaining it —
     * absent rather than defaulted is R13's rule for a missing *fact*, and a
     * missing paragraph is not one.
     */
    public function groundsFeeCollection(): bool
    {
        return $this->kind === TenantLinkKind::Payment && $this->feeCents !== null;
    }

    /**
     * The fee as money rather than as a bare integer.
     *
     * ⚠️ **THROWS RATHER THAN GUESSING A CURRENCY**, which is the direction `18`
     * §Money handling and {@see Money} both take: a bare integer of cents is the
     * shape that lets one currency be added to another. `null` is returned only
     * for the honest case — the business set no fee.
     *
     * ⚠️ **THE THROW IS REACHABLE ONLY FROM A HAND-BUILT DTO.** The store's
     * CHECK constraint makes `fee_cents` and `fee_currency` stand or fall
     * together, so every link this application's registry hands out has both;
     * what can reach it is a `TenantLink::payment(…, 8500)` written in a test,
     * and a loud failure there is better than a quoted figure in no currency.
     */
    public function fee(): ?Money
    {
        if ($this->feeCents === null) {
            return null;
        }

        if ($this->feeCurrency === null || trim($this->feeCurrency) === '') {
            throw new InvalidArgumentException(
                'This payment link carries a fee of '.$this->feeCents.' minor units and no currency, '
                .'so there is no amount to quote. A stored link always carries both — see the '
                .'tenant_links_fee_belongs_to_payment constraint — so this DTO was built by hand.'
            );
        }

        return Money::of($this->feeCents, $this->feeCurrency);
    }
}
