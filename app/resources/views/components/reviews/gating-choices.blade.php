@props(['model' => 'rating', 'legend' => 'Your choice'])

{{--
    The five answers of decision 1186, shared by the wizard step and the owner's
    settings screen.

    ⚠️ OUTCOME LANGUAGE, NO INTERNAL VOCABULARY (`22`). The owner never sees
    "threshold" or "invite_threshold" as configuration — they see who gets asked.
    "Review gating" appears exactly once, in the disclosure, because it is the
    name of the practice being disclosed rather than a name for a setting.

    ⚠️ THE FIRST OPTION IS NOT A RATING OF 1, AND THE LABEL SAYS SO IN WORDS.
    Decision 1187 warns that the range spans "no gating at all" to "the strictest
    gating there is" with no signal either end differs in kind. It does differ:
    the first answer writes no acknowledgement and gates nobody
    (`ReviewGating::inviteEveryone()`), and every other answer records a
    compliance position. Naming it "Ask everyone" rather than "1 star and above"
    is what carries that difference to the person choosing.

    ⚠️ AND THE LAST OPTION IS NOT SINGLED OUT AS DANGEROUS HERE. It is the most
    exposed answer under Google's policy and the disclosure above says so; a
    warning attached to one radio and not the others would read as a
    recommendation of the rest.
--}}

<fieldset>
    <legend class="text-lg font-medium">{{ $legend }}</legend>

    <div class="mt-3 space-y-3">
        @foreach (\App\Services\Reviews\ReviewGating::choices() as $rating)
            <label class="flex gap-3 rounded-[--radius-card] border border-rule p-4">
                <input
                    type="radio"
                    wire:model.live="{{ $model }}"
                    value="{{ $rating }}"
                    class="mt-1"
                >
                <span>
                    <span class="block text-base font-medium">
                        @if ($rating === \App\Services\Reviews\ReviewGating::EVERYONE)
                            Ask everyone
                        @elseif ($rating === \App\Services\Reviews\ReviewGating::MAX_THRESHOLD)
                            Ask only customers who rated us 5 stars
                        @else
                            Ask customers who rated us {{ $rating }} stars or better
                        @endif
                    </span>
                    <span class="block text-base">
                        @if ($rating === \App\Services\Reviews\ReviewGating::EVERYONE)
                            Every customer who leaves feedback is invited to review you publicly.
                        @else
                            Everyone else goes straight to your inbox instead.
                        @endif
                    </span>
                </span>
            </label>
        @endforeach
    </div>

    @error($model) <p class="mt-2 text-base text-alert">{{ $message }}</p> @enderror
</fieldset>
