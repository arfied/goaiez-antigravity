<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Enums\SupportMacroSlot;
use App\Exceptions\LegalCanonUnavailable;
use App\Models\SupportMacro;
use App\Support\LegalCanon;
use App\Support\Support\SupportMacroCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The support macro library, and the guard that stops one of its slots reaching
 * a tenant — T308 §A, CC-5 §0 and §4.
 *
 * ## ⛔ THE SLOT-RESOLUTION GUARD, AND WHY IT LIVES HERE RATHER THAN NOWHERE
 *
 * CC-5 §0: *"wherever a seeded template renders for sending, an unresolved slot
 * BLOCKS the send with a named error."* This application has two such render
 * points and they were in opposite states when this slice started:
 *
 *  - **The SMS path already had one.** `ReactComposer::assertNothingUnsubstituted()`
 *    refuses a rendered body still carrying `{…}`, naming the offender, and it
 *    is the thing standing between a typo and a real handset. Verified rather
 *    than rebuilt — CC-5's verify-or-extend, and *"already exists as X"* is a
 *    full pass.
 *  - **The support path had none.** {@see SupportDesk::answer()} took whatever
 *    string the composer handed it, so a macro pasted and half-filled reached
 *    the tenant with `{cancel_link}` showing. {@see self::assertEverySlotResolved()}
 *    is the guard that was missing, and `SupportDesk::answer()` is where it is
 *    asked — the last point at which a reply is still a draft.
 *
 * ⚠️ **IT REFUSES OUR SLOTS AND NOT EVERY BRACE, AND THE NARROWING IS THE
 * DESIGN.** A guard rejecting any `{…}` would refuse a staff reply that
 * legitimately quoted one — an agent explaining a macro to a colleague, or
 * pasting a JSON fragment from a bug report — and a lint tuned until it cries
 * wolf is one somebody turns off (511). The vocabulary is
 * {@see SupportMacroSlot}, which is a closed set, so the guard is exact in both
 * directions.
 *
 * ## ⚠️ THE CANON SLOT IS BOUND, NOT PASTED
 *
 * S-3 carries `{guarantee_sentence}` and nothing else authored, because T308
 * points at R39 rather than writing the promise out. {@see self::rendered()}
 * resolves it from `legal.guarantee_sentence` the moment the macro is inserted,
 * so counsel's next edit reaches every future paste and none of the ones already
 * sent. While that key is unset the macro is **not offered** — see
 * {@see self::insertable()} — rather than offered and then refused, because an
 * insert-button that fails when pressed is a support surface.
 */
final class SupportMacros
{
    public function __construct(private readonly LegalCanon $canon) {}

    /**
     * Every macro, in library order.
     *
     * @return Collection<int, SupportMacro>
     */
    public function library(): Collection
    {
        return SupportMacro::query()
            ->orderBy('position')
            ->orderBy('key')
            ->get();
    }

    /**
     * Every macro an agent may actually insert right now, already rendered.
     *
     * ⛔ **A MACRO WHOSE CANON IS UNSET IS ABSENT RATHER THAN BROKEN.** S-3 is
     * the whole of R39's promise; with `legal.guarantee_sentence` empty there is
     * nothing to insert, and offering a button that raises when pressed would
     * teach an agent that the console is unreliable. `library()` still returns
     * it, so an Ops screen can say *why* it is missing rather than pretending
     * the library is ten long.
     *
     * @return list<array{key: string, title: string, body: string}>
     */
    public function insertable(): array
    {
        $insertable = [];

        foreach ($this->library() as $macro) {
            try {
                $body = $this->rendered($macro);
            } catch (LegalCanonUnavailable) {
                continue;
            }

            $insertable[] = ['key' => $macro->key, 'title' => $macro->title, 'body' => $body];
        }

        return $insertable;
    }

    /**
     * One macro with its canon-bound slots filled and its agent slots left.
     *
     * @throws LegalCanonUnavailable when a bound sentence has not been set
     */
    public function rendered(SupportMacro $macro): string
    {
        $body = $macro->body;

        foreach (SupportMacroSlot::cases() as $slot) {
            if ($slot->canonKey() === null) {
                continue;
            }

            if (! str_contains($body, $slot->placeholder())) {
                continue;
            }

            $body = str_replace($slot->placeholder(), $this->canonFor($slot), $body);
        }

        return $body;
    }

