<?php

declare(strict_types=1);

namespace App\Services\Links;

use App\Contracts\Links\LinkRegistry;
use App\Enums\ShortLinkPurpose;
use App\Enums\TenantLinkKind;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\TenantLinkRecord;
use App\Services\Agent\AgentNudges;
use App\Services\Config\DefaultsRegistry;
use App\Services\ShortLinks\ShortLinks;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * What links this business has given its assistant, and how one reaches a
 * message — T176 P6, the implementation behind {@see LinkRegistry}.
 *
 * ⛔ **THE ONLY READER AND WRITER OF `tenant_links`, HELD THERE BY A LINT**
 * (`tests/Feature/Architecture/LinksTest.php`), on the reasoning decisions 624
 * and 1223 give for every other store behind one — and with a sharper reason
 * here. {@see TenantLinkRecord} carries `destination` as a plain attribute, so a
 * second reader is a call site one property access away from putting a raw
 * tenant URL into a text message. R14 says every agent-sent link rides a short
 * link; the DTO this class returns is what makes that structural rather than
 * remembered, and reading the model elsewhere walks around it.
 *
 * ## R13 lives in what this class returns, not in a flag it sets
 *
 * `null` from {@see booking()} and `[]` from {@see documents()} are the correct,
 * ordinary answers for the majority of businesses, and they mean *the skill is
 * absent* rather than *the lookup failed*. Nothing here logs a missing link,
 * nothing warns about one, and nothing substitutes a default — an assistant told
 * it can book while holding nothing to book with is precisely the state in which
 * a model invents a plausible URL.
 */
