{{--
    "Did we get that sorted?" — T546 §37.3(1), wave 38 lane C (10590–10609).

    ⚠️ NO TENANT DATA AND NO PAGE TITLE, ON `content/hold-confirmed.blade.php`'s
    OWN ARGUMENT. Whoever fetched this URL proved only that they hold a
    signature; a corporate mail scanner renders `show()` too, and `show()` is
    the only action a scanner can reach — the answer is a POST, never a GET.

    ⚠️ TWO BUTTONS, TWO FORMS, NEITHER PRE-SELECTED. Colour is never the sole
    indicator (`22`) and there is no default answer for a scanner or an
    accidental tap to fall into — a customer who does not press either button
    has told this platform nothing, which is exactly what "silence has no
    inferred answer" requires downstream.

    ⚠️ OUTCOME LANGUAGE THROUGHOUT (`22`). The buttons name what the customer is
    telling the business, never a system verb like "submit" or "confirm status".

    ⚠️ `noindex`, on the same signed-URL reasoning as `hold-confirmed`.
--}}
<x-marketing.layout title="Did we get that sorted?" :noindex="true"
    description="Let us know whether your problem was fixed.">
    <div class="mx-auto max-w-xl px-6 py-24 text-center">
        @if ($alreadyAnswered)
            <h1 class="font-display text-3xl font-semibold tracking-tight">Thanks for letting us know</h1>

            @if (isset($answer) && $answer === \App\Enums\FixThenAskResponse::Confirmed)
                <p class="mt-4 text-ink-2">
                    We've told the business you said this is sorted. If they're able to, they'll ask
                    whether you'd be willing to share that publicly.
                </p>
            @elseif (isset($answer))
                <p class="mt-4 text-ink-2">
                    We've let the business know this isn't resolved yet, so they can follow up with you.
                </p>
            @else
                <p class="mt-4 text-ink-2">You've already answered this one.</p>
            @endif

            <p class="mt-4 text-ink-2">You can close this page.</p>
        @else
            <h1 class="font-display text-3xl font-semibold tracking-tight">Did we get that sorted?</h1>

            <p class="mt-4 text-ink-2">
                A little while ago you told a business about a problem, and they believe it's fixed.
                Did they get it sorted for you?
            </p>

            <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:justify-center">
                <form method="POST" action="{{ $formAction }}">
                    @csrf
                    <input type="hidden" name="answer" value="confirmed">
                    <button type="submit"
                        class="w-full rounded-lg bg-action px-6 py-3 text-base font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 sm:w-auto">
                        Yes, that's sorted
                    </button>
                </form>

                <form method="POST" action="{{ $formAction }}">
                    @csrf
                    <input type="hidden" name="answer" value="not_resolved">
                    <button type="submit"
                        class="w-full rounded-lg border border-ink-3 px-6 py-3 text-base font-semibold text-ink-1 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 sm:w-auto">
                        No, not yet
                    </button>
                </form>
            </div>

            <p class="mt-8 text-ink-2">If you'd rather not say, that's completely fine — you can just close this page.</p>
        @endif
    </div>
</x-marketing.layout>
