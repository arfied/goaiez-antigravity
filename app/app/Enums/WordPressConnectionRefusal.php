<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Actuation\WordPress\WordPressCredentials;
use App\Support\OutboundSiteBudget;

/**
 * Why this platform declined to take a WordPress login — `BUILD-PLAN` §2.11.3
 * slice F1, decisions 5586–5590.
 *
 * ⚠️ **A REFUSAL IS A RETURN VALUE AND ITS SENTENCE IS PART OF THE PRODUCT.**
 * `22`'s rule: every string names what the person controls, never how the system
 * is built. An owner who pastes an administrator's Application Password has done
 * the obvious thing, not a wrong thing, and the sentence they get back has to
 * tell them what to do next — which is why {@see self::owner()} exists here and
 * not on a screen. The screen renders it; only this file decides it.
 *
 * ⛔ **`TooPowerful` IS §19.7's GATE SPEAKING, AND IT IS THE ONLY PLACE THE ROLE
 * REQUIREMENT IS EXPRESSED TO A HUMAN IN THIS SLICE.** Decision 5582: an
 * Application Password carries the capabilities of the user it belongs to and is
 * not a scope, so *"the credential cannot install plugins"* is a fact about the
 * WordPress user the owner chose. The probe is the mechanism; this sentence is
 * how the owner is asked for a different one.
 */
enum WordPressConnectionRefusal: string
{
    /**
     * The site was given as `http://`, or the site's own discovery answer was.
     *
     * ⛔ **NEVER SILENTLY UPGRADED TO `https://`.** An Application Password is
     * sent as Basic Auth — reversible base64, not a hash — so a downgraded
     * request hands a working login to anybody on the path. Rewriting the scheme
     * for the owner would also mean guessing that the site answers on TLS at
     * all, and a guess that is wrong fails *after* the credential has been sent.
     */
    case NotHttps = 'not_https';

    /**
     * Nothing at the address answered as a WordPress REST API.
     */
    case NoRestApi = 'no_rest_api';

    /**
     * The REST API is there and does not offer `wp/v2`.
     *
     * ⚠️ **A REAL CASE RATHER THAN A DEFENSIVE ONE.** WordPress's own discovery
     * documentation warns that *"WordPress 4.4 enabled the API infrastructure
     * for all sites, but did **not** include the core endpoints"*, and a
     * security plugin removing the `wp/v2` namespace is an ordinary hardening
     * step on a small-business site.
     */
    case NoCoreEndpoints = 'no_core_endpoints';

    /**
     * The username and Application Password were not accepted.
     */
    case NotAuthenticated = 'not_authenticated';

    /**
     * ⛔ §19.7's GATE. The credential can install plugins, edit users, or change
     * settings — so it can execute code on the site.
     */
    case TooPowerful = 'too_powerful';

    /**
     * The credential cannot edit the pages it would have to edit.
     *
     * ⚠️ **THE FLOOR AND THE CEILING ARE BOTH REAL AND THEY ALMOST MEET.**
     * Editing a page somebody else wrote needs `edit_others_pages` and
     * `edit_published_pages`, which an Author does not have; installing a plugin
     * needs `install_plugins`, which an Editor does not. Accepting a credential
     * that cannot do the work would mean discovering it at the first write, on a
     * change set that has already been opened.
     */
    case TooWeak = 'too_weak';

    /**
     * The site answered, but not in a shape this adapter can read.
     */
    case Unreadable = 'unreadable';

    /**
     * The owner's own web server asked this platform for room, and the wait it
     * asked for is still running.
     *
     * ⛔ **NOT `NoRestApi` AND NOT `Unreadable`, BECAUSE BOTH OF THOSE ARE
     * CLAIMS ABOUT THE OWNER'S WEBSITE AND THIS IS A CLAIM ABOUT US** (6262).
     * Telling somebody *"we could not find WordPress at that address"* because
     * their host asked this platform to slow down twenty minutes ago sends them
     * to their web person about a problem they do not have — 231's confident
     * mismatch, made to the one person who can least check it.
     *
     * ⛔ **THIS CASE SAID *"WE DID NOT ASK, BECAUSE WE ARE INSIDE A COOLDOWN OR
     * OVER OUR OWN PER-HOST BUDGET FOR THE WINDOW"* AND ITS SENTENCE SAID *"YOUR
     * WEBSITE ASKED US TO SLOW DOWN"* — BOTH READINGS ARE KEPT AND THE SECOND
     * WAS FALSE FOR HALF OF THE FIRST** (9754, corrected 9800–9819). The type
     * was honest about the two causes and {@see self::owner()} was not, so 6262
     * fixed the neighbouring arm — a cool-down arriving as `NoRestApi` — while
     * this one went on telling an owner their website had spoken when
     * {@see OutboundSiteBudget}'s own cap was what had stopped us. **The
     * corrected neighbour is what made the uncorrected sentence read as
     * considered.** ⚠️ **The case is narrowed rather than renamed**: `busy` is
     * what `wordpress.connection_refused` audit rows already carry, and it is
     * now true of every one written after this. What was ours moved to
     * {@see self::OurMinuteLimit} and {@see self::OurDayLimit}.
     */
    case Busy = 'busy';

