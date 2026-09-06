<div class="space-y-6 sm:space-y-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                    <li class="text-ink-2">/</li>
                    <li class="text-ink-2">Executive Reports</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">Executive ROI & Reputation Reports <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-attention-bg text-attention">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-ink-2">Comprehensive review volume breakdown, customer sentiment analysis, search attribution, and downloadable audit reports.</p>
        </div>
    </div>


    <livewire:x-139.adaccount-connect-card :business-id="\App\Support\Tenancy::id()" />
    <livewire:x-139.conversions-pushed-tile :business-id="\App\Support\Tenancy::id()" />
    <livewire:x-139.rejection-rate :business-id="\App\Support\Tenancy::id()" />
</div>