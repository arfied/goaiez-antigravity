<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Config\DefaultsRegistry;
use Illuminate\Console\Command;

/**
 * Is the sending domain actually authenticated? — T137 R3's DNS half.
 *
 * ⚠️ **THIS IS THE ONE PART OF SL-4 NO CODE CAN DO, AND IT IS ON THE CRITICAL
 * PATH FOR EVERY TRANSPORT.** R3: *"SPF/DKIM/DMARC still go on the goaiez
 * sending domain (DNS, ~1 hour — required for inbox placement on ANY
 * transport)."* Without them the mail sends perfectly, the queue drains, every
 * screen reports success, and the messages land in spam for every tenant at
 * once — 1195's failure exactly, *"invisible in testing and total in
 * production"*. A command that says so out loud is the cheapest possible guard.
 *
 * ⚠️ **IT CHECKS FOR PRESENCE AND SHAPE, NOT FOR CORRECTNESS**, and the
 * difference is stated rather than left to be assumed. It cannot tell whether
 * an SPF record authorises the right sending IPs, whether a DKIM public key
 * matches the private key the transport signs with, or whether the DMARC policy
 * is the one the owner intended. What it catches is the failure that actually
 * happens: **a record that was never published, or published on the wrong
 * name.**
 *
 * ⚠️ **`dns_get_record()` READS THE RESOLVER THIS MACHINE USES.** A record
 * published minutes ago may still be cached as absent, and a split-horizon
 * resolver can answer differently from the internet. So a red result is worth
 * re-running before acting on, and a green one is a fact about this machine's
 * view.
 */
final class MailDnsCheck extends Command
{
    protected $signature = 'mail:dns-check {--selector=google : The DKIM selector to look for}';

    protected $description = 'Check that SPF, DKIM and DMARC are published for the platform sending domain';

