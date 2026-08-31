<?php

declare(strict_types=1);

namespace App\Support\Account;

/**
 * The owner's navigation — one declaration for every screen an owner reaches.
 *
 * ⚠️ **BUILT BECAUSE THERE WAS NO SHELL AND SEVEN SCREENS WERE LINKING TO EACH
 * OTHER BY HAND.** `/home` linked to customers, connections and settings;
 * settings linked back to home, import and messages; messages linked only to
 * settings; import linked only to settings; the customer list linked to import
 * and nowhere else. The graph was partial and asymmetric, and every new screen
 * added another convention rather than an entry — `BUILD-PLAN` §2.9.2(4).
 *
 * ⚠️ **THE NAV IS RENDERED BY THE LAYOUT, NOT PASTED INTO EACH SCREEN.** The
 * staff console does the other thing: `<x-admin.nav>` appears at the top of nine
 * Blade views, so a tenth screen that forgets the line has no navigation and
 * nothing says so. Here the only way to render an owner screen is through
 * `components/account/layout`, so a screen cannot be built without one.
 *
 * ⚠️ **NOTHING IS FILTERED AND NOTHING IS QUERIED.** `AdminNav::for()` takes a
 * user and asks the Gate; this list is the same for everybody, because every
 * owner route is on `auth` alone. It also touches no database: the shell renders
 * the suspended-account status page, which is what a tenant in trouble is shown,
 * and a nav that needed a tenant would fail on the one screen that exists for
 * the account whose tenancy is the problem.
 *
 * ✅ **THE BADGE FIELD EXISTS NOW, AND IT IS A KEY RATHER THAN A NUMBER.** This
 * paragraph used to say no badge field ships, because `crm_tasks` had no writer
 * and the field would have been decision 272's shape (1442). The follow-ups
 * slice added both halves together, the way 1442 said it must: `OwnerNavItem`
 * carries a badge *key*, and `OwnerNavBadges` resolves the count where the nav
 * is rendered — behind a `Tenancy::id()` guard — so this list stays static and
 * the on-hold page still renders with no tenant at all.
 */
