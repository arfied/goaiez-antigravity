<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The internal navigation, filtered to what the viewer may actually reach.
 *
 * One declaration for the whole console rather than a partial per layout: a nav
 * assembled in a Blade file is one somebody copies, and the copy is where an
 * item loses its ability check.
 *
 * ⚠️ **It is `28` §9.2's console nav now, not only the platform admin's**, and
 * the class name lags the job. Each item names its own ability, so the two
 * populations separate themselves: `Platform` items ask `AdminAccess::GATE`
 * (`super_admin` alone) and `Support` items ask `SupportAccess::GATE` — which
 * means a `support_lead` sees the support section and none of the platform one,
 * without a role appearing anywhere in this file. That is the whole reason
 * `NavItem` asks the Gate rather than comparing roles.
 *
 * `28` §9.2 lists nine sections. Two are declared, because those are the ones
 * with screens behind them — decision 261's rule, that the nav links only to
 * what exists. Items are added here as screens land, and an item whose route
 * does not exist is a broken link rather than a security problem, so a test
 * asserts every declared route resolves.
 *
 * ⛔ **AND UNTIL 2026-08-24 THAT WAS THE ONLY DIRECTION ANYTHING CHECKED**
 * (9289). Every lint over this class ran nav → route, so **a console route
 * absent from this array contributed nothing to any offender list** — not a
 * quiet failure, no failure at all — and two screens sat with no inbound link
 * of any kind for months. `tests/Feature/Architecture/AdminNavTest.php` now
 * runs the other way, from `Route::getRoutes()`, the way `OwnerNavTest` always
 * has for the owner shell.
 *
 * ⚠️ **A ROUTE THAT TAKES A PARAMETER CANNOT BE LISTED HERE AT ALL**, which is
 * the constraint that shapes the exclusions in that file: {@see NavItem} has no
 * slot for one and `components/admin/nav.blade.php` calls `route($item->route)`
 * with nothing else, so such an item throws at render for every viewer. Those
 * screens are linked from an index that knows the parameter instead. **Do not
 * "fix" the coverage lint by adding one here.**
 */