final readonly class TenantLinks implements LinkRegistry
{
    /**
     * The label a booking link is stored under.
     *
     * ⚠️ **OURS RATHER THAN THE TENANT'S, FOR THE TWO SINGLE-VALUED KINDS.**
     * §2.4 asks the owner for a booking URL and a payment URL — not for what to
     * call them — and `CLAUDE.md`'s "never add a tenant-facing toggle" makes a
     * free-text label a support surface bought for nothing: there is exactly one
     * of each, so nothing needs naming to be told apart. A **document** is the
     * opposite case and does take the tenant's own words, because skill 7 has to
     * pick one of several by name.
     */
    public const string BOOKING_LABEL = 'Book a visit';

    public const string PAYMENT_LABEL = 'Pay your call-out fee';

    /**
     * The longest slug {@see document()} will take to the database.
     *
     * ⚠️ **THE ARGUMENT IS THE SAME AS `ShortLinks::resolve()`'s.** The value
     * arrives from skill 7, which picked it out of what a member of the public
     * asked for, by way of a model. Refusing an implausible one before the query
     * keeps an untrusted string from becoming a scan, and the ceiling matches the
     * column.
     */
    private const int MAX_SLUG_LENGTH = 255;

    public function __construct(
        private ShortLinks $shortLinks,
        private DefaultsRegistry $defaults,
    ) {}

    public function booking(): ?TenantLink
    {
        return $this->single(TenantLinkKind::Booking);
    }

    public function payment(): ?TenantLink
    {
        return $this->single(TenantLinkKind::Payment);
    }

    /**
     * @return array<string, TenantLink>
     */
    public function documents(): array
    {
        Tenancy::idOrFail();

        $documents = [];

        foreach ($this->query(TenantLinkKind::Document)->orderBy('label')->get() as $record) {
            $link = $this->toLink($record);

            if ($link->slug !== null) {
                $documents[$link->slug] = $link;
            }
        }

        return $documents;
    }

    /**
     * ⛔ **THE SLUG IS UNTRUSTED AND THIS IS WHERE THAT IS HANDLED.** It is
     * matched against this tenant's own rows through the scoped query — never
     * joined to a path, never used to build a URL, never looked up across
     * tenants. Anything that is not one of this business's documents is `null`,
     * which skill 7 reads as *we do not share that* rather than as an error.
     */
    public function document(string $slug): ?TenantLink
    {
        Tenancy::idOrFail();

        $normalised = trim($slug);

        if ($normalised === '' || mb_strlen($normalised) > self::MAX_SLUG_LENGTH) {
            return null;
        }

        $record = $this->query(TenantLinkKind::Document)
            ->where('slug', $normalised)
            ->first();

        return $record instanceof TenantLinkRecord ? $this->toLink($record) : null;
    }

    /**
     * Mint the short link that actually goes in a message (R14).
     *
     * ⚠️ **PER SEND, AND THE CUSTOMER ON THE THREAD IS WHAT MAKES A CLICK MEAN
     * A PERSON.** Nothing here is cached and nothing is reused: two customers
     * sent the same price sheet get two tokens, so `ShortLinkClicks` can attribute
     * a fetch to one of them and `CustomerTimeline` can render it. One token per
     * link would collapse the whole thing back into a page view, which is the
     * distinction `short_links`' own migration was written for.
     *
     * ⚠️ **NO EXPIRY, ON `ReviewInviteSender::tracked()`'s REASONING.** A price
     * sheet opened a month later should reach the document rather than a bare
     * 404, which decision 2490 makes indistinguishable from a token that never
     * existed and would read as the business having gone.
     *
     * ⛔ **THE CONVERSATION MUST BE THIS TENANT'S.** The global scope means a
     * caller cannot ordinarily load somebody else's thread — this refuses the
     * case where one was carried across a tenant boundary in memory (a queued
     * job, a console command, a support path), because the consequence is a short
     * link filed against the wrong business's timeline.
     */
    public function shortLinkFor(TenantLink $link, Conversation $conversation): string
    {
        $business = Tenancy::idOrFail();

        if ((int) $conversation->business_id !== $business) {
            throw new RuntimeException(
                'Refusing to mint a short link for a conversation belonging to another business. '
                .'A click on it would be recorded against this tenant while the thread it was sent '
                .'on belongs to somebody else.'
            );
        }

        $url = $this->shortLinks->urlFor($this->shortLinks->mint(
            $link->destination(),
            $this->purposeFor($link->kind),
            $this->customerOn($conversation),
        ));

        // ⛔ **SKILL 14 IS ARMED HERE, AND THIS IS THE ONE PLACE §2.2's TRIGGER
        // ACTUALLY HAPPENS (P11).** Its trigger column names a quote, a fee and
        // a booking link, and all three leave through this method — so arming
        // here keeps the rule *"a nudge fires because a link went out"* rather
        // than the looser *"because a thread went quiet"*, which would follow up
        // with people who got a complete answer.
        //
        // ⚠️ **AND IT STRUCTURALLY EXCLUDES P12's REVIEW LINK**, which is minted
        // through `ShortLinks::mint()` by `ReviewAskBridge` and never reaches
        // this method. Nudging somebody who did not answer a review invitation
        // would be a second solicitation to review.
        //
        // ⚠️ **NULL IS THE ORDINARY ANSWER AND IS DISCARDED**: the thread may
        // already owe one, the business may have switched the nudge off, or
        // there may be no contact to follow up with. None of those is a reason
        // to fail the send of the link itself.
        app(AgentNudges::class)->arm($conversation);

        return $url;
    }

    /**
     * @return array<value-of<TenantLinkKind>, bool>
     */
    public function grounded(): array
    {
        $payment = $this->payment();

        return [
            TenantLinkKind::Booking->value => $this->booking() instanceof TenantLink,

            // ⚠️ **THE PAYMENT KEY ANSWERS FOR SKILL 6, WHICH NEEDS THE FEE AS
            // WELL AS THE LINK** (R13: *"fee amount + payment URL → fee"*). A
            // payment link with no fee is still returned by `payment()` — a
            // customer who asks to pay can be sent somewhere — but it does not
            // ground the skill that quotes a call-out charge, and this map is
            // what the agent assembles its capabilities from.
            TenantLinkKind::Payment->value => $payment instanceof TenantLink && $payment->groundsFeeCollection(),

            TenantLinkKind::Document->value => $this->documents() !== [],
        ];
    }

    /**
     * Set the business's booking link.
     */
    public function setBooking(string $destination): TenantLink
    {
        return $this->toLink($this->writeSingle(TenantLinkKind::Booking, self::BOOKING_LABEL, $destination));
    }

    /**
     * Set the business's payment link, with the fee skill 6 is grounded on.
     *
     * ⚠️ **`$feeCents` OF `null` AND `0` ARE TWO DIFFERENT INSTRUCTIONS AND THIS
     * SIGNATURE KEEPS THEM APART.** `null` is *I have not said*, which switches
     * skill 6 off; `0` is *we come out free*, which grounds it. A truthiness
     * check anywhere on this path collapses the two.
     */
    public function setPayment(string $destination, ?int $feeCents = null, ?string $feeCovers = null): TenantLink
    {
        if ($feeCents !== null && $feeCents < 0) {
            throw new InvalidArgumentException('A call-out fee cannot be negative.');
        }

        $covers = $feeCovers === null || trim($feeCovers) === '' ? null : trim($feeCovers);

        if ($covers !== null && $feeCents === null) {
            throw new InvalidArgumentException(
                'A what-it-covers line with no fee would have the assistant explaining the scope of '
                .'a charge it cannot name. Set the fee, or leave both unset.'
            );
        }

        return $this->toLink($this->writeSingle(
            TenantLinkKind::Payment,
            self::PAYMENT_LABEL,
            $destination,
            $feeCents,
            $covers,
        ));
    }

    /**
     * Share a document under a name the assistant can pick it out by.
     *
     * ⚠️ **THE SLUG IS DERIVED FROM THE LABEL RATHER THAN TYPED**, on
     * `CLAUDE.md`'s no-toggles rule: a tenant naming a document "Price sheet" has
     * said everything a slug needs, and a second field asking for a handle is a
     * support surface plus a second thing to get out of step with the first.
     *
     * ⚠️ **SO NAMING IT AGAIN MOVES IT, AND RENAMING IT ADDS A SECOND ONE.** The
     * slug is the identity, so "Price sheet" with a new URL replaces the old one
     * while "Our prices" is a different document — which is the honest reading of
     * *"the name a customer would ask for"* and is why the screen offers add and
     * remove rather than an edit. A rename is remove-then-add, and the owner can
     * see both rows while they do it.
     */
    public function addDocument(string $label, string $destination): TenantLink
    {
        Tenancy::idOrFail();

        $name = trim($label);

        if ($name === '') {
            throw new InvalidArgumentException('A shared document needs a name the assistant can offer it by.');
        }

        $slug = Str::slug($name);

        if ($slug === '') {
            // Str::slug() strips everything it cannot transliterate, so a name
            // made entirely of punctuation or of an unsupported script leaves
            // nothing to address the document by. Refused rather than replaced
            // with a generated handle nobody typed and nobody could ask for.
            throw new InvalidArgumentException(
                'That name has no letters or numbers in it, so the assistant would have no way to '
                .'offer it. Use a name a customer might ask for, like "Price sheet".'
            );
        }

        $record = $this->query(TenantLinkKind::Document)->where('slug', $slug)->first()
            ?? new TenantLinkRecord;

        $record->forceFill([
            'kind' => TenantLinkKind::Document,
            'label' => $name,
            'destination' => $this->normaliseDestination($destination),
            'slug' => $slug,
        ])->save();

        return $this->toLink($record);
    }

    /**
     * Stop sharing one link.
     *
     * ⚠️ **DELETED RATHER THAN DISABLED, WHICH IS THE OPPOSITE CALL TO
     * `short_links`' `revoked_at`.** There the row is evidence about a message
     * already in somebody's inbox; here it is a standing instruction, and R13's
     * whole design is that its absence is the off state. A `disabled_at` would be
     * a second way to be off, and the two would disagree on whichever screen read
     * only one of them. **A short link already minted from this row keeps
     * working**, deliberately — the target URL is copied at mint time, so a
     * customer holding a text does not get a 404 because the owner tidied up.
     */
    public function remove(TenantLinkKind $kind, ?string $slug = null): void
    {
        Tenancy::idOrFail();

        $query = $this->query($kind);

        if ($kind === TenantLinkKind::Document) {
            $normalised = trim((string) $slug);

            if ($normalised === '') {
                throw new InvalidArgumentException('Removing a shared document needs the name it is stored under.');
            }

            $query->where('slug', $normalised);
        }

        $query->delete();
    }

    private function single(TenantLinkKind $kind): ?TenantLink
    {
        Tenancy::idOrFail();

        $record = $this->query($kind)->first();

        return $record instanceof TenantLinkRecord ? $this->toLink($record) : null;
    }

    /**
     * @return Builder<TenantLinkRecord>
     */
    private function query(TenantLinkKind $kind): Builder
    {
        return TenantLinkRecord::query()->where('kind', $kind->value);
    }

    private function writeSingle(
        TenantLinkKind $kind,
        string $label,
        string $destination,
        ?int $feeCents = null,
        ?string $feeCovers = null,
    ): TenantLinkRecord {
        Tenancy::idOrFail();

        $record = $this->query($kind)->first() ?? new TenantLinkRecord;

        $record->forceFill([
            'kind' => $kind,
            'label' => $label,
            'destination' => $this->normaliseDestination($destination),
            'slug' => null,
            'fee_cents' => $feeCents,
            'fee_currency' => $feeCents === null ? null : $this->currency(),
            'fee_covers' => $feeCovers,
        ])->save();

        return $record;
    }

    /**
     * ⛔ **A DESTINATION ON OUR OWN SHORT DOMAIN IS REFUSED.** Minting a short
     * link that points at a short link is a redirect chain at best; at worst the
     * pasted token belongs to another tenant's send, and this tenant's assistant
     * would hand a customer somebody else's tracked link. The domain is data
     * rather than a constant, so this is a comparison rather than a grep —
     * `ShortLinksTest` records why that distinction is the one that survives.
     */
    private function normaliseDestination(string $destination): string
    {
        $trimmed = trim($destination);

        if ($trimmed === '') {
            throw new InvalidArgumentException('A link needs somewhere to send people.');
        }

        $scheme = strtolower((string) parse_url($trimmed, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException(
                'A link has to start with http:// or https:// — anything else is not something a '
                .'phone will open.'
            );
        }

        $host = strtolower((string) parse_url($trimmed, PHP_URL_HOST));

        if ($host === '') {
            throw new InvalidArgumentException('That link has no website in it.');
        }

        if ($host === strtolower($this->shortLinks->domain())) {
            throw new InvalidArgumentException(
                'That is one of our own tracking links rather than your own page. Paste the address '
                .'your booking, payment or document actually lives at.'
            );
        }

        return $trimmed;
    }

    /**
     * ⚠️ **THE PLATFORM CURRENCY, READ AT WRITE TIME AND STORED.** `18` §Money
     * handling makes the currency part of the value; reading it at *read* time
     * instead would re-denominate every fee a tenant already set the day the
     * registry seed changes.
     */
    private function currency(): string
    {
        $stored = $this->defaults->value('billing.currency');

        return is_string($stored) && trim($stored) !== '' ? strtoupper(trim($stored)) : 'USD';
    }

    private function purposeFor(TenantLinkKind $kind): ShortLinkPurpose
    {
        return match ($kind) {
            TenantLinkKind::Booking => ShortLinkPurpose::TenantBooking,
            TenantLinkKind::Payment => ShortLinkPurpose::TenantPayment,
            TenantLinkKind::Document => ShortLinkPurpose::TenantDocument,
        };
    }

    /**
     * The contact the thread belongs to, when it has one.
     *
     * ⚠️ **NULL IS A REAL ANSWER** — a missed call from somebody who is not a
     * contact yet. `short_links.customer_id` is nullable for exactly this, and
     * the click is then recorded as having happened and attributed to nobody,
     * which is honest; inventing a contact from a click would be the alternative.
     */
    private function customerOn(Conversation $conversation): ?Customer
    {
        $customerId = $conversation->customer_id;

        if ($customerId === null) {
            return null;
        }

        return Customer::query()->find($customerId);
    }

    private function toLink(TenantLinkRecord $record): TenantLink
    {
        return match ($record->kind) {
            TenantLinkKind::Booking => TenantLink::booking($record->label, $record->destination),
            TenantLinkKind::Payment => TenantLink::payment(
                $record->label,
                $record->destination,
                $record->fee_cents,
                $record->fee_covers,
                $record->fee_currency,
            ),
            TenantLinkKind::Document => TenantLink::document(
                $record->label,
                $record->destination,
                (string) $record->slug,
            ),
        };
    }
}