final class OwnerNav
{
    /**
     * Every declared item, in render order.
     *
     * `29` §6.1's rule for the marketing nav applies here too (decision 261):
     * an item is added when its screen exists, never before. A nav naming a
     * route that does not resolve throws at render, and only a real `GET` sees
     * it — which is why `OwnerNavTest` drives one per screen.
     *
     * @return array<int, OwnerNavItem>
     */
    public static function all(): array
    {
        return [
            OwnerNavItem::make('Your results', 'account.home'),

            // `28` §85 puts Activity second in the Normal navigation, right
            // after Home, and this entry is that one. PRIMARY RATHER THAN UNDER
            // MORE, AND THE ARGUMENT IS 5849's OWN PREMISE: it says a failed
            // undo *"announces itself in the feed"* so *"an owner who does not
            // read the activity feed"* sees nothing — and burying the feed under
            // a disclosure would make that sentence true again by a different
            // route. Every other More entry is a thing you set once; this is the
            // thing you open to find out what happened.
            //
            // ⚠️ THIS ENTRY IS THE WHOLE OF THE SCREEN'S REACHABILITY.
            // `Architecture/OwnerNavTest` fails the build on an owner screen
            // with neither a nav entry nor a written exclusion, and it also
            // refuses a hand-written link from one owner screen to another — so
            // there is no second route in. `activity_feed` was write-only for
            // the entire life of this application (6073); a screen nobody could
            // find would leave it that way with a route to prove otherwise.
            //
            // ⚠️ NO BADGE, DELIBERATELY, AND IT IS NOT AN OMISSION (6285).
            // `OwnerNavBadges` resolves counts for the three work queues, and
            // the count that belongs here would be "things needing you" — but
            // the table is append-only and nothing anywhere marks an item
            // handled, so that number only ever climbs. A badge reading 47 in
            // month two, that no action can reduce, is a permanent alarm and
            // trains an owner to ignore the nav. The pill on the row says which
            // ones; the door does not pretend to keep score.
            //
            // Outcome language (`22`): `28` calls the module "Activity", which
            // is our word for our table. An owner is asking what this thing has
            // been doing for them.
            OwnerNavItem::make('What we have been doing', 'account.activity'),

            // `28` §5.3 Normal surface — GSC movement, unnamed competitor
            // sentence, honest unavailable copy for Maps / catchment.
            OwnerNavItem::make('How people find you', 'account.visibility'),

            // The profile has no entry of its own: it is one contact rather
            // than a destination, so the list it came from stays marked.
            OwnerNavItem::make('Your customers', 'account.customers', alsoCurrentFor: ['account.customers.show']),

            // R21 calls the Inbox the tenant's daily surface, so it sits in the
            // primary row rather than under More — the distinction `44` §2 draws
            // is between what somebody opens every day and what they set once.
            // ⚠️ IT IS DELIBERATELY BESIDE "Messages you sent" RATHER THAN
            // INSTEAD OF IT. The two are the two directions of one subject and
            // neither subsumes the other: this is the conversation, that is the
            // send log. The labels are what tell them apart, so they name the
            // direction rather than the mechanism (`22`).
            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH — `Architecture/OwnerNavTest`
            // fails the build on an owner screen with neither a nav entry nor a
            // written exclusion, and this screen is the only place a person can
            // answer a customer or hand a conversation back to the assistant.
            OwnerNavItem::make('Your inbox', 'account.inbox'),

            OwnerNavItem::make('Messages you sent', 'account.messages'),
            OwnerNavItem::make('Google reviews', 'account.connections'),
            OwnerNavItem::make('Your account', 'account.settings'),

            // `44` §2 puts the follow-ups list "under More" and badges that tab
            // with the due-today count. The badge is a KEY resolved at render
            // time by OwnerNavBadges — this list stays static and queries
            // nothing (1446), because it also renders on the on-hold page.
            OwnerNavItem::make('Follow-ups', 'account.follow-ups', OwnerNavItem::GROUP_MORE, badge: OwnerNavBadges::FOLLOW_UPS_DUE),
            OwnerNavItem::make('Reply drafts', 'account.replies', OwnerNavItem::GROUP_MORE, badge: OwnerNavBadges::REPLY_DRAFTS),

            // The recovery queue (2689). Under More beside the other two work
            // queues rather than in the primary row: it is the same shape as
            // Follow-ups and Reply drafts — a list somebody works down — and
            // `44` §2's reason for putting one there applies to all three.
            // Badged with the still-open count, which is what stops a
            // conversation sitting untouched because nobody opened the screen.
            OwnerNavItem::make('Win back customers', 'account.win-back', OwnerNavItem::GROUP_MORE, badge: OwnerNavBadges::TRIAGE_OPEN),

            // Under More because it is done once and then never again, which is
            // the distinction `44` §2 draws when it puts the follow-ups list
            // there rather than in the primary row.
            OwnerNavItem::make('Import your customers', 'account.customers.import', OwnerNavItem::GROUP_MORE),

            // The widget install (2950). Under More on the same distinction —
            // paste one line, name your website, never think about it again.
            // ⚠️ The entry is not optional polish: `Architecture/OwnerNavTest`
            // fails the build on an owner screen with neither a nav entry nor a
            // written exclusion, and this screen is the *only* way anything in
            // `app/` reaches `setAllowedDomains()`. A screen nobody can find
            // would leave every widget serving nobody, which is exactly the
            // defect it was built to close.
            OwnerNavItem::make('Reviews on your website', 'account.website', OwnerNavItem::GROUP_MORE),

            // The pixel install screen (decision 4979 item 2). Under More on
            // the same distinction as the entry above — paste one line, never
            // think about it again — and the label is the outcome rather than
            // the mechanism (`22`): an owner is not installing "a pixel", they
            // are letting us see how their website is doing.
            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH — `Architecture/OwnerNavTest`
            // fails the build on an owner screen with neither a nav entry nor a
            // written exclusion, and this is the only screen that will ever
            // mint a key for a tenant who predates the collector.
            OwnerNavItem::make('Let us see your website', 'account.pixel-install', OwnerNavItem::GROUP_MORE),

            // Under More on the same distinction, and the label is the outcome
            // rather than the mechanism (`22`): an owner is not managing a
            // "knowledge base", they are deciding what we say on their behalf.
            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH — `Architecture/OwnerNavTest`
            // fails the build on an owner screen that has neither a nav entry
            // nor a written exclusion, which is 1442's rule that both halves
            // land together. A screen nobody can reach is decision 272's shape
            // wearing a route.
            OwnerNavItem::make('What we answer from', 'account.knowledge', OwnerNavItem::GROUP_MORE),

            // T137 `R7`/`SL-9`. Under More on the same distinction as the two
            // above: forwarding is set up once and then revisited only when the
            // phone system changes. Outcome language (`22`) — an owner is not
            // configuring "call routing", they are deciding what happens to
            // their phone, and the number they need is on the same page.
            OwnerNavItem::make('Your phone', 'account.calls', OwnerNavItem::GROUP_MORE),

            // T176 §2.4/P6 — where the assistant sends people. Under More on the
            // same distinction as the three above: it is set when the business
            // changes scheduler or payment provider, not every day.
            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH. `Architecture/OwnerNavTest`
            // fails the build on an owner screen with neither a nav entry nor a
            // written exclusion, and this screen is the only writer of
            // `tenant_links` — so a screen nobody can find would leave R13's
            // gating permanently off for every tenant, with the agent falling
            // back to capture-and-handoff for ever and nothing saying why.
            OwnerNavItem::make('What your assistant can send', 'account.assistant-links', OwnerNavItem::GROUP_MORE),

            // T176 §2.4/P5, beside the entry above and for the same reason: it is
            // set when the prices change, not every day.
            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH, AND IT CARRIES MORE THAN
            // FINDABILITY. `Architecture/OwnerNavTest` fails the build on an owner
            // screen with neither a nav entry nor a written exclusion — and this
            // screen is the **only** place a person can confirm a price read off
            // an uploaded sheet. A screen nobody can find would leave every
            // proposal sitting unconfirmed for ever, which reads exactly like a
            // business that never uploaded anything.
            OwnerNavItem::make('What your assistant can quote', 'account.assistant-answers', OwnerNavItem::GROUP_MORE),

            // The plan, and the cancellation (2980–2999). Under More on the same
            // distinction as the three above: it is looked at when something
            // about the money changes, not every day.
            // ⚠️ THIS ENTRY IS THE CANCELLATION MECHANISM'S FINDABILITY RATHER
            // THAN NAVIGATION POLISH. `/billing` has never been in this list and
            // nothing in `app/` links to it, so before this row the only way to
            // reach a billing screen was the post-registration redirect — "you
            // can cancel any time" with nowhere to press. California's Automatic
            // Renewal Law asks that cancelling be at least as easy as signing
            // up, and a screen nobody can navigate to is not.
            OwnerNavItem::make('Your plan', 'account.plan', OwnerNavItem::GROUP_MORE),

            // Locations — what the plan covers, and the only way to add one
            // (2753). Under More beside the plan, on the same distinction: it is
            // looked at when something about the money changes, not every day.
            // ⚠️ THIS ENTRY IS THE ONLY WAY A SECOND LOCATION CAN EVER BE MADE.
            // `TenantProvisioner` makes the first and nothing else in `app/`
            // makes any, so a tenant who has paid for three would otherwise hold
            // one for ever — the extra-location SKU sold and never delivered,
            // which is a worse defect than the writerless column it started as.
            OwnerNavItem::make('Your locations', 'account.locations', OwnerNavItem::GROUP_MORE),

            // Credit — balances and top-ups (3482). Under More beside the plan,
            // on the same distinction: it is looked at when something about the
            // money changes.
            // ⚠️ THIS ENTRY IS THE FUNDER'S FINDABILITY RATHER THAN NAVIGATION
            // POLISH, exactly as the row above it is the cancellation's.
            // `Architecture/OwnerNavTest` fails the build on an owner screen with
            // neither a nav entry nor a written exclusion, and this screen is the
            // *only* thing in `app/` that reaches `CreditTopUps` — so a screen
            // nobody can find would leave every tenant's purchased balance at
            // zero for ever, which is the state 3482 records.
            OwnerNavItem::make('Your credit', 'account.credit', OwnerNavItem::GROUP_MORE),

            // Where the tenant's own texting registration has got to (5329,
            // 5420–5439). Under More on the same distinction as the entries
            // above: it is a thing you check while you are waiting on it and
            // then never again. Outcome language (`22`) — a person is not
            // visiting a "10DLC brand registration", they are finding out
            // whether they can text their own customers yet.
            // ⚠️ THIS ENTRY IS THE WHOLE OF THE SCREEN'S REACHABILITY AND IT IS
            // NOT NAVIGATION POLISH. `Architecture/OwnerNavTest` fails the build
            // on an owner screen with neither a nav entry nor a written
            // exclusion — and it also refuses a hand-written link from one owner
            // screen to another, so there is no second route in.
            // ⛔ ITS LAST SENTENCE READ "a tenant who could not find this page
            // would meet `BroadcastPreconditions`' refusal with no clock
            // against it" AND NO TENANT MEETS THAT REFUSAL — CORRECTED
            // 2026-08-28 (11520-11524). Those three strings are operator
            // sentences that reach a log and may never become tenant-facing
            // (5422), and `Campaigns::enrol()` — the sole writer of
            // `campaign_recipients` — has no caller in `app/`, so nothing
            // dispatches the pass that would ask. THE ENTRY STAYS AND THE
            // REACHABILITY ARGUMENT IS UNTOUCHED: the wait this page reports is
            // real, and an owner screen still owes a nav entry or a written
            // exclusion whatever is downstream of it.
            OwnerNavItem::make('Texting from your own number', 'account.texting', OwnerNavItem::GROUP_MORE),

            // Row 10 U1b — what we changed on their website, and the undo.
            // Under More on the same distinction as the entries above: it is a
            // thing you check when you want to know, not every day. Outcome
            // language (`22`) — an owner is not reading a "site change log",
            // they are finding out what happened to their website.
            // ⚠️ THIS ENTRY IS THE ONLY DOOR TO AN OWNER UNDO THAT EXISTS.
            // `Architecture/OwnerNavTest` fails the build on an owner screen
            // with neither a nav entry nor a written exclusion, and it also
            // refuses a hand-written link from one owner screen to another — so
            // there is no second route in. Before this screen, `29` §2 rule 32's
            // *"every site change is reversible"* was reversible **by us**:
            // `SiteChanges::revert()` had one caller and it was the automatic
            // rollback. A screen nobody can find would leave it that way.
            OwnerNavItem::make('What we changed on your website', 'account.site-changes', OwnerNavItem::GROUP_MORE),

            // T137 `SL-7`. Under More rather than in the primary row: it is the
            // thing you reach for when something is wrong, and the primary row
            // is what the product does when everything is right. Outcome
            // language (`22`) — the label is what the person is about to do,
            // not the module it opens, so no "Support" and no "Tickets".
            OwnerNavItem::make('Ask us something', 'account.support', OwnerNavItem::GROUP_MORE),
        ];
    }