    /**
     * The first of our own slots still sitting in a body, or null.
     *
     * ⚠️ **THE FIRST RATHER THAN ALL OF THEM, BECAUSE THE MESSAGE IS FOR A
     * PERSON.** An agent who left three slots in fixes the first, sends, and is
     * told about the second — which is three round trips, and is still better
     * than a list they scan past. The alternative was a refusal naming every
     * one, and the refusal that gets read is the short one.
     */
    public function unresolvedSlotIn(string $body): ?string
    {
        foreach (SupportMacroSlot::cases() as $slot) {
            if (str_contains($body, $slot->placeholder())) {
                return $slot->placeholder();
            }
        }

        return null;
    }

    /**
     * Refuse a reply that still carries a macro slot.
     *
     * ⛔ **CALLED FROM `SupportDesk::answer()` AND NOWHERE ELSE MATTERS.** That
     * is the one method that writes a staff message into a tenant's thread; a
     * check on the Livewire component instead would be bypassed by the console's
     * own service call, by the mailbox poller, and by any second screen.
     *
     * ⚠️ **IT IS NOT ASKED ON THE TENANT'S OWN REPLY.** `replyAsTenant()` takes
     * whatever a business owner types, braces included, and refusing it would be
     * this library's vocabulary policing somebody else's words.
     *
     * @throws InvalidArgumentException so the console renders it on the reply
     *                                  field — `Livewire\Support\Tickets::answer()`
     *                                  already catches exactly this type
     */
    public function assertEverySlotResolved(string $body): void
    {
        $slot = $this->unresolvedSlotIn($body);

        if ($slot === null) {
            return;
        }

        throw new InvalidArgumentException(
            "This reply still contains {$slot}, which is a macro placeholder and not a word. "
            .'Fill it in before sending: the account would receive the braces exactly as they are, '
            .'and nothing between here and their inbox would have shown them to you.'
        );
    }

    /**
     * Load `SupportMacroCatalog` into `support_macros` — `php artisan macros:sync`.
     *
     * ⚠️ **THE CATALOGUE IS AUTHORITATIVE**, on `CampaignPacks::sync()`'s
     * argument and for its reason: nothing but the catalogue has ever written
     * this table, there is no Ops editor for a macro, and refusing to update
     * would strand a corrected sentence in a file. T308 §A2's *"edits in the
     * library, renders in the composer"* — the library is this catalogue until
     * somebody builds a screen.
     *
     * @return list<string> the key of every macro written or moved
     */
    public function sync(bool $dryRun = false): array
    {
        $written = [];

        DB::transaction(function () use ($dryRun, &$written): void {
            foreach (SupportMacroCatalog::macros() as $macro) {
                $existing = SupportMacro::query()->where('key', $macro['key'])->first();

                if ($existing instanceof SupportMacro
                    && $existing->title === $macro['title']
                    && $existing->body === $macro['body']
                    && $existing->position === $macro['position']
                ) {
                    continue;
                }

                $written[] = $macro['key'];

                if ($dryRun) {
                    continue;
                }

                SupportMacro::query()->updateOrCreate(['key' => $macro['key']], $macro);
            }
        });

        return $written;
    }

    /**
     * @throws LegalCanonUnavailable
     */
    private function canonFor(SupportMacroSlot $slot): string
    {
        return match ($slot) {
            SupportMacroSlot::GuaranteeSentence => $this->canon->guaranteeSentence(),
            // ⚠️ **AN ARM RATHER THAN A DEFAULT, SO A NEW BOUND SLOT CANNOT
            // FALL THROUGH SILENTLY.** `canonKey()` is what decides a slot is
            // bound and this is what has to know how to fetch it; a `default`
            // returning the placeholder would put a `{…}` straight back into a
            // rendered body and the guard would then refuse the agent's reply
            // for something they never typed.
            default => throw new InvalidArgumentException(
                "The slot {$slot->placeholder()} declares a registry key and this method does not "
                .'know how to read it. A bound slot needs an arm here, or it is not bound.'
            ),
        };
    }
}
