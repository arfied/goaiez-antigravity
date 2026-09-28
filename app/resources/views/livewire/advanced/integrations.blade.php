<div class="space-y-6 sm:space-y-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                    <li class="text-ink-2">/</li>
                    <li class="text-ink-2">Integrations</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">Integrations</h1>
        </div>
    </div>

    <!-- Connections -->
    <div class="bg-card shadow rounded-lg p-6 border border-rule mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-ink">Connections</h2>
            <a href="{{ route('account.connections') }}" class="text-indigo-400 font-semibold hover:underline text-sm">Manage</a>
        </div>
        <div class="space-y-4">
            @forelse ($locations as $location)
                @php($connection = $connections->get($location->id))
                <div class="flex justify-between items-center py-2 border-b border-rule last:border-0">
                    <span class="text-sm font-medium text-ink">{{ $location->name }}</span>
                    @if ($connection?->isUsable())
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-ok-bg text-ok">{{ $connection->status->label() }}</span>
                    @else
                        <span class="text-sm text-ink-2">Not connected</span>
                    @endif
                </div>
            @empty
                <p class="text-sm text-ink-2">No locations found.</p>
            @endforelse
        </div>
    </div>

    <!-- Outbound webhooks -->
    <div class="bg-card shadow rounded-lg p-6 border border-rule mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-ink">Outbound webhooks</h2>
            <a href="{{ route('x-142.webhooks') }}" class="text-indigo-400 font-semibold hover:underline text-sm">Add Subscription</a>
        </div>
        @if($subscriptions->isEmpty())
            <p class="text-sm text-ink-2">No webhook subscriptions yet.</p>
        @else
            <div class="space-y-4">
                @foreach ($subscriptions as $subscription)
                    <div class="py-2 border-b border-rule last:border-0">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-ink">{{ $subscription->target_url }}</span>
                            <span class="text-xs text-ink-2">{{ $subscription->is_active ? 'Active' : 'Inactive' }}</span>
                        </div>
                        <p class="text-xs text-ink-2 mt-1">Events: {{ is_array($subscription->event_filter) ? implode(', ', $subscription->event_filter) : $subscription->event_filter }}</p>
                        <p class="text-xs text-ink-2">Created: {{ $subscription->created_at->format('M j, Y') }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Inbound endpoints -->
    <div class="bg-card shadow rounded-lg p-6 border border-rule mb-8">
        <h2 class="text-lg font-bold text-ink mb-4">Inbound endpoints</h2>
        <div class="space-y-2">
            @foreach ($inboundEndpoints as $endpoint)
                <div class="py-2 border-b border-rule last:border-0">
                    <code class="text-xs font-mono bg-paper px-2 py-1 rounded text-ink">{{ $endpoint }}</code>
                </div>
            @endforeach
        </div>
    </div>

    <!-- POS Integrations -->
    <div class="bg-card shadow rounded-lg p-6 border border-rule">
        <p class="text-sm text-ink-2">Point-of-sale and invoicing integrations are not available yet.</p>
    </div>
</div>
