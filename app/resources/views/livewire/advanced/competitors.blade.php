<div class="space-y-6 sm:space-y-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                    <li class="text-ink-2">/</li>
                    <li class="text-ink-2">Competitors</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">Local Competitor Intelligence Radar <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-attention-bg text-attention">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-ink-2">Track competitor review velocity, rating momentum, and search rankings in your catchment area.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                + Track New Competitor
            </button>
        </div>
    </div>

    @if($rows->isEmpty())
        <div class="bg-card shadow-card rounded-card border border-rule p-8 text-center">
            <p class="text-ink-2">Add a location to compare against nearby competitors.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($rows as $row)
                <div class="bg-card shadow-card rounded-card border border-rule p-6">
                    <h2 class="text-lg font-bold text-ink mb-2">{{ $row->location->name }}</h2>
                    <p class="text-ink-2">
                        @if($row->comparison->isMeasured())
                            {{ $row->comparison->sentence() }}
                        @else
                            {{ $row->comparison->absenceSentence() ?? 'Not measured yet — competitor signals refresh on their schedule.' }}
                        @endif
                    </p>
                </div>
            @endforeach
        </div>
    @endif
</div>
