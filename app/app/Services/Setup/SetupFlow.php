<?php

declare(strict_types=1);

namespace App\Services\Setup;

use App\Enums\WizardStep;
use App\Models\User;
use App\Models\WizardProgress;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Every rule about the onboarding wizard's shape, in one testable place.
 *
 * Components render and collect; this decides. The same reasoning that put
 * ReviewRouter outside a job (decision 370): a rule that lives in a Livewire
 * class can only be tested by rendering something, and a later row adding a
 * step should change one file rather than five.
 *
 * COMPLETION IS KEYED ON A RECORDED ANSWER, NEVER ON THE COLUMNS IT WROTE.
 * ⚠️ That used to have a second reason — an owner who chose "ask everyone" wrote
 * a threshold of 0 and *no* `gating_ack_at`, so keying on the acknowledgement
 * would have called them unanswered — and the column is gone (2074, 2660). The
 * first reason is the one that survives and it is sufficient: the state is
 * *derivable* from a stored threshold, and that inference is not what is trusted
 * here, because a future change to the seeded default would make it quietly
 * wrong and the failure would be a wizard that thinks it is finished.
 * ⚠️ **It is now strictly more necessary than it was.** Every location is
 * provisioned with a threshold of 4 already stored, so "has an invite threshold"
 * is true of a tenant who has never seen this screen — a derived reading would
 * mark every wizard complete before it started.
 *
 * ⚠️ REQUIRED_STEPS IS A THIRD EXCEPTION TO `29` §7.2's OWN RULE. That section
 * says every step is skippable except Google and consent basics; COMP-02 says
 * the threshold screen is required. Recorded rather than silently resolved —
 * see decision 44x. It gates *finishing*, not entering: the owner may leave the
 * wizard at any point, and because decision 290 already fails open, no threshold
 * applies until the ack exists. A compliance screen is not the first wall a new
 * owner hits.
 *
 * ALL THREE WRITE METHODS — markAnswered(), advanceTo(), complete() — SHARE
 * ONE CONTRACT: the `$progress` instance the caller passed in is left
 * current. advanceTo() and complete() get this for free, because they write
 * straight to that instance. markAnswered() does not — it locks and writes a
 * separately fetched row instead (see its own docblock) — so it syncs
 * `$progress` from that row before returning. Without that sync, a caller
 * doing `markAnswered($progress, ...); if (mayComplete($progress)) {
 * complete($progress); }` reads stale `data` off `$progress` and is told an
 * answer it just recorded does not exist. One contract, so no caller has to
 * remember which of the three methods needs a `->fresh()` and which does
 * not — none of them do.
 */
final class SetupFlow
{
    /**
     * The steps that must be answered before the wizard may be marked complete.
     *
     * @var array<int, WizardStep>
     */
    public const REQUIRED_STEPS = [WizardStep::ReviewRules];

    /**
     * The key under which answers live inside `wizard_progress.data`.
     *
     * Nested rather than top-level, because that column already carries the
     * audit pre-fill row 2 slice H writes (decision 274) and an answer key
     * colliding with a pre-fill key would silently overwrite the findings the
     * Welcome step exists to show.
     */
    private const ANSWERS_KEY = 'answers';

    public function progressFor(User $user): WizardProgress
    {
        Tenancy::idOrFail();

        return WizardProgress::query()
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    /**
     * Records `$answer` for `$step` and refreshes `$progress` in place — see
     * the class docblock's "one contract" note.
     *
     * WRITES TO A ROW THIS METHOD FETCHED, NOT DIRECTLY TO THE CALLER'S
     * INSTANCE, and locks it before reading — the same shape as, and for the
     * same reason as, ConsentService::refreshDerived(). The read-then-merge-
     * then-write is not atomic on its own: two near-simultaneous
     * markAnswered() calls for two different steps (a double-tap on a
     * flaky connection, or two tabs left open) would each read `data`
     * missing the other's uncommitted answer, and the later commit would
     * win with the older one — silently erasing whichever step lost the
     * race, with no error and no trace.
     *
     * @param  array<string, mixed>  $answer
     */
    public function markAnswered(WizardProgress $progress, WizardStep $step, array $answer = []): void
    {
        DB::transaction(function () use ($progress, $step, $answer): void {
            $target = WizardProgress::query()->lockForUpdate()->findOrFail($progress->id);

            $data = $target->data ?? [];

            $answers = $data[self::ANSWERS_KEY] ?? [];
            $answers[$step->value] = $answer;
            $data[self::ANSWERS_KEY] = $answers;

            $target->forceFill(['data' => $data])->save();

            // Syncs the caller's instance from the row this method actually
            // wrote — the other half of the "one contract" note above. Without
            // this, $progress itself would still show the data it had before
            // this call, and isAnswered()/answerFor()/mayComplete() read
            // $progress->data directly rather than re-querying.
            $progress->setRawAttributes($target->getAttributes(), true);
        });
    }

    public function isAnswered(WizardProgress $progress, WizardStep $step): bool
    {
        return array_key_exists($step->value, ($progress->data ?? [])[self::ANSWERS_KEY] ?? []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function answerFor(WizardProgress $progress, WizardStep $step): ?array
    {
        return ($progress->data ?? [])[self::ANSWERS_KEY][$step->value] ?? null;
    }

    /**
     * Advances the wizard to `$step` and refreshes `$progress` in place —
     * see the class docblock's "one contract" note.
     *
     * FORWARD ONLY. `SetupStep::mountSetupStep()` calls this unconditionally on
     * every mount, and `redirectRoute(..., navigate: true)` pushes browser
     * history — so without this guard, an ordinary Back press re-mounts an
     * earlier step and silently regresses `current_step`, which is exactly what
     * `SetupController` — the resume path this whole flow exists for — then
     * trusts. Answers already recorded in `data.answers` are unaffected either
     * way; this only guards which step a resumed session lands on. A step equal
     * to the current one is a no-op rather than a rewrite, since $progress
     * already reflects it.
     */
    public function advanceTo(WizardProgress $progress, WizardStep $step): void
    {
        if ($step->position() <= $progress->current_step->position()) {
            return;
        }

        $progress->forceFill(['current_step' => $step])->save();
    }

    public function mayComplete(WizardProgress $progress): bool
    {
        foreach (self::REQUIRED_STEPS as $step) {
            if (! $this->isAnswered($progress, $step)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Marks the wizard finished and refreshes `$progress` in place — see the
     * class docblock's "one contract" note. Throws when `mayComplete()` is
     * false.
     */
    public function complete(WizardProgress $progress): void
    {
        if (! $this->mayComplete($progress)) {
            throw new RuntimeException(
                'The review rules step has not been answered, so this wizard cannot be '
                .'marked finished. COMP-02 requires that screen, and it is the only place '
                .'an owner is shown what inviting selectively means before their seeded '
                .'threshold starts doing it.',
            );
        }

        $progress->forceFill([
            'current_step' => WizardStep::Done,
            'completed' => true,
        ])->save();
    }
}
