<div class="space-y-6 sm:space-y-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                    <li class="text-ink-2">/</li>
                    <li class="text-ink-2">Reports</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">Reports</h1>
        </div>
    </div>

    <div>
        <h2 class="text-lg font-medium text-ink mb-4">This month</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div class="bg-surface p-4 rounded-lg shadow border border-border">
                <div class="text-sm font-medium text-ink-2">Reviews received</div>
                <div class="mt-1 text-2xl font-semibold text-ink">{{ $reviewsThisMonth }}</div>
            </div>

            <div class="bg-surface p-4 rounded-lg shadow border border-border">
                <div class="text-sm font-medium text-ink-2">Review asks sent</div>
                <div class="mt-1 text-2xl font-semibold text-ink">{{ $asksThisMonth }}</div>
            </div>

            <a href="{{ route('advanced.voice') }}" class="block bg-surface p-4 rounded-lg shadow border border-border hover:bg-surface-hover">
                <div class="text-sm font-medium text-ink-2">Calls</div>
                <div class="mt-1 text-2xl font-semibold text-ink">{{ $callsThisMonth }}</div>
            </a>

            <a href="{{ route('advanced.broadcasts') }}" class="block bg-surface p-4 rounded-lg shadow border border-border hover:bg-surface-hover">
                <div class="text-sm font-medium text-ink-2">Broadcasts drafted</div>
                <div class="mt-1 text-2xl font-semibold text-ink">{{ $campaignsThisMonth }}</div>
            </a>
        </div>
        <p class="mt-4 text-sm text-ink-2">Counts are since the first of this month.</p>
    </div>

    <div>
        <h2 class="text-lg font-medium text-ink mb-4">Ad conversions</h2>
        <livewire:x-139.adaccount-connect-card :business-id="\App\Support\Tenancy::id()" />
        <livewire:x-139.conversions-pushed-tile :business-id="\App\Support\Tenancy::id()" />
        <livewire:x-139.rejection-rate :business-id="\App\Support\Tenancy::id()" />
    </div>
</div>
