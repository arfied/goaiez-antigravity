@php
    use App\Enums\AuditStatus;
    use App\Enums\FindingSeverity;
    use App\Enums\SignalState;

    /** @var \App\Models\PublicAudit $audit */
    $pending = $audit->status->isPending();
    $failed = $audit->status === AuditStatus::Failed;
    $findings = collect($audit->findings);

    // Problems first, then the good news. `29` §6.2's example finding is "12
    // reviews have no reply" — the thing costing money is what the visitor came
    // for, and burying it under four green ticks wastes the one screen we get.
    $ordered = $findings
        ->sortBy(fn (array $f): int => match ($f['severity'] ?? '') {
            FindingSeverity::Critical->value => 0,
            FindingSeverity::Attention->value => 1,
            default => 2,
        })
        ->values();

    $unavailable = collect($audit->checks)->reject(fn (array $c): bool => $c['ran'] ?? true)->values();
@endphp

{{--
    THE ONE PLACE AN AUDIT RESULT IS RENDERED.

    Both entry points use this file: `/audit/{token}` inlines it server-side, and
    `/` polls it as an HTML fragment and swaps the result in. That is the whole
    reason the fragment route exists (decision 258) — the alternative was a
    JavaScript reimplementation of this markup, which would have quietly undone
    what G1 built. Decision 236 makes the gauge's arc and its printed number
    structurally unable to disagree, via pathLength="100"; a second renderer in
    JS gets that guarantee wrong the first time somebody edits one of the two.

    `data-pending` is the whole polling contract. The home page reads it off the
    root element and stops asking when it is false — so the client needs no
    knowledge of AuditStatus, and adding a fifth status does not touch any JS.
--}}

<div
    data-audit-result
    data-pending="{{ $pending ? 'true' : 'false' }}"
    data-token="{{ $audit->token }}"
    class="flex flex-col items-center gap-8"
>
    <x-ui.gauge
        :score="$audit->score"
        :label="$audit->name_snapshot ?? 'This business'"
        :sentence="$failed
            ? 'We could not complete this check. Try running it again.'
            : null"
    />

    @if ($audit->name_snapshot !== null)
        <p class="-mt-4 text-center text-base text-ink-2">
            {{-- Named so a 90-day-old shared link is still legible. --}}
            Checked: <span class="font-semibold text-ink">{{ $audit->name_snapshot }}</span>
        </p>
    @endif

    @if ($pending)
        {{--
            Honest skeletons (decision 237): the label names the check actually
            running, so the visitor knows which of four answers is outstanding
            rather than staring at a grey box.
        --}}
        <div class="grid w-full gap-3 sm:grid-cols-2">
            @foreach ($audit->checks as $check)
                @continue($check['ran'] ?? false)
                <x-ui.skeleton :label="$check['label']" :lines="2" />
            @endforeach

            @if ($audit->checks === [])
                <x-ui.skeleton label="Reading your Google listing" :lines="2" />
                <x-ui.skeleton label="Comparing nearby businesses" :lines="2" />
            @endif
        </div>
    @endif

    @if ($ordered->isNotEmpty())
        <ul class="grid w-full gap-3 sm:grid-cols-2">
            @foreach ($ordered as $finding)
                @php
                    $severity = FindingSeverity::tryFrom($finding['severity'] ?? '') ?? FindingSeverity::Attention;
                @endphp

                <li>
                    <x-ui.attention-card
                        :state="$severity->signalState()"
                        :heading="$severity->signalState()->label()"
                    >
                        {{ $finding['sentence'] ?? '' }}
                    </x-ui.attention-card>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($unavailable->isNotEmpty())
        {{--
            `29` §6.2 and BUILD-PLAN §2.5.3: a check that cannot run says so
            rather than fabricating. Decision 229 is why this is a separate block
            and not a finding — a robots refusal or a bot challenge is a fact
            about *us* failing to look, and printing it beside real findings
            would read as an accusation about working infrastructure.
        --}}
        <div class="w-full rounded-[--radius-card] border border-rule bg-paper p-4">
            <p class="text-sm font-semibold text-ink">What we could not check</p>
            <ul class="mt-2 space-y-1">
                @foreach ($unavailable as $check)
                    <li class="flex items-start gap-2 text-sm text-ink-2">
                        <span aria-hidden="true" class="text-ink-3">{{ SignalState::Unknown->icon() }}</span>
                        <span>{{ $check['label'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! $pending && ! $failed)
        <x-ui.button href="{{ route('start', ['audit' => $audit->token]) }}" variant="primary" size="giant">
            Fix these for me
        </x-ui.button>
    @endif
</div>
