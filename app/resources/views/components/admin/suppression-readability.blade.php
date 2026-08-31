@props(['registers'])

@php
    use App\Enums\SignalState;
    use App\Services\Consent\SuppressionReadability;

    /** @var SuppressionReadability $registers */
    $readable = $registers->isReadable();
@endphp

{{--
    ⛔ WHETHER THIS PLATFORM IS REFUSING EVERY SEND — decision 9449(c), and the
    reason it is a shared component rather than two panels.

    `ConsentService::decide()` returns `SuppressionUnreadable` above the
    suppression lookup and above the purpose branch whenever
    `IdentifierHashEpochs::isReadable()` is false, so in that state every send on
    every channel for every tenant is refused — the review invites and the
    missed-call text-backs included. Until this component the only thing that
    said so was a console command, and `OperatorAlertKind` said so itself: the
    remedy was "a command nobody has run".

    NOTHING HERE IS RE-TYPED. The state comes from `isReadable()`, the heading
    from `sendingHeadline()` and the body from `operatorSentence()` — the same
    method the command prints. A screen that restated the rule in its own words
    would be a second copy of a compliance refusal, agreeing until one of them
    moved.

    AND IT TAKES ONE VALUE RATHER THAN THREE (9645). The first draft took the
    predicate and the two sentences as separate props, and a mutation showed a
    caller could pass a readable predicate beside a headline saying every send
    was refused — an operator told the platform had stopped, with the remedy
    withheld, and every test green. `SuppressionReadability` reads all three off
    one status, so nothing a screen can hand this component is inconsistent.

    TWO SCREENS, ONE BODY, AND THE FRAMING SENTENCE IS THE SLOT. Decision 9446's
    rule: the operator standing at Credentials is asking "what did my APP_KEY
    reach" and the one standing at Stop and start sending is asking "is anything
    going out". The answer is the same fact and the sentence around it is not,
    so the fact is derived once here and each screen supplies its own framing.

    THE ORDER OF THE TWO REMEDIES INSIDE `$sentence` IS LOAD-BEARING (9444):
    previous APP_KEY first, `--accept-loss` second and described as permanent.
    Nothing here may clamp, truncate, line-clamp or excerpt it — 8277 records
    what a silent 300-character cut did to exactly this text, and it left an
    operator holding half of "put the previous APP_KEY back". It is rendered
    whole, and `SendingRefusalSurfaceTest` pins the first option, the middle of
    it and the last clause of the longest arm.

    COLOUR IS NOT THE SIGNAL (`22`). The pill carries an icon and a word, the
    headline is a sentence, and the body is a paragraph. Nothing here is legible
    by hue alone.

    THE READABLE ARMS SAY "not for this reason" AND NEVER "sending is fine".
    This component knows one thing — whether the stored suppression hashes can
    be read. It knows nothing about the halt switches, a tenant pause, an
    account pause or a spent balance, and a green sentence covering those would
    be a status light over a question it never asked.
--}}

<section class="rounded-[--radius-panel] border border-rule bg-card p-5">
    <div class="flex flex-wrap items-center gap-3">
        <x-ui.status-pill
            :state="$readable ? SignalState::Ok : SignalState::Alert"
            :label="$readable ? 'Not stopped by this' : 'Nothing is going out'"
        />
        <h2 class="font-display text-lg font-semibold text-ink">Whether anything is being sent at all</h2>
    </div>

    <p @class(['mt-3 text-base', 'text-ink-2' => $readable, 'font-semibold text-ink' => ! $readable])>{{ $registers->headline }}</p>

    @if (trim($slot) !== '')
        <p class="mt-2 text-base text-ink-2">{{ $slot }}</p>
    @endif

    @unless ($readable)
        {{--
            The console page, whole and unclamped. A <pre> because the two
            numbered options and the commands under them are the layout that
            makes the safe move findable, and because an operator retypes those
            commands under pressure.
        --}}
        <pre class="mt-4 overflow-x-auto whitespace-pre-wrap break-words rounded-[--radius-control] border border-rule bg-paper p-4 font-mono text-base leading-relaxed text-ink-2">{{ $registers->sentence }}</pre>
    @endunless
</section>