    public function handle(DefaultsRegistry $defaults): int
    {
        $domain = $defaults->value('mail.sending_domain');

        if (! is_string($domain) || trim($domain) === '') {
            $this->error('No sending domain is configured (mail.sending_domain).');

            return self::FAILURE;
        }

        $domain = trim($domain);
        $selector = (string) $this->option('selector');

        $this->line("Sending domain: {$domain}");

        $results = [
            // SPF is a TXT record on the domain itself beginning `v=spf1`.
            // ⚠️ **MORE THAN ONE SPF RECORD IS WORSE THAN NONE** — RFC 7208 §4.5
            // says a domain with two `v=spf1` records is a permanent error, and
            // receivers treat it as a failure rather than merging them. That is
            // the misconfiguration a second vendor's setup wizard creates, so it
            // is reported separately from "missing".
            'SPF' => $this->check($domain, self::hasPrefix('v=spf1')),

            // DKIM is a TXT record at `<selector>._domainkey.<domain>`. Google
            // Workspace's own generated selector is `google` by default, which
            // is why that is the option's default; SES publishes three CNAMEs
            // instead, under selectors it generates — hence the flag. A CNAME
            // there is not a problem: the resolver follows it and returns the
            // TXT at the far end.
            //
            // ⛔ **THIS ARM ASKED FOR THE PREFIX `v=DKIM1` UNTIL 2026-08-20 AND
            // REPORTED A CORRECTLY PUBLISHED SES DOMAIN AS `not published`**
            // (5700). `v=` is OPTIONAL in DKIM — RFC 6376 §3.6.1 lists it as
            // RECOMMENDED and requires it be first *if present* — and Amazon
            // omits it, publishing the bare `p=<key>`. So the one check written
            // to catch a silent deliverability failure failed silently itself,
            // on every SES deployment, for ever. `isDkimRecord()` holds the rule
            // now, and it is driven against both vendors' real shapes.
            'DKIM' => $this->check($selector.'._domainkey.'.$domain, self::isDkimRecord(...)),

            // DMARC is a TXT record at `_dmarc.<domain>`. Its absence is the one
            // of the three that costs nothing today and everything later: with
            // no DMARC record there is no reporting, so the first sign of a
            // deliverability problem is a customer saying they never got the
            // email.
            'DMARC' => $this->check('_dmarc.'.$domain, self::hasPrefix('v=DMARC1')),
        ];

        $failed = false;

        foreach ($results as $label => [$found, $count]) {
            if ($found && $count === 1) {
                $this->info("  {$label}: published");

                continue;
            }

            $failed = true;

            $this->error($found
                ? "  {$label}: {$count} records found — more than one is a permanent error at the receiver"
                : "  {$label}: not published");
        }

        if ($failed) {
            $this->newLine();
            $this->warn(
                'Mail will still send and will still be accepted by the transport. It will not '
                .'reliably reach an inbox. Publish the records before the first customer-facing send.'
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  callable(string): bool  $matches
     * @return array{0: bool, 1: int} whether any record was found, and how many
     */
    private function check(string $name, callable $matches): array
    {
        // `@` because a domain with no records at all makes this emit a warning
        // and return false, and a warning on a console command reads as a crash.
        $records = @dns_get_record($name, DNS_TXT);

        if (! is_array($records)) {
            return [false, 0];
        }

        $matching = 0;

        foreach ($records as $record) {
            // ⚠️ `entries` RATHER THAN `txt`, AND BOTH ARE READ. A TXT record
            // longer than 255 bytes is published as several strings; PHP joins
            // them into `txt` and also exposes them separately in `entries`. A
            // long DKIM key is exactly that case, and reading only one of the
            // two misses it depending on the record's length.
            $value = '';

            if (isset($record['txt']) && is_string($record['txt'])) {
                $value = $record['txt'];
            } elseif (isset($record['entries']) && is_array($record['entries'])) {
                $value = implode('', array_filter($record['entries'], 'is_string'));
            }

            if ($matches($value)) {
                $matching++;
            }
        }

        return [$matching > 0, $matching];
    }

    /**
     * A record of this kind is one whose value opens with this version tag.
     *
     * SPF and DMARC both require their version tag first, so a prefix is the
     * whole rule for them. ⚠️ **DKIM is the exception and has its own matcher**
     * — see `isDkimRecord()`.
     *
     * @return callable(string): bool
     */
    private static function hasPrefix(string $prefix): callable
    {
        return static fn (string $value): bool => str_starts_with(
            mb_strtolower(trim($value)), mb_strtolower($prefix)
        );
    }

    /**
     * Is this TXT value a DKIM public key?
     *
     * ⛔ **THE VERSION TAG IS OPTIONAL AND AMAZON OMITS IT.** RFC 6376 §3.6.1
     * makes `v=` RECOMMENDED rather than required, and requires only that it be
     * the first tag *when it is present*; SES publishes `p=<key>` with no `v=`
     * at all, while Google Workspace publishes `v=DKIM1; k=rsa; p=<key>`. A
     * matcher that demanded the tag answered "not published" for a domain whose
     * DKIM was live and verified — 5700, and the reason this is a function with
     * a test rather than a string literal at the call site.
     *
     * ⚠️ **WHAT MAKES IT A KEY IS `p=`**, which RFC 6376 requires. A present-
     * but-wrong version tag is refused rather than ignored: `v=DKIM2` is not a
     * record this application should report as published, and treating an
     * unknown version as fine is how a future format change passes unnoticed.
     *
     * ⚠️ **AN EMPTY `p=` IS STILL PUBLISHED.** RFC 6376 §3.6.1 gives `p=` with
     * an empty value the meaning "this key has been revoked", which is a
     * deliberate statement by whoever published it and not an absence. This
     * command reports what is in DNS; it does not judge whether the key works.
     */
    public static function isDkimRecord(string $value): bool
    {
        $normalised = mb_strtolower(trim($value));

        if (str_starts_with($normalised, 'v=') && ! str_starts_with($normalised, 'v=dkim1')) {
            return false;
        }

        return preg_match('/(?:^|;)\s*p=/', $normalised) === 1;
    }
}
