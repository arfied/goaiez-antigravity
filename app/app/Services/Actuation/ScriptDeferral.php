<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\SpeedFix;
use App\Services\Config\DefaultsRegistry;

/**
 * `28` §4.2 — *"the only risky fix"*.
 *
 * *"Deferral applies **only** to a maintained allowlist of known-safe
 * third-party scripts (analytics, chat bubbles, social embeds, tag managers in
 * safe configurations). Never defer: payment scripts, booking engines, anything
 * matching a form or checkout signature. The allowlist ships in code, is
 * admin-extendable in the Ops Console, and every deferral is its own change
 * set."*
 *
 * ## Two lists, and the order they are asked in is the decision
 *
 * ⛔ **THE ALLOWLIST IS ASKED FIRST AND THE REFUSAL LIST IS ASKED LAST, WHICH IS
 * THE ONLY ORDER THAT LEAVES THE REFUSAL FALSIFIABLE** (398). Ask the refusal
 * first and every payment script is already refused by not being on the
 * allowlist, so deleting the refusal list entirely leaves a green suite — the
 * outer-guard shape this codebase has been bitten by four times. Asked last, the
 * refusal has one and only one live instance: **a payment or booking script an
 * operator has added to the Ops allowlist**, which is the realistic accident and
 * is exactly what the test plants.
 *
 * ⛔ **SO AN OPERATOR CANNOT ALLOW A PAYMENT SCRIPT, AND THAT IS THE POINT OF
 * THE ORDERING RATHER THAN A SIDE EFFECT.** `28` §4.2 says *never*, and a rule
 * an Ops row can switch off is not one. The Ops list widens what may be
 * deferred; it cannot narrow what may not.
 *
 * ## What the shipped list deliberately does not contain
 *
 * ⛔ **NO GENERAL-PURPOSE CDN.** `cdn.jsdelivr.net`, `unpkg.com` and
 * `cdnjs.cloudflare.com` serve whatever anybody asks them to, including every
 * payment and booking library on the refusal list below. Allowlisting a CDN
 * allowlists its contents, so the host-level answer would be *"defer anything at
 * all, as long as it arrived through this door"* — which is the allowlist tuned
 * until it catches nothing (511) reached from the other end.
 *
 * ⚠️ **THE MATCH IS ON HOST AND PATH AND NEVER ON THE QUERY STRING** — 5529's
 * rule, and it earns its keep twice here: a query string is where a booking
 * reference or an email address ends up, and a signature like `cart` matched
 * against one would refuse `gtm.js?id=GTM-CART` for a reason that has nothing to
 * do with a shopping cart.
 */
