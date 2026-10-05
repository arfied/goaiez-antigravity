<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Contracts\VoiceProvider;
use App\Enums\CallRoutingMode;
use App\Enums\LiveAnswerMode;
use App\Models\Call;
use App\Models\SupportSetting;
use App\Modules\CAgent\Actions\AgentVoiceTurnReadAction;
use App\Services\Config\DefaultsRegistry;
use App\Services\Sms\TenantNumbers;
use App\Services\Voice\CallForwarding;
use App\Services\Voice\RecordingAnnouncement;
use App\Services\Voice\VoiceGreeting;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * Your phone — call forwarding setup and tenant number allocation, shown to the person
 * who has to dial it.
 *
 * ⛔ **NOT A WIZARD STEP, AND THAT IS DECISION 2308's FINDING RATHER THAN A
 * PREFERENCE.** `WizardStep`'s docblock used to end *"Nothing else reads position
 * numbers"* and that sentence is false: `2026_08_03_161908_change_wizard_
 * progress_current_step_to_string` carries a `CASE` mapping each integer to a
 * step, and `WizardStepTest` iterates `WizardStep::cases()` asserting the
 * migration knows each one's position. Inserting a step before `Done` shifts
 * `Done` from 5 to 6 and reddens both — and the only way back to green is
 * editing an applied migration whose `WHEN 5 THEN 'done'` records what the
 * integer 5 meant in rows written **before it ran**. `Account\Knowledge` hit
 * this and moved out; this screen never went in. ✅ The correction is now in
 * `WizardStep`'s own docblock as well as here (2915), because that is the file
 * the next builder opens.
 *
 * ⚠️ **AND `/account` IS THE BETTER HOME ON ITS OWN MERITS.** A tenant changes
 * phone system, changes carrier, or turns the forward off for a fortnight while
 * they hire — `SetupFlow` will not re-enter a completed step, so a one-shot
 * wizard question is a decision they could never revisit. `Account\ReviewRules`
 * records the same argument for the same reason.
 *
 * ⚠️ **THE SCREEN RECORDS AN INTENTION AND SAYS SO.** Nothing here can observe
 * whether the carrier accepted the code — that needs a test call through the
 * Infobip Voice API, which 2109 records as the one external activation still
 * outstanding. So `forwarding_verified_at` stays unwritten (2913), the copy says
 * *"we cannot see your carrier's settings"* rather than reporting a state, and
 * no tick anywhere claims a forward is live.
 *
 * ⚠️ **IT WRITES NOTHING ITSELF.** `CallForwarding` is the only writer of
 * `support_settings` — the first this application has ever had — and this
 * component validates one answer and calls one method. `Account\ReviewRules`
 * makes the same split and states why: that is what stops a second screen
 * becoming a second set of rules.
 *
 * AUTHORIZATION IS A POLICY (`CLAUDE.md`). A `staff` user may read how calls are
 * handled — they answer the phone — and may not move it.
 */
#[Layout('components.account.layout')]
final class Calls extends Component
{
    /**
     * The chosen mode, held as a string because that is what a radio submits.
     *
     * Not a typed `CallRoutingMode`: Livewire hydrates a public property to its
     * declared type before validation runs, so a hand-posted `mode=proxy!` would
     * be a `ValueError` out of `tryFrom` rather than a validation message. The
     * same reasoning `Account\ReviewRules::$rating` records.
     */
    public string $mode = '';

    /**
     * Who picks up first (owner ruling D-6, 2026-10-05) — a string for `$mode`'s reason.
     */
    public string $liveAnswer = '';

    public function mount(CallForwarding $forwarding): void
    {
        abort_if(Tenancy::id() === null, 403);

        $this->mode = $forwarding->modeFor()->value;
        $this->liveAnswer = $forwarding->liveAnswerFor()->value;
    }

    public function save(CallForwarding $forwarding): void
    {
        // Authorization before validation: telling somebody their answer is
        // invalid and then refusing them for their role is two errors for one
        // action, and the second is the one that mattered.
        Gate::authorize('create', SupportSetting::class);

        $validated = $this->validate([
            'mode' => ['required', Rule::enum(CallRoutingMode::class)],
        ], [
            'mode.required' => 'Choose how you want your calls handled.',
            'mode.enum' => 'Choose how you want your calls handled.',
        ]);

        $mode = CallRoutingMode::from($validated['mode']);

        $forwarding->chooseMode($mode, 'user:'.(auth()->id() ?? 'unknown'));

        // Outcome language, and no personal data in the toast (104). It says
        // what was saved and deliberately does not say the forward is working —
        // nothing here has checked, and 2913 is why.
        Toaster::success('Saved — '.mb_strtolower($mode->label()));
    }

    /**
     * Who picks up first: the AI receptionist straight away, or the owner's phone first (owner ruling D-6, 2026-10-05).
     *
     * The same split as {@see self::save()}: this validates one answer and calls one method, and only an owner or a manager
     * may move it — it decides whether a customer's call reaches a person or an AI first.
     */
    public function saveLiveAnswer(CallForwarding $forwarding): void
    {
        Gate::authorize('create', SupportSetting::class);

        $validated = $this->validate([
            'liveAnswer' => ['required', Rule::enum(LiveAnswerMode::class)],
        ], [
            'liveAnswer.required' => 'Choose who picks up first.',
            'liveAnswer.enum' => 'Choose who picks up first.',
        ]);

        $mode = LiveAnswerMode::from($validated['liveAnswer']);

        $forwarding->chooseLiveAnswer($mode, 'user:'.(auth()->id() ?? 'unknown'));

        Toaster::success('Saved — '.mb_strtolower($mode->label()));
    }

    /**
     * How many calls the history shows.
     *
     * ⚠️ **A FIXED WINDOW RATHER THAN A PAGINATOR, AND IT IS THE MOBILE FLOOR
     * THAT DECIDES IT.** T176 §2's rule for every owner surface is that the loop
     * works one-handed on a phone; a paginated call log is a second screen to
     * build and a control to press before the thing an owner came for. What they
     * came for is *"who rang and did we miss them"*, which is the top of the
     * list.
     */
    private const int RECENT_CALLS = 20;

    public function render(
        CallForwarding $forwarding,
        TenantNumbers $numbers,
        VoiceProvider $voice,
        RecordingAnnouncement $announcement,
        DefaultsRegistry $registry,
    ): View {
        // Refused rather than resolved when there is no tenant — `Knowledge`'s
        // reasoning exactly: internal staff belong to no business by design, so
        // a signed-in support agent typing this URL is the ordinary way to
        // arrive with nothing resolved, and letting `Tenancy::idOrFail()` reach
        // the renderer is a 500 that reads as our page being broken.
        abort_if(Tenancy::id() === null, 403);

        return view('livewire.account.calls', [
            // ⚠️ RE-READ RATHER THAN `$this->mode`. The read-only panel shown to
            // a `staff` user must state what is actually in force, and `$mode`
            // is a property the browser can post — a person who may not change
            // this setting must not be able to change what the page says it is.
            'current' => $forwarding->modeFor(),
            'number' => $numbers->displayNumberFor(Tenancy::idOrFail()),
            'codes' => $forwarding->dialCodes(),
            'ringSeconds' => $forwarding->ringTimeoutSeconds(),
            'mayChoose' => Gate::allows('create', SupportSetting::class),
            'modes' => CallRoutingMode::cases(),

            // Who picks up first (owner ruling D-6). Re-read rather than `$this->liveAnswer`, for `current`'s reason.
            'currentLiveAnswer' => $forwarding->liveAnswerFor(),
            'liveAnswerModes' => LiveAnswerMode::cases(),

            // ⚠️ WHETHER THE RECEPTIONIST ANSWERS AT ALL TODAY. The platform switch is off until the voice worker and its
            // vendors exist, and a panel offering a choice about an AI that answers nothing must say so rather than imply it.
            'receptionistLive' => $registry->value('voice.live_agent.enabled') === true,

            // ⛔ **THE EXTERNAL GATE, SHOWN TO THE OWNER RATHER THAN HIDDEN FROM
            // THEM** (T176 §7 item 3). Until Infobip activates Voice/Calls on the
            // account, this screen's forwarding codes send calls to a number that
            // records nothing — and a page that explains how to forward a line
            // while quietly not answering it is the worst version of this
            // feature. The panel says so in outcome language and gives them
            // nothing to press, because there is nothing they can do about it.
            'answeringLive' => $voice->isActivated(),

            // ⚠️ **THE ANNOUNCEMENT SENTENCE, ON THE OWNER'S OWN SCREEN.** 2104
            // makes it unconditional on every recorded call in every state, and
            // an owner is entitled to know what their customers hear before they
            // speak — not least because some of them will be asked about it.
            // `VoiceGreeting::ANNOUNCEMENT` is the single source; a second copy
            // in this template is how the two drift.
            'announcement' => VoiceGreeting::ANNOUNCEMENT,

            // ⛔ **WHETHER THAT SENTENCE IS TRUE TODAY, RATHER THAN PRINTED AS
            // THOUGH IT WERE** (4506). The template stated *"every call we pick
            // up is recorded, and every caller is told so"* unconditionally,
            // while nothing in this application enforced the announcement — so
            // an operator who skipped the vendor-side configuration produced a
            // §632 violation this page had promised was impossible, with the
            // penalty on the tenant. Both halves are needed: the vendor must be
            // answering calls at all, and an operator must have attested the
            // clip is first. It is the same fact `IngestVoiceEventJob` refuses
            // to store a recording without.
            'recordingLive' => $voice->isActivated() && $announcement->isAttested(),

            // ⚠️ **EAGER-LOADED, BECAUSE THE TEMPLATE ASKS EVERY ROW WHETHER IT
            // HAS A MESSAGE.** Twenty lazy loads on the one screen an owner
            // opens most is 4x's N+1 with a phone on the other end of it.
            // ⚠️ **`NULLS LAST` SPELLED OUT, AND A LINT CAUGHT THE VERSION
            // THAT DID NOT.** `started_at` is nullable — a call whose vendor
            // read failed has none — and Postgres puts NULLs FIRST in a
            // descending sort, so `orderByDesc('started_at')` would have put
            // every unreadable call above today's real ones on the one screen an
            // owner opens to see who rang. `ConventionsTest`'s "no descending
            // order relies on Postgres putting NULLs first" reddened on this
            // file the first time it ran.
            //
            // ⚠️ **`id` IS THE TIE-BREAK**, so two calls in the same second have
            // a stable order rather than whatever the planner returns.
            'calls' => $calls = Call::query()
                ->with('voicemail')
                ->orderByRaw('started_at DESC NULLS LAST')
                ->orderByDesc('id')
                ->limit(self::RECENT_CALLS)
                ->get(),

            // What was said on the calls the AI receptionist answered, read through C-Agent's own reader so this screen
            // never reaches into its table. One query for the twenty rows, not one per row.
            'turns' => app(AgentVoiceTurnReadAction::class)->forCalls(
                $calls->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
            ),
        ]);
    }
}