final class AdminNav
{
    /**
     * @return array<int, NavItem>
     */
    public static function all(): array
    {
        return [
            NavItem::make('Automation runs', 'admin.automation-runs', AdminAccess::GATE),
            // Reachable from the nav because it takes no route parameter, unlike
            // LocationSettings, ReviewQueue and LegalDocuments — it finds its
            // business itself.
            //
            // ⚠️ **THAT SENTENCE WAS THE WHOLE ARGUMENT FOR THREE ABSENCES AND
            // IT IS AN ARGUMENT FOR NOT LISTING THEM, NEVER FOR LEAVING THEM
            // UNREACHABLE** (9287). Two of the three had no way in from
            // anywhere; the third had one and was the pattern nobody applied.
            // All three are now linked from an index, and
            // `AdminNavTest`'s `adminNavExcludedRoutes()` is where that is
            // recorded and checked — a comment on a neighbouring item is not a
            // list, and nothing failed while it was wrong.
            NavItem::make('Health information', 'admin.phi-tenants', AdminAccess::GATE),
            // Extra locations (T176 P25). Listed for 'Health information's
            // reason — it takes no route parameter and finds its own business —
            // and listed at all because this is the *only* path in the whole
            // application that can set up a second location: `TenantProvisioner`
            // makes the first and nothing else makes any. A customer who has
            // paid for three and can be given one is 2753's SKU sold and not
            // delivered. Outcome language: what the operator does, not the SKU.
            NavItem::make('Extra locations', 'admin.tenant-locations', AdminAccess::GATE),
            // T137 `SL-8`'s kill switches (2630). Listed for the same reason
            // 'Health information' is — it takes no route parameter and finds
            // its own tenant — and listed at all because a kill switch nobody
            // can find is most of the way to one that does not exist. Outcome
            // language: what the person controls, not the mechanism — and both
            // directions, because the screen that only stops is the one an
            // operator cannot use to undo what it did.
            NavItem::make('Stop and start sending', 'admin.sending-controls', AdminAccess::GATE),

            // Email sending allowance meter (4442, built at 4600). Listed rather than
            // reached from the screen above, because it answers a different
            // question about a different channel — that one holds the SMS kill
            // switches and this is the email account's allowance — and listed
            // at all because since 4603 a deployment can be sending nothing
            // whatever with the only evidence in `failed_jobs`. Outcome
            // language: what is happening to the mail, not what measures it.
            NavItem::make('Email sending', 'admin.mail-sending', AdminAccess::GATE),

            // The Google grants a deleted tenant left behind — decision
            // 4888(a). Listed for 'Health information's reason: it takes no
            // route parameter and finds its own subject, and listed at all
            // because an obligation nobody can see is the same gap
            // `sending_health_windows` had before it had a reader. Outcome
            // language: what a deleted account left behind, not the table it
            // lives in.
            NavItem::make('Google grants left behind', 'admin.gbp-grant-revocations', AdminAccess::GATE),

            // Every bell this platform has rung at itself. Listed for 'Health
            // information's reason — it takes no route parameter and finds its
            // own subject — and listed at all because `operator_alerts` had ten
            // writers and no reader: the email and the text arrive on a phone
            // and then there was nowhere to go and look at what they said.
            // Outcome language: what has happened to us, not the table it is
            // kept in.
            NavItem::make('What has alerted us', 'admin.operator-alerts', AdminAccess::GATE),

            NavItem::make('Industry starting points', 'admin.industry-starting-points', AdminAccess::GATE),
            NavItem::make('Settings', 'admin.platform-settings', AdminAccess::GATE),
            NavItem::make('Credentials', 'admin.credentials', AdminAccess::GATE),

            // The legal library. Reachable now for the same reason 'Health
            // information' is: it takes no route parameter and finds its own
            // contents. `Admin\LegalDocuments` still cannot be listed here and
            // is one click away from this index.
            NavItem::make('Legal documents', 'admin.legal-index', AdminAccess::GATE),

            // The read path for `terms_acceptances` (T176 P22, 3995). Listed
            // rather than reached from the legal library, because the question
            // it answers is about a *tenant* and the library is about our own
            // documents — and listed at all because evidence nobody can find is
            // most of the way to evidence that does not exist. It takes no route
            // parameter and finds its own tenant, like 'Health information'.
            NavItem::make('Signup agreements', 'admin.terms-acceptances', AdminAccess::GATE),

            // The read path for `owner_notification_consents` (wave 39 lane
            // A, 10660) — `terms_acceptances`' precedent, one table over,
            // listed for the same reason: evidence nobody can find is most of
            // the way to evidence that does not exist. It takes no route
            // parameter and finds its own tenant, like 'Signup agreements'.
            NavItem::make('Owner-channel consent', 'admin.owner-notify-consents', AdminAccess::GATE),

            // The index by number (wave 40 lane C, 10880). Listed immediately
            // after 'Owner-channel consent' because it is how that screen is
            // reached when all you have is the number — which is the only thing
            // a carrier ever gives you. Outcome language: what we know, not
            // which tables it came out of.
            NavItem::make('What we know about a number', 'admin.number-lookup', AdminAccess::GATE),

            // What we texted that account holder, and what they said back (wave
            // 41 lane E, 11110). Listed after the two above because the three
            // are one question in sequence — who is this number, may we text
            // them, and what happened when we did — and listed at all for the
            // reason 'Owner-channel consent' is: `owner_notifications.kind`,
            // `.occasion`, `.provider_message_id` and
            // `owner_replies.in_reply_to_notification_id` had writers and no
            // reader anywhere in `app/`. Outcome language: what was said, not
            // which tables it came out of.
            NavItem::make('Texts with an account holder', 'admin.owner-channel-texts', AdminAccess::GATE),

            // `28` §9.2's audit explorer, which that section places under
            // Platform and §14.1 names again — so it takes `AdminAccess::GATE`
            // and a `support_lead` cannot open it. Two items because it answers
            // two questions and only one of them has a tenant; see
            // `App\Livewire\Admin\StaffActivity`.
            NavItem::make('Audit — an account', 'admin.audit-account', AdminAccess::GATE),
            NavItem::make('Audit — our staff', 'admin.audit-staff', AdminAccess::GATE),

            // `28` §9.2's "internal users & roles", also under Platform. The
            // one screen in the console that decides who reaches the rest of
            // it, which is why it takes the narrowest gate available (623).
            NavItem::make('People who work here', 'admin.internal-users', AdminAccess::GATE),

            // `28` §9.2's Accounts section. Grouped separately because the
            // people who reach it are a different population from the people
            // who reach the two above, not because it is a different kind of
            // screen — a `cs_readonly` sees neither group and a `support_lead`
            // sees only this one.
            NavItem::make('Open an account', 'support.accounts', SupportAccess::GATE, 'Support'),

            // `28` §9.5's data-request queue — GDPR/CCPA asks with due dates.
            NavItem::make('Data requests', 'support.data-requests', SupportAccess::GATE, 'Support'),

            // T137 `SL-7`'s queue. Listed rather than reached from Account 360,
            // because the question it answers is "who is waiting on us" and
            // that one cannot be asked one account at a time — which is the
            // whole reason `support_queue_entries` exists.
            NavItem::make('Support requests', 'support.tickets', SupportAccess::GATE, 'Support'),
        ];
    }

    /**
     * Visible items, grouped for rendering.
     *
     * @return Collection<string, Collection<int, NavItem>>
     */
    public static function for(?User $user): Collection
    {
        return collect(self::all())
            ->filter(fn (NavItem $item): bool => $item->visibleTo($user))
            ->groupBy(fn (NavItem $item): string => $item->group);
    }
}
