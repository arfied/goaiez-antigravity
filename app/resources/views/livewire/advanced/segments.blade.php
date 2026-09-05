<div class="space-y-6 sm:space-y-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                    <li class="text-ink-2">/</li>
                    <li class="text-ink-2">Customer Segments</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">Smart Customer Segments & Audiences <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-attention-bg text-attention">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-ink-2">Dynamic groups computed from customer interactions, review status, feedback sentiment, and cadence recency.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                + Create Custom Segment
            </button>
        </div>
    </div>

    <!-- Segments Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="bg-card border border-rule rounded-card shadow-card p-6 flex flex-col justify-between">
            <div>
                <div class="flex justify-between items-center mb-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-paper text-ink-2 border border-rule whitespace-nowrap">High Review Propensity</span>
                    <span class="text-xs text-ink-2">Dynamic</span>
                </div>
                <h3 class="text-lg font-bold text-ink">Recent Satisfied Visitors</h3>
                <p class="text-xs text-ink-2 mt-1">Customers with completed service in the last 14 days with zero complaints.</p>
            </div>
            <div class="mt-6 pt-4 border-t border-rule flex justify-between items-center">
                <div class="text-2xl font-bold text-ink">86 <span class="text-xs text-ink-2 font-normal">contacts</span></div>
                <a href="{{ route('advanced.broadcasts.compose') }}" class="text-xs font-semibold text-ink hover:underline p-2 min-h-[40px] inline-flex items-center">Message Segment &rarr;</a>
            </div>
        </div>

        <div class="bg-card border border-rule rounded-card shadow-card p-6 flex flex-col justify-between">
            <div>
                <div class="flex justify-between items-center mb-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-paper text-ink-2 border border-rule whitespace-nowrap">VIP Advocates</span>
                    <span class="text-xs text-ink-2">Dynamic</span>
                </div>
                <h3 class="text-lg font-bold text-ink">5-Star Google Reviewers</h3>
                <p class="text-xs text-ink-2 mt-1">Confirmed 5-star public reviewers eligible for loyalty rewards and referral programs.</p>
            </div>
            <div class="mt-6 pt-4 border-t border-rule flex justify-between items-center">
                <div class="text-2xl font-bold text-ink">142 <span class="text-xs text-ink-2 font-normal">contacts</span></div>
                <a href="{{ route('advanced.broadcasts.compose') }}" class="text-xs font-semibold text-ink hover:underline p-2 min-h-[40px] inline-flex items-center">Message Segment &rarr;</a>
            </div>
        </div>

        <div class="bg-card border border-rule rounded-card shadow-card p-6 flex flex-col justify-between">
            <div>
                <div class="flex justify-between items-center mb-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-paper text-ink-2 border border-rule whitespace-nowrap">Re-engagement</span>
                    <span class="text-xs text-ink-2">Dynamic</span>
                </div>
                <h3 class="text-lg font-bold text-ink">Lapsed Customers (60+ Days)</h3>
                <p class="text-xs text-ink-2 mt-1">Prior clients who have not visited recently. Perfect for seasonal win-back offers.</p>
            </div>
            <div class="mt-6 pt-4 border-t border-rule flex justify-between items-center">
                <div class="text-2xl font-bold text-ink">112 <span class="text-xs text-ink-2 font-normal">contacts</span></div>
                <a href="{{ route('advanced.broadcasts.compose') }}" class="text-xs font-semibold text-ink hover:underline p-2 min-h-[40px] inline-flex items-center">Message Segment &rarr;</a>
            </div>
        </div>
    </div>
</div>