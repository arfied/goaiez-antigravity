<div class="space-y-6 sm:space-y-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                    <li class="text-ink-2">/</li>
                    <li class="text-ink-2">Broadcasts</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">Marketing & Re-engagement Broadcasts <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-attention-bg text-attention">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-ink-2">Send compliant, 10DLC-registered SMS and email announcements to segmented customer lists.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <a href="{{ route('advanced.broadcasts.compose') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Broadcast
            </a>
        </div>
    </div>

    <!-- Metrics Bar -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 mb-8">
        <div class="bg-card p-5 rounded-card border border-rule shadow-card">
            <div class="text-sm font-medium text-ink-2">Campaigns This Month</div>
            <div class="mt-1 text-3xl font-bold text-ink">{{ $campaignsThisMonth }}</div>
        </div>
        <div class="bg-card p-5 rounded-card border border-rule shadow-card">
            <div class="text-sm font-medium text-ink-2">Recipients Enrolled</div>
            <div class="mt-1 text-3xl font-bold text-ink">{{ $recipientsEnrolled }}</div>
        </div>
    </div>

    <!-- Campaigns List -->
    <div class="bg-card shadow-card rounded-card border border-rule overflow-x-auto overflow-y-hidden">
        <div class="px-6 py-4 border-b border-rule flex justify-between items-center">
            <h2 class="text-lg font-semibold text-ink">Recent Broadcasts</h2>
            <span class="text-xs text-ink-2">Updated automatically</span>
        </div>
        @if($campaigns->isEmpty())
            <div class="p-6 text-center text-sm text-ink-2">
                No broadcasts yet. Your first campaign appears here after it is drafted.
            </div>
        @else
            @if (session('status')) <p>{{ session('status') }}</p> @endif
            @error('confirm') <p>{{ $message }}</p> @enderror
            <div class="overflow-x-auto" tabindex="0" aria-label="Recent broadcasts table">
                <table class="min-w-full divide-y divide-rule">
                    <thead class="bg-paper">
                        <tr>
                            <th class="px-6 py-3 whitespace-nowrap text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Campaign Name</th>
                            <th class="px-6 py-3 whitespace-nowrap text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 whitespace-nowrap text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Recipients</th>
                            <th class="px-6 py-3 whitespace-nowrap text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Replies</th>
                            <th class="px-6 py-3 whitespace-nowrap text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 whitespace-nowrap text-left text-xs font-medium text-ink-3 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-rule text-sm">
                        @foreach($campaigns as $campaign)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-ink">{{ $campaign->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap"><span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-ok-bg text-ok">{{ $campaign->status->value }}</span></td>
                                <td class="px-6 py-4 whitespace-nowrap text-ink-2">{{ $campaign->recipients_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-ink-2">{{ $campaign->replies_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-ink-2">{{ $campaign->started_at?->format('M j, Y') ?? $campaign->created_at->format('M j, Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($campaign->status === \App\Enums\CampaignStatus::Draft)
                                        <button type="button" wire:click="confirm({{ $campaign->id }})">Confirm and send</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p>Confirming enrols the dormant audience; messages go out on the next scheduled run, every fifteen minutes.</p>
        @endif
    </div>
</div>
