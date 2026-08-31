<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Account\Settings;
use App\Models\AuditLogEntry;
use App\Models\Business;
use App\Models\OwnerNotificationConsent;
use App\Services\AuditService;
use App\Services\Consent\OwnerConsentService;
use App\Support\Admin\AdminAccess;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * What one business's own account holder agreed to, and the proof of it
 * (wave 39 lane A, decision 10660) — `App\Livewire\Admin\TermsAcceptances`'
 * shape, one table over.
 *
 * ⚠️ **THIS IS THE READER THE RECORD WAS WRITTEN FOR.** Wave 38 lane A built
 * `owner_notification_consents` with a writer and no reader at all — the
 * near-mirror of decision 272's shape, and the exact one `TermsAcceptances`'
 * own docblock names as the reason it exists.
 *
 * ⛔ **AND THIS DOCBLOCK CALLED IT "THE ANSWER TO A CARRIER'S *THIS NUMBER
 * NEVER AGREED TO BE TEXTED*" UNTIL WAVE 40 LANE C, WHICH IT COULD NOT BE**
 * (10882). `lookUp()` resolves its input with `(int) trim($this->lookup)`, so
 * typing the one thing a carrier actually gives you — the phone number —
 * returned *"Enter a business number."* **This is the record for a business
 * you can already name.** The index by number is
 * {@see NumberLookup}, which links here per business it resolves; the two
 * screens are one question each and this one is unchanged.
 *
 * ⛔ **IT SHOWS AND IT DOES NOTHING ELSE.** No action beyond the lookup: no
 * button to correct a number, no way to grant or withdraw consent from here.
 * An owner corrects their own number from `/account/settings`
 * ({@see Settings::editOwnerMobile()}); a staff member
 * who believes a row is wrong has one honest remedy, which is to say so.
 *
 * ⚠️ **IT LOOKS A BUSINESS UP BY NUMBER, LIKE `TermsAcceptances` AND
 * `PhiTenants`, AND FOR THE SAME REASON.** `businesses` carries two RLS
 * policies and neither admits platform staff, so the runtime role cannot
 * enumerate businesses at all — a list would need a third policy and a
 * session flag saying "I am staff", which is a platform-wide authorization
 * surface this screen has no business inventing.
 *
 * ⚠️ **A RESOLVED LOOKUP IS AUDITED IN THE LOOKED-UP TENANT'S OWN LOG**, the
 * same entry and the same argument as `TermsAcceptances::lookUp()` and
 * `PhiTenants::lookUp()`. What this screen renders is the account holder's
 * own consent evidence — the page they were on, a hashed address, their
 * browser's user agent, the actor string naming them, and the mobile number
 * itself — so a read that left no trace would let staff walk the business id
 * space with nothing anywhere to say they did.
 *
 * ⚠️ **THIS SCREEN SHOWS OWNER MOBILES, PLAINTEXT** — the whole point of the
 * evidence table (`owner_notification_consents`) is that a human can answer
 * "which number" from it, and a hash cannot answer that. The table itself is
 * `BelongsToTenant`, RLS `ENABLE`+`FORCE`d with a real `business_id`
 * predicate (its own creating migration and 10660's adding one), so this
 * screen reaches it only inside `Tenancy::actingAs()`, one lookup at a time,
 * exactly as `TermsAcceptances` does.
 *
 * ⚠️ **NO PERSONAL DATA REACHES A TOAST** (decision 104). The only toasts
 * here are "enter a number" and "there is no business N".
 */
final class OwnerNotifyConsents extends Component
{
    /**
     * The business number an admin typed. A string, because it comes from a
     * text input and an unparseable one has to be answerable rather than
     * fatal.
     */
    public string $lookup = '';

    /**
     * The business in view, once one has resolved.
     *
     * ⚠️ `#[Locked]` FOR `TermsAcceptances`' EXACT REASON — without it this is
     * an ordinary public property that arrives in the update payload, so
     * anybody who can reach this component could set it to any integer and
     * let `render()` read that tenant's consent evidence with `lookUp()`
     * never called and nothing recorded anywhere.
     */
    #[Locked]
    public ?int $businessId = null;

    public function mount(): void
    {
        // Repeated on the component rather than left to the route's `can:`
        // middleware — `TermsAcceptances`' own reason: a route-gate test
        // passes while `mount()` is wide open, because `can:` refuses during
        // route matching and the component never runs.
        $this->authorize(AdminAccess::GATE);
    }

    /**
     * Put a business in view.
     *
     * ⚠️ IT CAN THROW, WHICH IS CORRECT RATHER THAN OVERSIGHT — the audit
     * write is not wrapped and `$this->businessId` is set after it, so an
     * unwritable `audit_log` fails the whole action instead of showing the
     * record.
     */
    public function lookUp(AuditService $audit): void
    {
        $this->authorize(AdminAccess::GATE);

        $id = (int) trim($this->lookup);

        if ($id <= 0) {
            $this->businessId = null;
            Toaster::error('Enter a business number.');

            return;
        }

        $business = $this->resolve($id);

        if (! $business instanceof Business) {
            $this->businessId = null;
            Toaster::error("There is no business {$id}.");

            return;
        }

        Tenancy::actingAs(
            $id,
            fn (): AuditLogEntry => $audit->record('business.viewed_by_staff', $this->actor(), $business),
        );

        $this->businessId = $id;
    }

    public function render(OwnerConsentService $owner): View
    {
        $this->authorize(AdminAccess::GATE);

        $business = $this->businessId === null ? null : $this->viewing(
            fn (): ?Business => Business::query()->find($this->businessId),
        );

        return view('livewire.admin.owner-notify-consents', [
            'business' => $business,
            // ⚠️ THROUGH `OwnerConsentService`, NEVER `OwnerNotifyNumber`
            // DIRECTLY (10660) — see `currentNumberFor()`'s own docblock: the
            // "one writer" ArchitectureTest lint in OwnerChannelTest.php over
            // that model is really "the one file that touches this table at
            // all", because it carries no RLS predicate of its own to fall
            // back on.
            'number' => $business instanceof Business
                ? $owner->currentNumberFor($business)
                : null,
            'history' => $business instanceof Business
                ? $this->viewing(fn (): Collection => OwnerNotificationConsent::query()
                    ->where('business_id', $business->getKey())
                    ->latest('id')
                    ->get())
                : collect(),
        ]);
    }

    /**
     * Run a callback as the business in view — `TermsAcceptances`' own
     * cross-tenant step, in one place. `Tenancy::actingAs()` restores the
     * previous tenant in a finally block, so a throwing callback cannot leave
     * the admin's session pointed at somebody else's business.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function viewing(callable $callback): mixed
    {
        $id = $this->businessId ?? throw new InvalidArgumentException(
            'Look up a business first.'
        );

        return Tenancy::actingAs($id, $callback);
    }

    /**
     * The business a typed number resolves to, or null.
     *
     * Its own tenant context, because `businesses` is FORCE ROW LEVEL
     * SECURITY and a lookup with the admin's own tenant established would
     * answer "no" for every business but their own.
     */
    private function resolve(int $id): ?Business
    {
        return Tenancy::actingAs(
            $id,
            fn (): ?Business => Business::query()->whereKey($id)->first(),
        );
    }

    /**
     * Who is reading this, for the audit entry — `TermsAcceptances`' and
     * `PhiTenants`' convention: automation is a first-class actor in this
     * log, so a user foreign key would have nothing to point at for most
     * entries.
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id === null ? 'admin' : 'user:'.$id;
    }
}
