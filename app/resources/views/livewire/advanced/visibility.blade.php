<div class="space-y-6 sm:space-y-8">
    <div class="mb-8">
        <nav class="flex mb-2" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                <li class="text-ink-2">/</li>
                <li class="text-ink-2">Search Engine Visibility</li>
            </ol>
        </nav>
        <h1 class="text-2xl font-bold text-ink sm:text-3xl">Search Engine Visibility <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-attention-bg text-attention">Preview — not live</span></h1>
        <p class="mt-1 text-sm text-ink-2">Deep telemetry from Google Search Console and local map pack rankings.</p>
    </div>

    @if($rows->isEmpty())
        <p class="text-base text-ink-2">Add a location to measure its visibility.</p>
    @else
        <div class="space-y-12">
            @foreach($rows as $row)
                @php
                    $location = $row['location'];
                    $report = $row['report'];
                @endphp
                <div class="bg-card shadow rounded-lg border border-rule p-6">
                    <h2 class="text-xl font-semibold text-ink mb-6">{{ $location->name }}</h2>

                    <div class="space-y-8">
                        <section class="space-y-3" aria-labelledby="search-heading-{{ $location->id }}">
                            <h3 id="search-heading-{{ $location->id }}" class="font-display text-lg font-semibold text-ink">Google Search</h3>
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

                        <section class="space-y-3" aria-labelledby="queries-heading-{{ $location->id }}">
                            <h3 id="queries-heading-{{ $location->id }}" class="font-display text-lg font-semibold text-ink">Top search queries</h3>
                            <p class="text-base text-ink-2">Search queries appear once Google Search Console is connected.</p>
                        </section>

                        <section class="space-y-3" aria-labelledby="maps-heading-{{ $location->id }}">
                            <h3 id="maps-heading-{{ $location->id }}" class="font-display text-lg font-semibold text-ink">Google Maps</h3>
                            <p class="text-base text-ink-2">{{ $report->mapsUnavailableSentence() }}</p>
                        </section>

                        <section class="space-y-3" aria-labelledby="catchment-heading-{{ $location->id }}">
                            <h3 id="catchment-heading-{{ $location->id }}" class="font-display text-lg font-semibold text-ink">Where visitors come from</h3>
                            <p class="text-base text-ink-2">{{ $report->catchmentUnavailableSentence() }}</p>
                        </section>

                        <section class="space-y-3" aria-labelledby="competitors-heading-{{ $location->id }}">
                            <h3 id="competitors-heading-{{ $location->id }}" class="font-display text-lg font-semibold text-ink">Nearby businesses</h3>
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
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
