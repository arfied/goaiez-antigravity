{{--
    The persistent impersonation banner (`28` §9.4).

    ⚠️ INJECTED INTO THE RESPONSE BY `Impersonating`, NOT INCLUDED BY A LAYOUT.
    `28` requires it on *every* impersonated page, and this application already
    has four separate layouts plus Livewire's own — so a per-layout include is
    one new layout away from a page where an agent cannot tell whose account
    they are in, and nothing would fail. Injection makes the banner a property
    of the session rather than of the template.

    ⚠️ TOKENS, NOT INLINE HEX, AND THAT IS A DELIBERATE TRADE. Injecting into
    arbitrary pages argues for inline styles, because a framework error page
    does not load our stylesheet and would render this unstyled. It is written
    in tokens anyway: `ComponentLibraryTest`'s reasoning — a literal hex looks
    right in light mode and is wrong in dark, and nobody notices until a
    customer does — applies to every *real* page this appears on, which is all
    of them but the error pages. Unstyled, this degrades to a readable line of
    text with a working End session button at the foot of the page. A banner
    that is legible everywhere and wrong-coloured on half of them is the worse
    of the two failures.

    COLOUR IS NOT THE SIGNAL (`22`). The mode is written out in words, so the
    banner says what it means in monochrome, to a screen reader, and to somebody
    who cannot tell the two backgrounds apart.
--}}
<div
    role="status"
    aria-live="polite"
    class="fixed inset-x-0 bottom-0 z-[9999] flex flex-wrap items-center gap-x-4 gap-y-2
           border-t-4 px-4 py-3 text-base
           {{ $isAct
                ? 'border-alert bg-attention-bg text-ink'
                : 'border-rule-strong bg-card text-ink' }}"
>
    <strong class="font-semibold">
        {{-- Not an icon alone: the word carries the meaning. --}}
        <span aria-hidden="true">{{ $isAct ? '⚠' : '👁' }}</span>
        {{ $isAct ? 'Making changes' : 'Viewing only' }}
    </strong>

    <span>
        You are in <strong class="font-semibold">{{ $businessName }}</strong>’s account as
        <strong class="font-semibold">{{ $agentName }}</strong>.
    </span>

    <span class="text-ink-2">
        {{ $reason }}@if ($ticketRef) · {{ $ticketRef }} @endif
    </span>

    <span class="text-ink-2">Ends {{ $expiresAt }}</span>

    {{--
        A form, not a link. Ending a session changes state, and the one thing an
        agent must always be able to do is stop — so this route is the single
        exemption from the view-only write refusal, named in the middleware.
    --}}
    <form method="POST" action="{{ route('impersonation.stop') }}" class="ml-auto">
        @csrf
        <button
            type="submit"
            class="min-h-11 rounded-[--radius-control] border-2 border-current px-4 text-base font-semibold"
        >
            End session
        </button>
    </form>
</div>
