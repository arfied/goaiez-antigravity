<div class="space-y-6 sm:space-y-8">
    <div class="mb-8">
        <nav class="flex mb-2" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                <li class="text-ink-2">/</li>
                <li class="text-ink-2">Reputation Defense</li>
            </ol>
        </nav>
        <h1 class="text-2xl font-bold text-ink sm:text-3xl">Reputation defense</h1>
        <p class="mt-1 text-sm text-ink-2">Feedback rated below {{ $minPublicStars }} stars stays private and opens a conversation; {{ $minPublicStars }} stars and above is invited to Google.</p>
    </div>

    <div class="bg-card border border-rule rounded-card shadow-card p-6">
        <h2 class="text-lg font-semibold text-ink mb-4">Last 90 days</h2>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="p-4 bg-paper border border-rule rounded-lg text-center">
                <div class="text-2xl font-bold text-ink">{{ $feedbackReceived }}</div>
                <div class="text-xs text-ink-2 mt-1">Feedback received</div>
            </div>
            <div class="p-4 bg-paper border border-rule rounded-lg text-center">
                <div class="text-2xl font-bold text-ink">{{ $keptPrivate }}</div>
                <div class="text-xs text-ink-2 mt-1">Kept private</div>
            </div>
            <div class="p-4 bg-paper border border-rule rounded-lg text-center">
                <div class="text-2xl font-bold text-ink">{{ $invitedToGoogle }}</div>
                <div class="text-xs text-ink-2 mt-1">Invited to Google</div>
            </div>
            <div class="p-4 bg-paper border border-rule rounded-lg text-center">
                <div class="text-2xl font-bold text-ink">{{ $removalRequests }}</div>
                <div class="text-xs text-ink-2 mt-1">Removal requests</div>
            </div>
        </div>
    </div>
</div>