final readonly class ScriptDeferral
{
    /**
     * The allowlist that ships in code — §4.2's four named categories and
     * nothing else.
     *
     * ⚠️ **HOSTS, EXACT, NEVER SUFFIXES.** `str_ends_with($host, 'hotjar.com')`
     * also matches `hotjar.com.example.net`, which somebody else can register —
     * the same reasoning that keeps `PlatformMailer`'s sending-domain check an
     * exact comparison (5506).
     *
     * @var list<string>
     */
    public const array SHIPPED = [
        // Tag managers and analytics.
        'www.googletagmanager.com',
        'www.google-analytics.com',
        'ssl.google-analytics.com',
        'plausible.io',
        'cdn.usefathom.com',
        'static.hotjar.com',
        'script.hotjar.com',
        // Chat bubbles.
        'widget.intercom.io',
        'js.intercomcdn.com',
        'embed.tawk.to',
        'static.zdassets.com',
        // Social embeds.
        'connect.facebook.net',
        'platform.twitter.com',
        'platform.instagram.com',
        'assets.pinterest.com',
    ];

    /**
     * The Ops key an operator may add hosts to — §4.2's *"admin-extendable in
     * the Ops Console"*.
     *
     * ⚠️ **IT ADDS, IT NEVER REPLACES.** A key that replaced the shipped list
     * would let an empty edit silently switch the whole fix off — total, silent
     * and indistinguishable from working, which is the unset SES sending
     * ceiling's failure mode with its sign flipped (4604).
     */
    public const string EXTRA_HOSTS_KEY = 'speed.script_deferral_extra_hosts';

    /**
     * Hosts that are never deferred, whatever any allowlist says.
     *
     * @var list<string>
     */
    public const array FORBIDDEN_HOSTS = [
        // Payments.
        'js.stripe.com',
        'checkout.stripe.com',
        'www.paypal.com',
        'www.paypalobjects.com',
        'js.braintreegateway.com',
        'web.squarecdn.com',
        'js.squareup.com',
        'x.klarnacdn.net',
        'checkout.razorpay.com',
        'js.authorize.net',
        'pay.google.com',
        // Booking engines.
        'assets.calendly.com',
        'embed.acuityscheduling.com',
        'cdn.mindbodyonline.com',
        'book.squareup.com',
    ];

    /**
     * Signatures matched anywhere in the host and path.
     *
     * ⛔ **THE HOST LIST ABOVE CANNOT BE COMPLETE AND MUST NOT BE RELIED ON TO
     * BE.** A local business's booking engine is as likely to be a regional
     * vendor nobody here has heard of as it is to be Calendly, and the cost of
     * missing one is a customer unable to pay or book on their own website with
     * a green suite behind it. So the host list is the part that is certain and
     * this is the part that is cautious: a false refusal costs a fix that was
     * never applied, and a false permission costs somebody a booking.
     *
     * ⚠️ **`captcha` AND ITS FAMILY ARE HERE BECAUSE §4.2 SAYS *"anything
     * matching a form … signature"***, and a deferred challenge widget is a
     * contact form that silently stops submitting — which looks exactly like a
     * quiet week rather than like a fault.
     *
     * @var list<string>
     */
    public const array FORBIDDEN_SIGNATURES = [
        'checkout',
        'payment',
        '/pay',
        'billing',
        'stripe',
        'paypal',
        'braintree',
        'adyen',
        'klarna',
        'authorize.net',
        'booking',
        'book-now',
        'reserve',
        'appointment',
        'schedul',
        'cart',
        'recaptcha',
        'hcaptcha',
        'turnstile',
    ];

    public function __construct(private DefaultsRegistry $registry) {}

    /**
     * May {@see SpeedFix::ScriptDeferral} touch this script?
     *
     * ⚠️ **A URL THIS CANNOT PARSE IS REFUSED.** Everything here is a fact about
     * a host, so a string with no host is a string about which no safety claim
     * can be made.
     */
    public function mayDefer(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = mb_strtolower($host);

        if (! in_array($host, $this->allowed(), true)) {
            return false;
        }

        return ! $this->isForbidden($host, $url);
    }

    /**
     * Every host that may be deferred: the shipped list plus the Ops additions.
     *
     * @return list<string>
     */
    public function allowed(): array
    {
        $extra = $this->registry->value(self::EXTRA_HOSTS_KEY);

        if (! is_array($extra)) {
            return self::SHIPPED;
        }

        $hosts = self::SHIPPED;

        foreach ($extra as $host) {
            if (is_string($host) && trim($host) !== '') {
                $hosts[] = mb_strtolower(trim($host));
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * Only the scripts that may be deferred, in the order they were offered.
     *
     * @param  list<string>  $urls
     * @return list<string>
     */
    public function deferrable(array $urls): array
    {
        return array_values(array_filter($urls, fn (string $url): bool => $this->mayDefer($url)));
    }

    /**
     * Does this script wear a payment, booking or checkout signature?
     *
     * ⚠️ **THE QUERY STRING IS CUT OFF BEFORE THE SIGNATURES ARE MATCHED**, and
     * a URL whose path cannot be read is treated as a bare host rather than
     * being waved through — the whole string is never matched, because that is
     * how a signature ends up firing on a campaign parameter.
     */
    private function isForbidden(string $host, string $url): bool
    {
        if (in_array($host, self::FORBIDDEN_HOSTS, true)) {
            return true;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $subject = $host.(is_string($path) ? mb_strtolower($path) : '');

        foreach (self::FORBIDDEN_SIGNATURES as $signature) {
            if (str_contains($subject, $signature)) {
                return true;
            }
        }

        return false;
    }
}
