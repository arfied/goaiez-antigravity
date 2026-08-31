<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Whether a hostname reaches the public internet, asked before a socket opens.
 *
 * ⛔ **THIS IS AN SSRF CHECK AND IT EXISTS BECAUSE ONE URL IN THIS APPLICATION
 * IS TYPED IN BY A CUSTOMER.** `locations.website_url` is pasted by a business
 * owner and then fetched by our own servers with a credential attached, which is
 * the shape that turns a web form into a request from inside our network. Every
 * other outbound destination in `app/` is a vendor host from configuration.
 *
 * ⚠️ **THE THIRD COPY OF THIS LOGIC, AND THE FIRST ONE WITH A NAME.**
 * `App\Services\Places\ShortLinkResolver` and
 * `App\Services\Sms\InboundMediaFetcher` each carry a private pair of methods
 * doing exactly this. They are deliberately **not** edited here — both sit in
 * areas other branches are working in, and a refactor across three files to save
 * twenty lines is the trade `CLAUDE.md` warns about in the other direction. What
 * this class buys is that the *fourth* caller has somewhere to go, and the two
 * existing copies have a home to move to. That move is owed and recorded.
 *
 * ⚠️ **IT DOES NOT DEFEAT DNS REBINDING AND MUST NOT BE DESCRIBED AS IF IT
 * DID.** The name is resolved here and resolved again by the HTTP client when
 * the socket opens, and nothing pins the answer between the two. What it does
 * catch is the ordinary case — a literal private address, `localhost`, a name
 * whose A record points inside — which is every accident and most of the
 * casual attempts.
 */
final class PublicAddress
{
    /**
     * ⚠️ **AN IP LITERAL IS CHECKED DIRECTLY AND NEVER RESOLVED.**
     * `dns_get_record('127.0.0.1')` does not answer the question that was asked.
     */
    public static function reaches(string $host): bool
    {
        $host = mb_strtolower(trim($host, " \t\n\r\0\x0B[]"));

        if ($host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublic($host);
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        // ⚠️ **A NAME THAT DOES NOT RESOLVE IS REFUSED RATHER THAN ALLOWED.**
        // Failing open here would make an outage in our own resolver into
        // permission to fetch anything.
        if ($records === false || $records === []) {
            return false;
        }

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (! is_string($address) || ! self::isPublic($address)) {
                return false;
            }
        }

        return true;
    }

    public static function isPublic(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
