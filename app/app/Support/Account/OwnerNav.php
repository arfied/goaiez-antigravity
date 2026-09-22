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
            OwnerNavItem::make('Google reviews', 'account.connections', OwnerNavItem::GROUP_MORE, section: 'Reviews & your website'),
            OwnerNavItem::make('Your account', 'account.settings', OwnerNavItem::GROUP_MORE, section: 'Your account'),
            OwnerNavItem::make('All screens', 'account.all-screens', OwnerNavItem::GROUP_MORE, section: 'Your account'),

            // `44` §2 puts the follow-ups list "under More" and badges that tab
            // with the due-today count. The badge is a KEY resolved at render
            // time by OwnerNavBadges — this list stays static and queries
            // nothing (1446), because it also renders on the on-hold page.
            OwnerNavItem::make('Follow-ups', 'account.follow-ups', OwnerNavItem::GROUP_MORE, section: 'Messages & follow-ups', badge: OwnerNavBadges::FOLLOW_UPS_DUE),
            OwnerNavItem::make('Reply drafts', 'account.replies', OwnerNavItem::GROUP_MORE, section: 'Messages & follow-ups', badge: OwnerNavBadges::REPLY_DRAFTS),

            // The recovery queue (2689). Under More beside the other two work
            // queues rather than in the primary row: it is the same shape as
            // Follow-ups and Reply drafts — a list somebody works down — and
            // `44` §2's reason for putting one there applies to all three.
            // Badged with the still-open count, which is what stops a
            // conversation sitting untouched because nobody opened the screen.
            OwnerNavItem::make('Win back customers', 'account.win-back', OwnerNavItem::GROUP_MORE, section: 'Customers', badge: OwnerNavBadges::TRIAGE_OPEN),

            // Under More because it is done once and then never again, which is
            // the distinction `44` §2 draws when it puts the follow-ups list
            // there rather than in the primary row.
            OwnerNavItem::make('Import your customers', 'account.customers.import', OwnerNavItem::GROUP_MORE, section: 'Customers'),

            // The widget install (2950). Under More on the same distinction —
            // paste one line, name your website, never think about it again.
            // ⚠️ The entry is not optional polish: `Architecture/OwnerNavTest`
            // fails the build on an owner screen with neither a nav entry nor a
            // written exclusion, and this screen is the *only* way anything in
            // `app/` reaches `setAllowedDomains()`. A screen nobody can find
            // would leave every widget serving nobody, which is exactly the
            // defect it was built to close.
            OwnerNavItem::make('Reviews on your website', 'account.website', OwnerNavItem::GROUP_MORE, section: 'Reviews & your website'),

            // The pixel install screen (decision 4979 item 2). Under More on
            // the same distinction as the entry above — paste one line, never
            // think about it again — and the label is the outcome rather than
            // the mechanism (`22`): an owner is not installing "a pixel", they
            // are letting us see how their website is doing.
            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH — `Architecture/OwnerNavTest`
            // fails the build on an owner screen with neither a nav entry nor a
            // written exclusion, and this is the only screen that will ever
            // mint a key for a tenant who predates the collector.
            OwnerNavItem::make('Let us see your website', 'account.pixel-install', OwnerNavItem::GROUP_MORE, section: 'Reviews & your website'),

            // Under More on the same distinction, and the label is the outcome
            // rather than the mechanism (`22`): an owner is not managing a
            // "knowledge base", they are deciding what we say on their behalf.
            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH — `Architecture/OwnerNavTest`
            // fails the build on an owner screen that has neither a nav entry
            // nor a written exclusion, which is 1442's rule that both halves
            // land together. A screen nobody can reach is decision 272's shape
            // wearing a route.
            OwnerNavItem::make('What we answer from', 'account.knowledge', OwnerNavItem::GROUP_MORE, section: 'Messages & follow-ups'),

            // T137 voice forwarding / `SL-9`. Under More on the same distinction as the two
            // above: forwarding is set up once and then revisited only when the
            // phone system changes. Outcome language (`22`) — an owner is not
            // configuring "call routing", they are deciding what happens to
            // their phone, and the number they need is on the same page.
            OwnerNavItem::make('Your phone', 'account.calls', OwnerNavItem::GROUP_MORE, section: 'Phone & texting'),

            // T176 §2.4/P6 — where the assistant sends people. Under More on the
            // same distinction as the three above: it is set when the business
            // changes scheduler or payment provider, not every day.
            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH. `Architecture/OwnerNavTest`
            // fails the build on an owner screen with neither a nav entry nor a
            // written exclusion, and this screen is the only writer of
            // `tenant_links` — so a screen nobody can find would leave R13's
            // gating permanently off for every tenant, with the agent falling
            // back to capture-and-handoff for ever and nothing saying why.
            OwnerNavItem::make('What your assistant can send', 'account.assistant-links', OwnerNavItem::GROUP_MORE, section: 'Messages & follow-ups'),

            // T176 §2.4/P5, beside the entry above and for the same reason: it is
            // set when the prices change, not every day.
            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH, AND IT CARRIES MORE THAN
            // FINDABILITY. `Architecture/OwnerNavTest` fails the build on an owner
            // screen with neither a nav entry nor a written exclusion — and this
            // screen is the **only** place a person can confirm a price read off
            // an uploaded sheet. A screen nobody can find would leave every
            // proposal sitting unconfirmed for ever, which reads exactly like a
            // business that never uploaded anything.
            OwnerNavItem::make('What your assistant can quote', 'account.assistant-answers', OwnerNavItem::GROUP_MORE, section: 'Messages & follow-ups'),
            OwnerNavItem::make('Things your assistant could not do', 'x-124.assistantunsupported-log', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Action preview', 'x-124.preview-card', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Lead routing rules', 'x-10.routing-rules', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Unassigned leads', 'x-10.unassigned-count', OwnerNavItem::GROUP_CATALOG),
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
            OwnerNavItem::make('Your plan', 'account.plan', OwnerNavItem::GROUP_MORE, section: 'Your account'),

            // Locations — what the plan covers, and the only way to add one
            // (2753). Under More beside the plan, on the same distinction: it is
            // looked at when something about the money changes, not every day.
            // ⚠️ THIS ENTRY IS THE ONLY WAY A SECOND LOCATION CAN EVER BE MADE.
            // `TenantProvisioner` makes the first and nothing else in `app/`
            // makes any, so a tenant who has paid for three would otherwise hold
            // one for ever — the extra-location SKU sold and never delivered,
            // which is a worse defect than the writerless column it started as.
            OwnerNavItem::make('Your locations', 'account.locations', OwnerNavItem::GROUP_MORE, section: 'Your account'),

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
            OwnerNavItem::make('Your credit', 'account.credit', OwnerNavItem::GROUP_MORE, section: 'Your account'),

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
            OwnerNavItem::make('Texting from your own number', 'account.texting', OwnerNavItem::GROUP_MORE, section: 'Phone & texting'),

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
            OwnerNavItem::make('What we changed on your website', 'account.site-changes', OwnerNavItem::GROUP_MORE, section: 'Reviews & your website'),

            // T137 `SL-7`. Under More rather than in the primary row: it is the
            // thing you reach for when something is wrong, and the primary row
            // is what the product does when everything is right. Outcome
            // language (`22`) — the label is what the person is about to do,
            // not the module it opens, so no "Support" and no "Tickets".
            OwnerNavItem::make('Ask us something', 'account.support', OwnerNavItem::GROUP_MORE, section: 'Your account'),

            // The list of forms people gave up on (X-110). Under More because it is
            // a thing checked periodically rather than an everyday surface.
            // ⚠️ THIS ENTRY IS THE WHOLE OF THE SCREEN'S REACHABILITY AND IT IS
            // NOT OPTIONAL POLISH. Architecture/OwnerNavTest fails the build on
            // an owner screen with neither a nav entry nor a written exclusion,
            // and this is the only way a person can see these abandoned forms.
            // A screen nobody can find is useless.
            OwnerNavItem::make('Forms people gave up on', 'x-110.abandoned-forms', OwnerNavItem::GROUP_CATALOG),

            // Verifying the tag installation (X-110). Under More on the same
            // distinction — it is used during initial setup or troubleshooting,
            // not every day.
            // ⚠️ THIS ENTRY IS NOT NAVIGATION POLISH. Architecture/OwnerNavTest
            // fails the build on an owner screen that lacks both a nav entry
            // and an exclusion. This is the only place a tenant can confirm their
            // tag is working. A screen nobody can reach would leave them guessing
            // whether we are actually collecting their data.
            OwnerNavItem::make('Is our tag working', 'x-110.install-verify', OwnerNavItem::GROUP_CATALOG),

            // What their pages earn (X-138). Ruled to be under More deliberately,
            // despite being potentially daily: the primary row is a design the
            // owner has already seen, and re-cutting it is a nav question.
            // ⚠️ THIS ENTRY IS THE ONLY DOOR TO THIS ATTRIBUTION DATA.
            // Architecture/OwnerNavTest refuses to let an owner screen exist
            // without a nav entry or a written exclusion, and it also refuses
            // hand-written links between screens. A screen without a nav entry
            // would be unreachable, leaving the revenue numbers hidden.
            OwnerNavItem::make('What your pages earn', 'x-138.attribution-row', OwnerNavItem::GROUP_CATALOG),

            // Where the business is listed (X-192). Placed under More because
            // directory registrations are generally set and forgotten.
            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH. Architecture/OwnerNavTest
            // fails the build on any owner screen with neither a nav entry nor
            // a written exclusion. This is the canonical owner door for directory
            // memberships, so a screen nobody can navigate to would leave the
            // owner unable to manage their listings.
            OwnerNavItem::make('Where you are listed', 'x-192.memberships-list', OwnerNavItem::GROUP_CATALOG),

            // Invoices the owner has sent (X-199). Under More because dealing
            // with past billing is typically periodic, not a daily task.
            // ⚠️ THIS ENTRY EXITS THE SCREEN FROM A WRONG MEASUREMENT.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET, so it owes a nav entry.
            // Leaving it out of the nav makes the invoices unreachable.
            OwnerNavItem::make('Invoices you sent', 'x-199.invoices', OwnerNavItem::GROUP_CATALOG),

            // Credit the owner has extended to customers (X-199). Under More on
            // the same distinction as the invoices screen: it is a financial
            // ledger checked when necessary.
            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET, so it owes a nav entry.
            // A screen nobody can find would leave every extended credit invisible
            // to the funder.
            OwnerNavItem::make("Credit you've extended", 'x-199.credits', OwnerNavItem::GROUP_CATALOG),

            // People who went quiet (X-110). Under More because re-engaging cold leads
            // is typically periodic, not a daily task.
            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET, so it owes a nav entry.
            // A screen nobody can find would leave these cooling leads invisible.
            OwnerNavItem::make('People who went quiet', 'x-110.cooling', OwnerNavItem::GROUP_CATALOG),

            // Live visitors on the site (X-110). Under More because monitoring
            // live traffic is typically done when checking campaigns rather than
            // every single day.
            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET, so it owes a nav entry.
            // A screen nobody can find would leave live visitor traffic hidden.
            OwnerNavItem::make('Who is on your site now', 'x-110.visitors-live', OwnerNavItem::GROUP_CATALOG),

            // Campaign ROI dashboard (X-138). Under More because campaign
            // reporting is typically checked periodically, not every day.
            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET, so it owes a nav entry.
            // A screen nobody can find would leave campaign revenue numbers hidden.
            OwnerNavItem::make('What your campaigns earned', 'x-138.roi-dashboard', OwnerNavItem::GROUP_CATALOG),

            // Ad platform connections (X-139). Under More because connection
            // management is set up infrequently, not a daily task.
            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET, so it owes a nav entry.
            // A screen nobody can find would leave ad platform connections inaccessible.
            OwnerNavItem::make('Your ad accounts', 'x-139.adaccount-connect-card', OwnerNavItem::GROUP_CATALOG),

            // Conversions uploaded to ad platforms (X-139). Under More because
            // tracking attribution syncs is typically periodic.
            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET, so it owes a nav entry.
            // A screen nobody can find would leave pushed conversion numbers hidden.
            OwnerNavItem::make('Sales sent back to your ads', 'x-139.conversions-pushed-tile', OwnerNavItem::GROUP_CATALOG),

            // Rejected conversion uploads (X-139). Under More because reviewing
            // integration failures is an administrative check, not a daily task.
            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET, so it owes a nav entry.
            // A screen nobody can find would leave upload rejection rates hidden.
            OwnerNavItem::make('Uploads your ads rejected', 'x-139.rejection-rate', OwnerNavItem::GROUP_CATALOG),

            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET once mount() resolves the
            // tenant, so it owes a nav entry.
            OwnerNavItem::make('What is booked', 'x-108.calendar', OwnerNavItem::GROUP_CATALOG),

            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET once mount() resolves the
            // tenant, so it owes a nav entry.
            OwnerNavItem::make('Who is waiting for a slot', 'x-108.waitlist', OwnerNavItem::GROUP_CATALOG),

            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET once mount() resolves the
            // tenant, so it owes a nav entry.
            OwnerNavItem::make('Estimates you have drafted', 'x-164.estimates-list', OwnerNavItem::GROUP_CATALOG),

            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET once mount() resolves the
            // tenant, so it owes a nav entry.
            OwnerNavItem::make('Areas you cover', 'x-10.territory-map', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Customer interests', 'x-131.interest-tags', OwnerNavItem::GROUP_CATALOG),

            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET once mount() resolves the
            // tenant, so it owes a nav entry.
            OwnerNavItem::make('Booked and collected by month', 'x-07.forecast-risk-tiles', OwnerNavItem::GROUP_CATALOG),

            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET once mount() resolves the
            // tenant, so it owes a nav entry.
            OwnerNavItem::make('Starter content', 'x-180.pack-browser', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Message sequences you run', 'x-185.digest-line', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Content packs', 'x-185.experiment-board', OwnerNavItem::GROUP_CATALOG),

            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET once mount() resolves the
            // tenant, so it owes a nav entry.
            OwnerNavItem::make('Posts planned for your week', 'x-184.content-week', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Content calendar', 'x-184.calendar', OwnerNavItem::GROUP_CATALOG),

            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET once mount() resolves the
            // tenant, so it owes a nav entry.
            OwnerNavItem::make('Offers running now', 'x-210.active-promotions', OwnerNavItem::GROUP_CATALOG),

            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET once mount() resolves the
            // tenant, so it owes a nav entry.
            OwnerNavItem::make('Offers your customers used', 'x-210.redemptions', OwnerNavItem::GROUP_CATALOG),

            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen saves a
            // real offer through PromotionCreateAction once mount() resolves
            // the tenant, so it owes a nav entry.
            OwnerNavItem::make('Create an offer', 'x-210.promotion-builder', OwnerNavItem::GROUP_CATALOG),

            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET once mount() resolves the
            // tenant, so it owes a nav entry.
            OwnerNavItem::make('Drafts written for you', 'x-183.draft-review', OwnerNavItem::GROUP_CATALOG),

            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET once mount() resolves the
            // tenant, so it owes a nav entry.
            OwnerNavItem::make('Why drafts were held back', 'x-183.gate-rejection-reasons', OwnerNavItem::GROUP_CATALOG),

            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET once mount() resolves the
            // tenant, so it owes a nav entry.
            OwnerNavItem::make('Automations you have set up', 'x-125.canvas', OwnerNavItem::GROUP_CATALOG),

            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET once mount() resolves the
            // tenant, so it owes a nav entry.
            OwnerNavItem::make('Automations that ran', 'x-125.runs', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Automation errors', 'x-125.flow-error-dashboard', OwnerNavItem::GROUP_CATALOG),

            // ⚠️ THIS ENTRY IS NOT OPTIONAL POLISH.
            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET once mount() resolves the
            // tenant, so it owes a nav entry.
            OwnerNavItem::make('Campaigns stopped or paused', 'x-186.stop-log', OwnerNavItem::GROUP_CATALOG),

            // Architecture/OwnerNavTest fails the build on an owner screen with
            // neither a nav entry nor a written exclusion. This screen runs a
            // real query and renders it on every GET once mount() resolves the
            // tenant, so it owes a nav entry.
            OwnerNavItem::make('Campaigns running now', 'x-186.live-run', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('People in your campaigns', 'x-186.audience-preview-count', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Your campaign sequences', 'x-186.sequence-builder', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Your prices', 'x-163.pricebook', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Prices to confirm', 'x-163.confirmation-screen', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Your members', 'x-165.members', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Your plans', 'x-165.plans', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Daily pricing digest', 'x-163.daily-pricing-digest', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Margin by job', 'x-166.margin-by-job', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Margin by service', 'x-166.by-service', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Margin by source', 'x-166.by-source', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Margin by technician', 'x-166.by-tech', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Churn risk list', 'x-08.risk-list', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Sorted risk rankings', 'x-08.sorted', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Reason per row', 'x-08.reason-per-row', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Person timeline', 'x-132.person-timeline', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Stock by van', 'x-167.stock-by-van', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Reorders', 'x-167.reorders', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Resolution rate & confidence', 'x-132.resolution-rate-confidence', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Timesheets', 'x-168.timesheets', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Connect a source', 'x-156.connect-source', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Prompt copy', 'x-207.promptcopy-editor', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Timesheet approvals', 'x-168.approvals', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Social queue', 'x-182.social-queue', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Your hours', 'x-168.own-hours', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Referral slots', 'x-190.slot-board', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Your rates', 'x-82.rate-registry', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Partner network', 'x-190.network-map', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Field assistant', 'x-175.stafffacing-assistant-panel', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Pool depth', 'x-190.pool-depth-per', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Route map', 'x-162.map', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Promotion targeting', 'x-210.targeting-preview', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Number pool', 'x-188.pool-inventory', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Discounts given', 'x-210.earnedvsgiven-panel', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Call attribution', 'x-137.attribution-row', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Connected accounts', 'x-182.connected-accounts', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Commissions', 'x-170.commissions', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Scorecards', 'x-170.scorecard', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Plugin installs', 'x-104.install-count', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Redirect queue', 'x-129.cutover-queue', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Schema status', 'x-176.seo-tab-website', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Chat leads', 'x-102.offline-form-inbox', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Retired devices', 'x-207.retirement-reasons', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Forms', 'x-155.forms', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Plugin sites', 'x-104.plugin-settings-page', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Migration status', 'x-129.migration-card', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Edge deployments', 'x-157.edge-status-per', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Chat thread', 'x-102.thread', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Reveal log', 'x-206.reveal-log', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Form submissions', 'x-155.submissions-thread', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Parked numbers', 'x-188.park-list', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Change history', 'x-121.entity-history-viewer', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Restore tests', 'x-203.dr-dashboard', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Rage clicks', 'x-102.rageclick-rate', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Connections', 'x-206.connections', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Spam rate', 'x-155.spam-rate', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Your number', 'x-188.your-number-card', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Restore test log', 'x-203.restorationtest-log', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Push health', 'x-207.perplatform-delivery-health', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Agent turns', 'c-agent.thread', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Number complaints', 'x-188.pernumber-complaint-board', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Runbooks', 'x-203.runbook-runner', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Google profile', 'x-177.gbp-card', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Team alerts', 'x-153.alert-roster-screen', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Reply codes', 'x-153.alert-reply-by', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Suspension risks', 'x-177.suspensionrisk-events-fleetwide', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Push prompts', 'x-207.one-confirmonce-toggle', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('QA queue', 'x-181.qa-queue-sladueat', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Resolved tickets', 'x-181.resolution', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('QA tickets', 'c-reviews.tickets', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Loss alerts', 'c-reviews.loss-alerts', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Claim expiry', 'x-153.claimexpiry-rate', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Agent refusals', 'c-agent.refusalcode-distribution-per', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('QA report', 'c-reviews.qa-report', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Agent teaching', 'c-agent.teaching-box', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Ticket', 'x-181.ticket', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Reviews', 'c-reviews.reviews-qa-requests', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Agent grounding', 'c-agent.groundcheck-screen', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Affiliate earnings', 'x-205.earnings', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Reveal credential', 'x-206.reveal', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('DNI pool usage', 'x-137.dni-pool-utilisation', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Credits', 'c-billing.credits', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Chat widget', 'x-102.customerfacing-widget', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Branded media', 'x-189.preview-per-destination', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Affiliates', 'x-205.portal', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('AI calls', 'c-ai.model-board', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Failed deliveries', 'x-123.dlq-request-inspector', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Monthly billing', 'c-billing.mrr', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Saved views', 'x-194.saved-views-list', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Affiliate payouts', 'x-205.payout-run', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Email domain', 'c-mail.dns-card', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Domain warm-up', 'c-mail.warmup-calendars-per', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Earned links', 'x-191.links-earned', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Outreach ratio', 'x-191.pitchacquire-ratio', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Dispatch board', 'x-162.dispatch-board', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Revenue recovery', 'c-billing.revenue-recovery', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Dunning board', 'c-billing.dunning-board', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('View', 'x-194.any-view-it', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Calls', 'x-66.calls', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Geo-grid', 'x-16.geogrid-map', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Service area', 'x-16.servicearea-polygon', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Sends by class', 'x-193.sendsbyclass', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Do-not-text list', 'c-sms.donottext-list', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Text thread', 'c-sms.thread', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Overdue invoices', 'x-211.ageing-by-reason', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Your cart', 'x-117.cart-block', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Checkout', 'x-117.checkout-block', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Payment plans', 'x-211.paymentplan-builder', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Harvest coverage', 'x-16.harvest-coverage-by', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Number health', 'c-sms.pernumber-complaint-monitoring', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Message segments', 'c-sms.composer-segment-warning', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Collections package', 'x-211.collections-package-preview', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Invoice thread', 'x-211.invoice-thread-beside', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Disputes', 'x-201.dispute-card', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Dispute queue', 'x-201.dispute-queue', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Browser extension', 'x-196.extension-popup', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Proposed pages', 'x-140.proposed-pages', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Payment methods', 'x-120.card-screen', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Site editor', 'x-178.site-editor-assistant', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Connect your AI', 'x-142.connect-your-ai', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Webhooks', 'x-142.webhooks', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Sync conflicts', 'x-173.conflicts-list', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Sync error rate', 'x-173.sync-error-rate', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Customers', 'x-01.customers-list', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Inbox', 'x-01.thread', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('First wins', 'x-118.today', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Time to first minute', 'x-118.ttfm-distribution', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Dry-run preview', 'x-212.dryrun-preview', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Import rejections', 'x-212.postimport-audit', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Approvals', 'x-202.queue', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Approval history', 'x-202.audit-export', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Fixer inbox', 'x-209.private-inbox', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Autopilot ladder', 'x-209.ladders-own-state', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Person', 'x-01.person', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Activity', 'x-01.history', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Your documents', 'x-160.upload-drop', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Documents to review', 'x-160.review-screen', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Extraction error rate', 'x-160.extraction-error-rate', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Document vault', 'x-113.document-vault', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Staff', 'x-113.staff', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Roles', 'x-113.roles', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Permissions', 'x-113.permission-matrix', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Action log', 'x-122.action-log', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Capability refusals', 'x-126.refusal-analytics', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Reconciliation discrepancies', 'x-198.reconciliation-discrepancies', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Call latency', 'x-66.latency-p50p95-per', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Call coaching', 'x-66.livecoaching-whisper-panel', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('What we learned', 'x-119.reviewwhatifound-screen', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Fact freshness', 'x-119.fact-freshness-per', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Demand in your area', 'x-130.public-index-pages', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Demand by trade', 'x-130.coverage-by-trade', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('WhatsApp templates', 'c-whatsapp.template-status-card', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Template approval queue', 'c-whatsapp.template-approval-queue', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Data coming in', 'x-156.ingest-volume-by', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Rows we could not take', 'x-156.rejectedrows-list', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Onboarding checks', 'x-118.groundcheck', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Field mapping', 'x-212.unmatchedfield-map', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Consent refusals', 'x-204.refusals-by-reason', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Compliance registers', 'x-204.register-slot-states', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Deal tracker', 'x-218.deal-tracker', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Discovery board', 'x-218.discovery-board', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Deliverable proof', 'x-218.deliverable-proof', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Gateway connections', 'x-198.connect-card', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Same account', 'x-198.same-account', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Agency Console', 'x-112.agency-console', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Agency staff', 'x-112.staff', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Migration commit', 'x-212.commit', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Reconciliation report', 'x-212.reconciliation-report', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Standard Flow', 'x-118.same-flow', OwnerNavItem::GROUP_CATALOG),
            OwnerNavItem::make('Direct Test Call', 'x-118.test-call', OwnerNavItem::GROUP_CATALOG),
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

    /**
     * @return array<int, OwnerNavItem>
     */
    public static function catalog(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (OwnerNavItem $item): bool => $item->group === OwnerNavItem::GROUP_CATALOG,
        ));
    }

    public static function moreIsCurrent(): bool
    {
        foreach (array_merge(self::more(), self::catalog()) as $item) {
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
        foreach (array_merge(self::more(), self::catalog()) as $item) {
            if ($item->current()) {
                return $item->label;
            }
        }

        return null;
    }
}