    /**
     * @return array<int, OwnerNavItem>
     */
    public static function primary(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (OwnerNavItem $item): bool => $item->group === OwnerNavItem::GROUP_PRIMARY,
        ));
    }

    /**
     * @return array<int, OwnerNavItem>
     */
    public static function more(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (OwnerNavItem $item): bool => $item->group === OwnerNavItem::GROUP_MORE,
        ));
    }

    /**
     * Whether the screen being rendered lives under More.
     *
     * ⚠️ **NO LONGER USED TO OPEN THE DISCLOSURE — CORRECTED 2026-08-27
     * (decisions 10420–10422, wave 37 lane A).** This docblock used to read
     * *"the disclosure opens itself when it does, so an owner who arrives at a
     * More screen … is not shown a closed menu with no indication of where they
     * are."* That intent survives; the mechanism it justified did not. Two
     * lanes of wave 36 independently measured the auto-opened panel physically
     * covering the very button an owner had come to press — `<details open>`'s
     * `absolute z-20` panel has no counter-elevation on `<main>`, and the
     * overlap is permanent, not a transient render (decisions 10331–10332,
     * 10350). This flag still decides whether the summary is shown with the
     * "current" visual weight; `currentMoreLabel()` below is what now answers
     * "where am I" — visibly, with the disclosure CLOSED.
     */
    public static function moreIsCurrent(): bool
    {
        foreach (self::more() as $item) {
            if ($item->current()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The label of the More item the request is currently on, or null.
     *
     * ⚠️ **THIS IS THE REPLACEMENT FOR THE OLD AUTO-OPEN, NOT AN ADDITION
     * BESIDE IT** (decisions 10420–10422). The disclosure's own `<ul>` renders
     * its contents whether the `<details>` is open or shut, so the current
     * item's `aria-current="page"` anchor already exists in the DOM either
     * way — but a closed native `<details>` hides its non-summary children
     * from the accessibility tree as well as visually (the UA stylesheet
     * applies `display: none`), so that `aria-current` is invisible to
     * everybody once the disclosure stays closed by default. The summary
     * itself is never hidden, so this is what has to carry the answer:
     * `nav.blade.php` renders "More: {label}" instead of a bare "More" when
     * this returns non-null, which is exposed the same way to a sighted user
     * reading it and to a screen reader announcing the toggle's own text —
     * with no panel opened, nothing covered, and no second aria-current to
     * keep in sync with the one the anchor already carries.
     */
    public static function currentMoreLabel(): ?string
    {
        foreach (self::more() as $item) {
            if ($item->current()) {
                return $item->label;
            }
        }

        return null;
    }
}
