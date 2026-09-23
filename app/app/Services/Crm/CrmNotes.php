<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\CrmNote;
use App\Models\Customer;
use App\Models\User;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * The owner's own notes on a contact (`34` §1.2).
 *
 * ⚠️ **`crm_notes` HAD A MODEL, A FACTORY, AN RLS POLICY AND A SCHEMA ISOLATION
 * TEST, AND NOTHING IN `app/` HAS EVER TOUCHED IT.** Decision 272's shape, and
 * this codebase has now recorded it fourteen times — `Business::provision()`,
 * `autopilot_settings` (377), `feedback_pages`, `review_destinations`, `plugins`
 * (399), `PlanEntitlement::currentFor()` (518), `data_classification` (465),
 * `gating_ack_at` (520–528), `subscriptions` (580), `users.role` (740) and the
 * rest. The tell is always the same and it is worth naming once more: an
 * isolation test passes perfectly against a table nothing writes, because
 * proving a row cannot be read across a tenant boundary needs no row.
 *
 * `crm_tasks` and `crm_timeline` sit beside it in exactly that state and are
 * deliberately still empty after this slice — see `CustomerTimeline` for why
 * reading the timeline table would have been the worse of the two mistakes.
 *
 * ## Why a service for something this small
 *
 * `34` §1.2 asks for *"simple owner notes on the contact (append, edit-own, no
 * rich text)"* — three words that hide three rules, and a component writing
 * `CrmNote::create()` inline would get one of them right. A note carries an
 * author who may be gone by the time somebody reads it, its `created_at` is
 * nullable and nothing else on the row is monotonic, and it is the first thing
 * in this application that stores free text the owner typed about a named
 * person. One place, one lint holding it there.
 */
final class CrmNotes
{
    /**
     * `34` §1.2 wants a note, not a document. Long enough for "wants the 9am
     * slot, allergic to the resin we normally use", short enough that nobody
     * pastes a contract into a field with no editor.
     */
    public const int MAX_LENGTH = 2000;

    public function maxLength(): int
    {
        return app(DefaultsRegistry::class)->int('crm.notes.max_length');
    }

    /**
     * Append a note to a contact.
     *
     * ⚠️ **`created_at` IS SET HERE BECAUSE NOTHING ELSE WILL.** `CrmNote` has
     * `$timestamps = false` — `crm_notes` carries `created_at` and no
     * `updated_at`, so Eloquent's automatic stamping is off wholesale rather
     * than half-on. A note inserted without it is undated forever, and an
     * undated note is the one thing a note cannot be: the whole value of "wants
     * the 9am slot" is when somebody learned it.
     *
     * The author is nullable in the schema and required here. A null
     * `user_id` is what a *system*-written note would look like, and nothing
     * writes one — every note on this path was typed by a signed-in human, so
     * accepting null would record "we don't know who wrote this" about a row
     * where we always do.
     */
    public function add(Customer $customer, string $body, ?User $author = null): CrmNote
    {
        Tenancy::idOrFail();

        $body = trim($body);

        if ($body === '') {
            throw new InvalidArgumentException(
                'A note needs a body. An empty note is a row that says somebody had '
                .'something to record and lost it.',
            );
        }

        if (mb_strlen($body) > $this->maxLength()) {
            throw new InvalidArgumentException(
                'A note is longer than '.$this->maxLength().' characters. The form refuses '
                .'this first; reaching here means a caller skipped validation.',
            );
        }

        $author ??= Auth::user();

        if (! $author instanceof User) {
            throw new InvalidArgumentException(
                'A note needs an author. Notes are typed by people, and a note nobody '
                .'signed is unattributable the moment the person who typed it leaves.',
            );
        }

        return CrmNote::query()->create([
            'customer_id' => $customer->getKey(),
            'user_id' => $author->getKey(),
            'body' => $body,
            'created_at' => now(),
        ]);
    }

    /**
     * A contact's notes, newest first.
     *
     * ⚠️ **ORDERED BY `id`, NEVER BY `created_at`.** Postgres sorts NULL *first*
     * on a DESC ordering and `created_at` is nullable on this table, so
     * `latest('created_at')` would float any undated row above every dated one
     * and the `id` tiebreak would never run. That is the defect decision 289's
     * neighbourhood hit three times in one slice, and an `ArchitectureTest` lint
     * now fails the build on `latest`/`oldest`/`orderByDesc` over anything but
     * `id` in `app/`. `add()` above is why no row here should be undated; this
     * ordering is why a row that somehow is cannot lie about its place.
     *
     * @return Collection<int, CrmNote>
     */
    public function forCustomer(Customer $customer): Collection
    {
        Tenancy::idOrFail();

        return CrmNote::query()
            ->where('customer_id', $customer->getKey())
            ->with('author')
            ->orderByDesc('id')
            ->get();
    }
}
