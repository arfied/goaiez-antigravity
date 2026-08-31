<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * The block CAN-SPAM requires at the bottom of a commercial email — T176 P21.
 *
 * 15 U.S.C. §7704(a)(5)(A) asks for three things in the message itself: clear
 * identification that it is an advertisement or solicitation, notice of the
 * opportunity to decline further messages, and *"a valid physical postal address
 * of the sender"*. This renders all three, in both bodies.
 *
 * ⚠️ **BOTH BODIES, AND THE PLAIN-TEXT ONE IS NOT OPTIONAL.** A recipient
 * reading in a text-only client, or a mailbox provider scoring the multipart
 * alternative, sees only that part — and a footer present in the HTML and absent
 * from the text is a message that is compliant in one rendering and not in the
 * other. `MailMessage` always produces both, so both are always there to append
 * to.
 *
 * ⚠️ **THE ADDRESS IS OURS AND THE BUSINESS NAME IS THEIRS, AND THAT PAIRING IS
 * A JUDGEMENT** (decision 4020). The mail leaves `goaieasy.net` under
 * `"{Business} via GO AI EZ"`, so the transmitting sender is us and the postal
 * address that has to be valid is ours. The tenant's name appears because a
 * recipient asked *them* for nothing and deserves to know which business
 * prompted the message; the tenant's own address is not printed, because
 * `locations.address` is nullable and a footer that is sometimes complete and
 * sometimes not is worse than one that is consistently ours.
 *
 * ⚠️ **NOTHING HERE IS TENANT-SUPPLIED EXCEPT THE BUSINESS NAME, AND IT IS
 * ESCAPED.** `PlatformMailIdentity::fromName()` makes the same point about the
 * `From:` header: a trading name is text somebody typed, and the HTML rendering
 * is the one place it could carry markup.
 */
final readonly class CanSpamFooter
{
    public function __construct(
        /**
         * Who is sending — the platform's own name, matching the `From:`
         * display name's second half.
         */
        public string $senderName,

        /**
         * The tenant this went out on behalf of, or null for a commercial
         * message that is ours alone.
         */
        public ?string $businessName,

        /**
         * `mail.postal_address`, verbatim. Multi-line is expected and each line
         * is rendered as its own line rather than joined.
         */
        public string $postalAddress,

        /**
         * The RFC 8058 one-click endpoint — the same URL the
         * `List-Unsubscribe` header carries, so a recipient who does not
         * trust their mail client's button and a recipient who does end up in
         * exactly the same place.
         */
        public string $unsubscribeUrl,
    ) {}

    /**
     * The plain-text rendering.
     *
     * ⚠️ **THE URL IS BARE RATHER THAN WRAPPED IN ANGLE BRACKETS.** Some clients
     * linkify a bare URL and stop at the `>` of a wrapped one, producing a link
     * that 404s while looking correct.
     */
    public function asText(): string
    {
        $lines = ['', str_repeat('-', 40), $this->intro()];

        $lines[] = '';
        $lines[] = $this->senderName;

        foreach ($this->addressLines() as $line) {
            $lines[] = $line;
        }

        $lines[] = '';
        $lines[] = 'Stop receiving these emails: '.$this->unsubscribeUrl;

        return implode("\n", $lines)."\n";
    }

    /**
     * The HTML rendering.
     *
     * Inline styles only: an email client strips `<style>` blocks and none of
     * them load the application's stylesheet. The colour is `#4b5563` on the
     * template's white, which is 7.5:1 and clears WCAG 2.2 AA with room —
     * `22`'s rule, and a grey footer is the commonest place it gets broken.
     */
    public function asHtml(): string
    {
        $address = implode('<br>', array_map(
            static fn (string $line): string => e($line),
            $this->addressLines(),
        ));

        $url = e($this->unsubscribeUrl);

        return '<div style="margin:24px auto 0;max-width:570px;padding:16px 24px;'
            .'border-top:1px solid #e5e7eb;color:#4b5563;line-height:1.5;">'
            .'<p style="margin:0 0 12px;">'.e($this->intro()).'</p>'
            .'<p style="margin:0 0 12px;">'.e($this->senderName).'<br>'.$address.'</p>'
            .'<p style="margin:0;"><a href="'.$url.'" style="color:#4b5563;">'
            .'Stop receiving these emails</a></p>'
            .'</div>';
    }

    /**
     * §7704(a)(5)(A)(i)'s identification, in `22`'s outcome language.
     *
     * ⚠️ **IT SAYS WHY THE MESSAGE ARRIVED, NOT WHAT THE SYSTEM DID.** "You are
     * receiving this because…" is what a recipient can act on; "sent via our
     * transactional relay" is a fact about us.
     */
    private function intro(): string
    {
        $business = $this->businessName === null ? null : self::flattened($this->businessName);

        if ($business === null || $business === '') {
            return 'You received this email from '.$this->senderName.'.';
        }

        return 'You received this email because '.$business
            .' asked '.$this->senderName.' to send it on their behalf.';
    }

    /**
     * A tenant-supplied trading name with its control characters removed.
     *
     * ⚠️ **THE PLAIN-TEXT BODY HAS NO ESCAPING AND THAT IS WHERE THIS MATTERS.**
     * The HTML rendering runs everything through `e()`, so markup in a business
     * name is already inert there. The text part has nothing equivalent: a
     * newline in a trading name would put a line of the tenant's choosing into
     * a compliance footer, immediately above our own postal address, where it
     * reads as ours. `PlatformMailIdentity::fromName()` makes the identical
     * argument about the `From:` header and strips rather than rejects, for its
     * reason — a stray tab in a trading name must not stop that tenant's mail.
     */
    private static function flattened(string $value): string
    {
        return trim((string) preg_replace('/[\p{C}]+/u', ' ', $value));
    }

    /**
     * @return list<string>
     */
    private function addressLines(): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $this->postalAddress) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), $lines),
            static fn (string $line): bool => $line !== '',
        ));
    }
}