    /**
     * **We** did not ask. Our own per-host budget for this minute is spent.
     *
     * ⛔ **THE SENTENCE MAY NOT MENTION THE OWNER'S WEBSITE DOING ANYTHING**,
     * because on this arm their website was never contacted at all — this is
     * {@see OutboundSiteBudget::REQUESTS_PER_MINUTE}, a number this platform
     * chose about its own politeness, and 9755's census found five independent
     * authors in this tree fixing this same shape one instance at a time.
     */
    case OurMinuteLimit = 'our_minute_limit';

    /**
     * **We** did not ask. Our own per-host budget for the day is spent.
     *
     * ⚠️ **SEPARATE FROM {@see self::OurMinuteLimit} BECAUSE THE NEXT ACTION IS
     * DIFFERENT** — see {@see OutboundSiteRefusal::DayBudgetSpent}. An owner in
     * front of a connect form is told *when* to come back, and a minute and a
     * day are not the same answer.
     */
    case OurDayLimit = 'our_day_limit';

    /**
     * The owner-facing refusal for a brake {@see OutboundSiteBudget} applied.
     *
     * ⛔ **A TOTAL `match` AND THE ONLY WAY TO REACH {@see self::Busy} FROM A
     * THROTTLE** (9800–9819). {@see WordPressCredentials} used to map the string
     * `'throttled'` onto `Busy` at two separate call sites, which is one arm of
     * a two-cause population reaching a sentence that is true of the other one;
     * a caller can no longer say *"your website asked us to slow down"* without
     * an {@see OutboundSiteRefusal::HostAskedForRoom} it got from the budget.
     * **No `default`**, so a brake added later is an `UnhandledMatchError` on
     * the connect path rather than a fourth cause quietly wearing one of these
     * three sentences.
     */
    public static function forOutboundBrake(OutboundSiteRefusal $brake): self
    {
        return match ($brake) {
            OutboundSiteRefusal::HostAskedForRoom => self::Busy,
            OutboundSiteRefusal::MinuteBudgetSpent => self::OurMinuteLimit,
            OutboundSiteRefusal::DayBudgetSpent => self::OurDayLimit,
        };
    }

    /**
     * What the owner is told, in words about their website.
     *
     * ⚠️ **NO VENDOR STRING EVER REACHES HERE.** A WordPress error body is
     * written by whatever plugin refused, can contain a path, a user login or a
     * SQL fragment, and is shown to a person. What the owner gets is one of the
     * sentences below and nothing else.
     *
     * ⚠️ **THIS SAID "ONE OF THESE SEVEN SENTENCES" UNTIL AN EIGHTH ARRIVED**
     * (6262), and the count is now gone rather than corrected a third time —
     * 9800–9819 added a ninth and a tenth. A count in prose beside the thing it
     * counts is `CLAUDE.md`'s 2505, and `match` with no `default` is what
     * actually holds the set closed.
     *
     * ⛔ **THE ONE SENTENCE HERE THAT NAMES SOMETHING THE OWNER'S WEBSITE DID IS
     * {@see self::Busy}'s, AND IT IS THE ONLY ONE THAT MAY.** The two brakes
     * below it are this platform's own and say so in the first clause; writing
     * either of them as a fact about the site is the defect 9754 found and
     * 9800–9819 closed, and it is the one change to this method that no test
     * outside `WordPressRefusalAttributionTest` would notice.
     */
    public function owner(): string
    {
        return match ($this) {
            self::NotHttps => 'Your website address has to start with https:// before we can connect. Ask whoever hosts your site to turn on the padlock, then try again.',
            self::NoRestApi => 'We could not find WordPress at that address. Check the address, or ask your web person whether the site is WordPress.',
            self::NoCoreEndpoints => 'Your WordPress has its editing interface switched off — often a security plugin. Ask your web person to turn the WordPress REST API back on.',
            self::NotAuthenticated => 'WordPress did not accept that login. Create a fresh application password in WordPress and paste it in again.',
            self::TooPowerful => 'That login can change your whole website — plugins, users and settings. We will not hold a login that powerful. In WordPress, add a new user with the Editor role, create an application password for that user, and connect with it instead.',
            self::TooWeak => 'That login cannot edit your pages, so we could not do anything with it. In WordPress, give the user the Editor role and try again.',
            self::Unreadable => 'Your website answered in a way we did not understand. Nothing was changed. Try again, and tell us if it keeps happening.',
            self::Busy => 'Your website asked us to slow down, so we left it alone. Nothing was changed and nothing is wrong with your site. Try again in a few minutes.',
            self::OurMinuteLimit => 'We are the ones slowing down, not your website. We limit how often we contact a site, and we have reached that limit for this minute. Nothing was changed and nothing is wrong with your site. Try again in a minute.',
            self::OurDayLimit => 'We are the ones slowing down, not your website. We limit how often we contact a site, and we have reached that limit for today. Nothing was changed and nothing is wrong with your site. Try again tomorrow.',
        };
    }
}
