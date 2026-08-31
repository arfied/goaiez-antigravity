<div class="space-y-8">
    <header>
        <h1 class="font-display text-2xl font-semibold text-ink">How people find you</h1>
        <p class="mt-2 text-base text-ink-2">
            What we can measure from your own data — never a ranking promise.
        </p>
    </header>

    {{--
        ⚠️ THIS REPLACES THE SUPPRESSION SENTENCE (3060–3079). It used to read
        "we will show them per location once you can choose which one you are
        looking at" — a promise this slice keeps. Every figure below is
        per-location, so the picker sits above all of them and the whole report
        moves together; nothing on this page is business-wide for it to be
        confused with.
    --}}
    <x-account.location-picker :locations="$locationOptions" :selected="$selectedLocation" />

    @if ($location === null)
        <p class="text-base text-ink-2">Add a location to see how people find you.</p>
    @elseif ($report === null)
        <p class="text-base text-ink-2">Nothing to show yet.</p>
    @else
        <section class="space-y-3" aria-labelledby="search-heading">
            <h2 id="search-heading" class="font-display text-lg font-semibold text-ink">Google Search</h2>
            {{--
                ⛔ THE REASON IS RENDERED, NOT THE STATE (9820–9839). This
                @switch had one arm for `unavailable` — "Search numbers are
                temporarily unavailable." — over a reason field carrying five
                different remedies, and one arm for `no_data_yet` saying "Google
                Search has nothing to report for this period yet" over a
                population that included our own unrun sync. `VisibilityReading`'s
                own docblock had promised "each maps to a different sentence and
                a different remedy" since the day it shipped; this is where that
                becomes true.

                ⚠️ The reason's `sentence()` is a `match` with no default, so a
                new case is a fatal in a test rather than a silent fall-through
                to a vaguer sentence here.
            --}}
            @if ($sentence = $report->searchSentence())
                <p class="text-base text-ink">{{ $sentence }}</p>
            @elseif ($report->search->reason !== null)
                <p class="text-base text-ink-2">{{ $report->search->reason->sentence() }}</p>
            @else
                <p class="text-base text-ink-2">
                    @switch($report->search->state->value)
                        @case('not_connected')
                            Connect Google Search Console under Google reviews to see search movement.
                            @break
                        @case('no_property_chosen')
                            Choose which Search Console property belongs to this location.
                            @break
                        @case('no_data_yet')
                            Google Search has nothing to report for this period yet.
                            @break
                        @default
                            We cannot compare these two periods yet.
                    @endswitch
                </p>
            @endif
        </section>

        <section class="space-y-3" aria-labelledby="maps-heading">
            <h2 id="maps-heading" class="font-display text-lg font-semibold text-ink">Google Maps</h2>
            <p class="text-base text-ink-2">{{ $report->mapsUnavailableSentence() }}</p>
        </section>

        <section class="space-y-3" aria-labelledby="catchment-heading">
            <h2 id="catchment-heading" class="font-display text-lg font-semibold text-ink">Where visitors come from</h2>
            <p class="text-base text-ink-2">{{ $report->catchmentUnavailableSentence() }}</p>
        </section>

        <section class="space-y-3" aria-labelledby="competitors-heading">
            <h2 id="competitors-heading" class="font-display text-lg font-semibold text-ink">Nearby businesses</h2>
            {{--
                ⛔ "We have not found nearby businesses to compare yet" IS NOW
                ONLY REACHED FROM A CHECK THAT ASKED GOOGLE (9820–9839). It used
                to answer for a spent Places budget, a sync that had never run
                and a kill switch as well, which is our own cost cap wearing a
                claim about somebody's high street. Everything with a cause
                carries a reason and renders its own sentence.
            --}}
            @if ($report->competitors->isMeasured())
                <p class="text-base text-ink">{{ $report->competitors->sentence() }}</p>
            @elseif ($absence = $report->competitors->absenceSentence())
                <p class="text-base text-ink-2">{{ $absence }}</p>
            @else
                <p class="text-base text-ink-2">
                    @switch($report->competitors->state->value)
                        @case('no_place_id')
                            Confirm your Google listing to compare with nearby businesses.
                            @break
                        @case('no_neighbours')
                            We have not found nearby businesses to compare yet.
                            @break
                        @default
                            A nearby comparison is temporarily unavailable.
                    @endswitch
                </p>
            @endif
        </section>
    @endif
</div>
