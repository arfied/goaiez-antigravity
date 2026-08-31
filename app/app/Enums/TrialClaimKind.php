<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Billing\TrialEligibility;
use App\Services\Places\PlaceConfirmation;
use App\Support\HashedIp;

/**
 * The kinds of identity a signup can claim, ordered by how much they prove.
 *
 * ⛔ **NEITHER OF THESE IS A VERIFIED BUSINESS, AND THAT IS THE FINDING RATHER
 * THAN A GAP IN THIS ENUM.** Decision 2066 asks for "one trial per verified
 * business" and **nothing in this application verifies a business**: email
 * verification is switched off (`config/fortify.php` — `Features::emailVerification()`
 * is commented out), `businesses.ein` has no writer anywhere, and
 * `SupportSetting::forwarding_verified_at` is deliberately never written (2913).
 * So the strongest honest key is the strongest of these two, and each case says
 * what it does and does not stop.
 *
 * ⚠️ **A CLAIM IS EVIDENCE, NOT A VERDICT.** Nothing here decides anything;
 * {@see TrialEligibility} reads the claims and answers, so
 * that "what did we observe" and "what do we do about it" stay two questions.
 */
enum TrialClaimKind: string
{
    /**
     * A Google listing this account confirmed as its own.
     *
     * ✅ **THE STRONGEST KEY THIS CODEBASE HAS.** It is written only by
     * {@see PlaceConfirmation::confirm()}, which is the one
     * writer of `locations.google_place_id` and takes a literal `true $confirmed`
     * argument — a human pasted their own Maps URL and said "yes, that's us".
     * A place id is Google's identifier for one physical business, so two
     * accounts holding the same one are, on Google's own reckoning, the same
     * business.
     *
     * ⛔ **WHAT IT DOES NOT STOP, STATED PLAINLY BECAUSE THE OPPOSITE READING IS
     * THE NATURAL ONE.** `PlaceConfirmation` records that somebody *said* a
     * listing was theirs; it does not prove ownership, because nothing asks
     * Google. So this key stops **replay** — the same listing funding a second
     * account — and does not stop **impersonation** — a stranger's listing
     * funding a first one. Real verification is a Google Business Profile OAuth
     * connection proving management of the listing, which this application can
     * already make (`OauthConnection`) and does not require. Until it does, do
     * not describe this as proof of ownership anywhere.
     */
    case GoogleListing = 'google_listing';

    /**
     * The network the account registered from, as a keyed hash.
     *
     * ⚠️ **A VELOCITY SIGNAL AND NOT AN IDENTITY.** Written at provisioning from
     * {@see HashedIp}, so the address itself is never stored — and
     * an address is not a person in either direction: a household, an office and
     * a whole mobile carrier can share one, and one fraudster can hold a
     * thousand. It answers "did a burst of accounts arrive from one place" and
     * nothing else, which is why it can only ever contribute to withholding an
     * allowance, never to refusing a signup.
     *
     * ⚠️ **AND IT IS ABSENT FOR A REQUEST WITH NO RESOLVABLE ADDRESS**, which
     * `HashedIp` is explicit is a real answer rather than a placeholder. An
     * account with no origin claim contributes to no burst and is counted in
     * none — see `TrialEligibility::signupOriginRefusal()`, which says why that
     * fails toward silence rather than toward a refusal.
     */
    case SignupOrigin = 'signup_origin';
}
