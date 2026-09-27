{{--
    The owner's account screen (`29` §11.2 row 5's Pause).

    COLOUR IS NOT THE SIGNAL (`22`). Paused and running are told apart by the
    sentence at the top and by which button is offered — never by a hue alone —
    so the state survives monochrome, a screen reader, and the 8% of men who
    would not see a red badge as different from a green one.

    OUTCOME LANGUAGE THROUGHOUT (`29` §2 rule 47). "Everything is paused", not
    "autopilot disabled"; "Start everything again", not "resume automations".
    The button says what the owner gets, and the verb survives into the state
    it produces.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Your account</h1>
        <p class="mt-1 text-base text-ink-2">
            What we do for you, and how to stop us.
        </p>
    </div>


    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        @if ($paused)
            <h2 class="font-display text-lg font-semibold text-ink">Everything is paused</h2>

            <p class="mt-2 text-base text-ink-2">
                We are not doing anything for you right now — no review invitations,
                no replies, nothing published.
                @if ($pausedAt)
                    Paused {{ $pausedAt->diffForHumans() }}.
                @endif
            </p>

            {{--
                Said out loud, because it is the thing an owner assumes went
                away and would be upset to lose. The QR code on their counter
                keeps working, and the words their customers leave are still
                theirs when they come back.
            --}}
            <p class="mt-3 text-base text-ink-2">
                Your customers can still leave feedback, and it is waiting for you.
            </p>

            @if ($pausedBySupport)
                <div class="mt-4 rounded-[--radius-control] border border-rule p-3">
                    <p class="text-base text-ink">GO AI EZ support paused this for you.</p>
                    @if ($pauseReason)
                        <p class="mt-1 text-sm text-ink-2">{{ $pauseReason }}</p>
                    @endif
                </div>
            @endif

            @if ($confirmingResume)
                <div class="mt-5 rounded-[--radius-control] border border-rule p-4">
                    <p class="text-base text-ink">
                        Start everything again? We will begin asking your customers for
                        reviews and replying on your behalf.
                    </p>

                    <div class="mt-4 flex flex-wrap gap-3">
                        {{--
                            ⚠️ `id` EXISTS SO A TEST CAN CLICK THIS RELIABLY.
                            The label's own comma makes it explicit CSS to
                            Pest's browser plugin (`Selector::isExplicit()`
                            treats `,` as a CSS special character), so a bare
                            `click('Yes, start everything')` is sent to the
                            CSS engine as an invalid selector rather than
                            matched as text.
                        --}}
                        <x-ui.button id="confirm-resume" wire:click="resume" type="button">Yes, start everything</x-ui.button>

                        <button
                            type="button"
                            wire:click="cancelResume"
                            class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
                        >
                            Stay paused
                        </button>
                    </div>
                </div>
            @else
                <div class="mt-5">
                    <x-ui.button wire:click="confirmResume" type="button">Start everything again</x-ui.button>
                </div>
            @endif
        @else
            <h2 class="font-display text-lg font-semibold text-ink">Everything is running</h2>

            <p class="mt-2 text-base text-ink-2">
                If you need us to stop, stop us. Nothing is lost — your customers can
                still leave feedback, and you can start again whenever you want.
            </p>

            {{--
                No confirmation on this one, deliberately: pausing is the safe
                direction, it is fully reversible on this same screen, and a
                confirm step on the stop button is a confirm step in the way at
                the moment somebody needs it most. Starting again is what gets
                the second look.
            --}}
            <div class="mt-5">
                <x-ui.button wire:click="pause" type="button">Pause everything</x-ui.button>
            </div>
        @endif
    </div>

    {{--
        ⚠️ THE SECOND DOOR ON THE GATING QUESTION, and the reason decision 1143's
        ruling is honoured rather than merely recorded. The wizard asks it once
        and `SetupFlow` will not re-enter a completed step, so without this the
        threshold would be a tenant setting nobody could change — which is the
        shape of decision 272 applied to a control rather than to a table.

        Rendered whether or not the account is paused: choosing who to ask is
        the owner's own act, and a pause stops us acting, not them (821).
    --}}
    {{--
        ⚠️ THE HYPHENATED ALIAS IS LOAD-BEARING, NOT A STYLE CHOICE.
        `App\Livewire\Account\ReviewRules` auto-discovers as
        "account.review-rules", and Livewire 4 validates a nested child's tag
        against letters, numbers and hyphens only — the dot throws "Invalid
        Livewire child tag name" and takes this whole page with it, Pause
        Everything included. `AppServiceProvider::registerNestableLivewire
        Components()` registers the alias and records why. And the failure
        arrives on the first *update*, not the first render, so a nested
        subdirectory component looks correct until somebody clicks something.
    --}}
    {{--
        ⚠️ PLACED HERE RATHER THAN AT THE TOP OF THE PAGE, AND THE PLACEMENT IS
        THE HONEST PART (3060–3079). The two panels immediately below — the
        review rules and the timezone — are the per-location ones, and both went
        dark for a multi-location tenant before this existed (the timezone one
        with no explanation at all, 3062). **Pause Everything above is
        business-wide**, so a picker sitting over it would read as though an
        owner were pausing one branch, which is not what that control does.
        Colour carries none of this (`22`); the scope is the reading order and
        the label.

        It renders itself away entirely for the one-location tenant, so the
        overwhelmingly common shape gains no control it does not need.
    --}}
    <x-account.location-picker :locations="$locationOptions" :selected="$selectedLocation" />

    <livewire:account-review-rules wire:key="account-review-rules" />

    {{--
        HOW OUR REPLIES SHOULD READ (GBP-03, decision 6460's writer for 1732).

        ⚠️ BUSINESS-WIDE, WHICH IS WHY IT SITS **BELOW** THE PICKER AND NOT
        INSIDE ITS SCOPE. `response_templates` carries a `business_id` and no
        `location_id`; the two panels above and below the picker are the
        per-location ones. It renders for every tenant, one location or ten,
        because there is no location answer for it to be missing.

        ⚠️ RENDERED WHETHER OR NOT THE ACCOUNT IS PAUSED, on the review-rules
        panel's reasoning (821): a pause stops us acting, not them, and writing
        down how a reply should read is the owner's own act.

        ⚠️ THE HYPHENATED ALIAS IS LOAD-BEARING — see the comment above the
        review-rules panel and `AppServiceProvider::registerNestableLivewire
        Components()`. A dotted tag dies on the first *update*, taking Pause
        Everything down with it.
    --}}
    <livewire:account-reply-examples wire:key="account-reply-examples" />

    {{--
        THE HOURS WE ARE ALLOWED TO TEXT (1597).

        A control, not navigation — the owner changes something here and it is
        changed, which is the line this page holds.

        ⚠️ NOTHING DERIVES THIS FROM THE STATE ON A CONTACT (1598). Six US states
        straddle two timezones and the error runs in the direction that texts
        people earlier, so the question is asked rather than answered for them.

        Absent rather than disabled when the tenant does not have exactly one
        location: the timezone is per location and there is no location picker
        anywhere in this application, so a panel that silently wrote to whichever
        row came back first would be worse than one that is not there.
    --}}
    @if ($timezoneAvailable)
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5" data-timezone-panel>
            <h2 class="font-display text-lg font-semibold text-ink">When we can text your customers</h2>

            <p class="mt-2 text-base text-ink-2">
                Some states only allow business texts during set hours. Tell us where you
                are and we will keep to them.
            </p>

            <form wire:submit="saveTimezone" class="mt-4 space-y-3">
                <div>
                    <label for="location-timezone" class="block text-sm font-medium text-ink">Your timezone</label>
                    <select
                        id="location-timezone"
                        wire:model="timezone"
                        class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                    >
                        <option value="">Choose your timezone</option>
                        @foreach ($timezones as $zone)
                            <option value="{{ $zone }}">{{ str_replace('_', ' ', $zone) }}</option>
                        @endforeach
                    </select>
                    @error('timezone')
                        <p class="mt-1 text-sm text-ink">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-ui.submit size="default" target="saveTimezone" busy="Saving…">Save</x-ui.submit>
                </div>
            </form>
        </div>
    @endif

    {{--
        THE OWNER CHANNEL (10540, the owner ruling of 2026-08-27) — the same
        control `App\Livewire\Setup\Done` offers once, offered again here so
        that leaving it blank in the wizard is a deferral rather than
        `FindBusiness::skip()`'s kind of door that never opens again.

        UNCHECKED BY DEFAULT, WITH THE FULL DISCLOSURE, ONLY WHEN THERE IS NO
        LIVE PERMIT ALREADY OR THE OWNER HAS ASKED TO CHANGE THE NUMBER — a
        business that has consented and is not editing sees a plain statement
        and a way to stop, never the form uninvited, on the same "ask for no
        reason" rule the Pause control above states for itself.

        ⚠️ "CHANGE NUMBER" IS THE MISTYPED-DIGIT CORRECTION (10660). Before
        this control existed, correcting a typo meant pressing "Stop texting
        me" first — the more visible path, and the only one, for a problem
        that has nothing to do with wanting to stop.
    --}}
    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Texts about your own account</h2>

        @if ($ownerNotifyPermit && ! $editingOwnerMobile)
            <p class="mt-2 text-base text-ink-2">
                We will text you at that number when something needs you.
            </p>

            <div class="mt-4 flex flex-wrap gap-3">
                <x-ui.button wire:click="stopOwnerNotify" type="button" size="default" variant="secondary">
                    Stop texting me
                </x-ui.button>

                <button
                    type="button"
                    wire:click="editOwnerMobile"
                    class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
                >
                    Change number
                </button>
            </div>
        @else
            <p class="mt-2 text-base text-ink-2">
                {{ $ownerNotifyDisclosure }}
            </p>

            <form wire:submit="saveOwnerNotify" class="mt-4 space-y-3">
                <div>
                    <label for="account-owner-mobile" class="block text-sm font-medium text-ink">Your mobile number</label>
                    <input
                        type="tel"
                        id="account-owner-mobile"
                        wire:model="ownerMobile"
                        autocomplete="tel"
                        placeholder="(555) 123-4567"
                        class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                    >
                    @error('ownerMobile')
                        <p class="mt-1 text-sm text-ink">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        wire:model="ownerConsent"
                        class="mt-1 size-5 rounded border-rule-strong text-ink"
                    >
                    <span class="text-base text-ink">
                        Text me at this number about my own account.
                    </span>
                </label>
                @error('ownerConsent')
                    <p class="text-sm text-ink">{{ $message }}</p>
                @enderror

                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.submit size="default" target="saveOwnerNotify" busy="Saving…">Text me</x-ui.submit>

                    @if ($ownerNotifyPermit)
                        <button
                            type="button"
                            wire:click="cancelEditOwnerMobile"
                            class="min-h-11 rounded-[--radius-control] px-4 text-base text-ink-2 hover:text-ink"
                        >
                            Cancel
                        </button>
                    @endif
                </div>
            </form>
        @endif
    </div>

    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Alerts on this browser</h2>
        @if (! $pushConfigured)
            <p>Browser alerts are not set up on this server yet.</p>
        @else
            <p>{{ $pushDevices > 0 ? 'This browser has alerts turned on.' : 'Turn on alerts to get a notification here when something needs you.' }}</p>

            <div x-data="pushEnable(@js($pushPublicKey))">
                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="button" x-on:click="enablePush" class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink">Turn on alerts in this browser</button>
                </div>
                <p x-show="errorMessage" x-text="errorMessage" class="mt-2 text-sm text-red-500" style="display: none;"></p>
            </div>

            @if ($pushDevices > 0)
                <form wire:submit="sendTestAlert" class="mt-4">
                    <x-ui.submit size="default" target="sendTestAlert" busy="Sending…">Send a test alert</x-ui.submit>
                </form>
            @endif
        @endif
    </div>

    {{--
        DOWNLOAD MY DATA (`28` §3.7). A control, not navigation — pressing it
        starts a build, which is what earns it a place on this "controls only"
        screen; see the closing comment below.

        ⚠️ NEVER GATED ON `$paused`. The button and the service behind it both
        work identically whether or not the account is paused — exit friction
        is a churn strategy this product does not use.

        ⚠️ A PLAIN FORM POST, NOT `wire:click` (1900). A Livewire action posts to
        `livewire.update`, which SuspendedTenantStatus cannot exempt without
        exempting every action on every screen — so a *suspended* owner could
        never press this. The same form is on the on-hold page, which is the
        screen a suspended owner is actually looking at.
    --}}
    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Download my data</h2>

        <p class="mt-2 text-base text-ink-2">
            Everything we have on file for your account — contacts, reviews, and
            messages we sent — in one ZIP file.
        </p>

        @if ($latestExport)
            <p class="mt-3 text-sm text-ink-2">
                @if ($exportDownloadUrl)
                    Ready — built {{ $latestExport->built_at?->diffForHumans() }}. This
                    link stops working {{ $latestExport->expires_at?->diffForHumans() }}.
                @elseif ($latestExport->status->value === 'failed')
                    The last attempt could not be built. Try again below.
                @else
                    Building your last request now — this takes a few minutes.
                @endif
            </p>
        @endif

        <div class="mt-4 flex flex-wrap items-center gap-3">
            @if ($exportDownloadUrl)
                <x-ui.button :href="$exportDownloadUrl" size="default">Download my data</x-ui.button>
            @endif

            <form method="POST" action="{{ route('account.data-export.request') }}">
                @csrf
                <x-ui.button
                    type="submit"
                    size="default"
                    :variant="$exportDownloadUrl ? 'secondary' : 'primary'"
                >
                    {{ $exportDownloadUrl ? 'Build a new download' : 'Download my data' }}
                </x-ui.button>
            </form>
        </div>
    </div>

    {{--
        ⚠️ TWO DOOR PANELS STOOD HERE AND THE SHELL IS WHERE THEIR REASON WENT.
        One carried Import (*"`ImportCustomers` shipping with no link would be
        decision 272's shape inside the slice built to close decision 272's
        shape"*) and one carried the message log (*"`outreach_messages` had been
        collecting rows with no reader at all — this link is the whole point of
        the slice rather than navigation polish"*). Both arguments were right and
        both are answered better by an entry in `OwnerNav`, which puts the door on
        every screen instead of on whichever one was built when the need arose.

        ⚠️ THE LINE THIS PAGE NOW HOLDS IS CONTROLS ONLY, and the two things above
        are what make it worth stating rather than obvious. Pause Everything and
        the review rules are both **controls** — an owner changes something here
        and it is changed. A panel whose whole content is a heading, a sentence
        and a link is **navigation**, and navigation on a settings screen is the
        drift that turns it into a second nav surface: the seventh linking
        convention this slice exists to prevent. Add a control here; add a
        destination to `OwnerNav`.
    --}}

    {{-- Advanced Dashboard Opt-in (Doc 28 Part 1 §1.2) --}}
    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Power & Advanced tools</h2>
        <p class="mt-2 text-base text-ink-2">
            @if ($advancedEnabled)
                Advanced tools are currently active. You have access to manual broadcasts, directory citations, competitor tracking, and deep SEO diagnostics.
            @else
                Want more control? Turn on Advanced tools to access directory citation tracking, manual broadcast campaigns, competitor signals, and SEO audit tools.
            @endif
        </p>

        <div class="mt-4 flex items-center gap-3">
            <button
                type="button"
                wire:click="toggleAdvanced"
                class="inline-flex items-center px-4 py-2 border border-rule dark:border-gray-700 rounded-md shadow-sm text-sm font-medium {{ $advancedEnabled ? 'text-rose-700 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-300' : 'text-indigo-700 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-300' }}"
            >
                {{ $advancedEnabled ? 'Turn off Advanced tools' : 'Turn on Advanced tools' }}
            </button>
            @if ($advancedEnabled)
                <a href="{{ route('advanced.home') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                    Open Advanced Dashboard &rarr;
                </a>
            @endif
        </div>
    </div>
</div>

@script
<script>
    Alpine.data('pushEnable', (publicKey) => ({
        errorMessage: '',
        async enablePush() {
            this.errorMessage = '';
            try {
                const registration = await navigator.serviceWorker.register('/push-sw.js');
                const permission = await Notification.requestPermission();
                
                if (permission !== 'granted') {
                    this.errorMessage = 'Your browser blocked alerts for this site. Allow them in the browser\'s site settings, then try again.';
                    return;
                }

                const rawData = window.atob(publicKey.replace(/-/g, '+').replace(/_/g, '/'));
                const applicationServerKey = new Uint8Array(rawData.length);
                for (let i = 0; i < rawData.length; ++i) {
                    applicationServerKey[i] = rawData.charCodeAt(i);
                }

                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: applicationServerKey
                });

                $wire.savePushSubscription(subscription.toJSON());
            } catch (e) {
                this.errorMessage = e.message || 'Failed to turn on alerts.';
            }
        }
    }));
</script>
@endscript
