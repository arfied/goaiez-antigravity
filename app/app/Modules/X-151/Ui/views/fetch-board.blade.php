<div>
    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2 border border-rule rounded-md p-1 bg-surface">
                    <x-ui.button size="default" variant="secondary" wire:click="setTab('all')" class="{{ $tab === 'all' ? 'bg-paper shadow-sm' : 'border-transparent bg-transparent' }}">All</x-ui.button>
                    <x-ui.button size="default" variant="secondary" wire:click="setTab('queued')" class="{{ $tab === 'queued' ? 'bg-paper shadow-sm' : 'border-transparent bg-transparent' }}">Queued</x-ui.button>
                    <x-ui.button size="default" variant="secondary" wire:click="setTab('blocked')" class="{{ $tab === 'blocked' ? 'bg-paper shadow-sm' : 'border-transparent bg-transparent' }}">Blocked</x-ui.button>
                    <x-ui.button size="default" variant="secondary" wire:click="setTab('stale')" class="{{ $tab === 'stale' ? 'bg-paper shadow-sm' : 'border-transparent bg-transparent' }}">Stale</x-ui.button>
                </div>
                <x-ui.button size="default" variant="secondary" wire:click="toggleSample">
                    {{ $isSample ? 'Exit sample' : 'Show a sample' }}
                </x-ui.button>
            </div>
        </div>

        @if($actionNotice && str_starts_with($actionNotice, 'Marked stale'))
            <div class="bg-green-50 text-green-800 p-4 rounded-md">
                {{ $actionNotice }}
            </div>
        @elseif($actionNotice)
            <x-ui.error-panel heading="Action failed">
                {{ $actionNotice }}
            </x-ui.error-panel>
        @endif

        <div wire:loading>
            <x-ui.skeleton label="Loading board..." />
        </div>

        <div wire:loading.remove>
            @if(! $isSample && $fetches->isEmpty() && $tab === 'all')
                <x-ui.empty-state heading="Nothing fetched yet" action="Show a sample" target="toggleSample">
                    No fetching activity has been recorded. Connect a target to start.
                </x-ui.empty-state>
            @else
                @if($targets->isNotEmpty())
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        @foreach($targets as $t)
                            <div class="bg-paper border border-rule rounded-lg p-4 shadow-sm" data-tile="target-{{ $t->id }}">
                                <div class="font-medium text-ink">{{ $t->domain }}</div>
                                <div class="text-sm text-ink-2 mt-1">
                                    {{ $t->rps_ceiling }} req/s &middot; {{ $t->concurrency_ceiling }} workers
                                </div>
                                @php
                                    $targetFetches = $isSample ? $fetches->where('target_id', $t->id)->count() : \App\Modules\X151\Models\Fetch::where('business_id', $this->businessId)->where('target_id', $t->id)->count();
                                @endphp
                                <div class="text-2xl font-semibold text-ink mt-2" data-value="{{ $targetFetches }}">
                                    {{ $targetFetches }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="space-y-4">
                    @forelse($fetches as $f)
                        <div class="bg-paper border border-rule rounded-lg p-4 shadow-sm">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="text-sm font-medium text-ink truncate" title="{{ $f->url }}">
                                        {{ $f->url }}
                                    </div>
                                    <div class="text-xs text-ink-2 mt-1">
                                        {{ $targets[$f->target_id]->domain ?? 'Unknown' }} &middot; {{ \Carbon\Carbon::parse($f->updated_at)->diffForHumans() }}
                                    </div>
                                </div>
                                <div>
                                    @if($f->is_stale || $f->status === 'stale')
                                        <x-ui.status-pill state="attention" label="Stale" />
                                    @elseif($f->status === 'skipped_captcha')
                                        <x-ui.status-pill state="alert" label="CAPTCHA, skipped after 3 tries" />
                                    @elseif($f->status === 'success')
                                        <x-ui.status-pill state="ok" label="Fetched" />
                                    @elseif(str_starts_with($f->status, 'queued'))
                                        <x-ui.status-pill state="unknown" label="Queued" />
                                    @else
                                        <x-ui.status-pill state="unknown" label="{{ $f->status }}" />
                                    @endif
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end">
                                @if(! $f->is_stale)
                                    <x-ui.button size="default" variant="secondary" wire:click="markStale({{ $f->id }})">Mark stale</x-ui.button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-ink-2 text-sm">
                            No records found for this view.
                        </div>
                    @endforelse
                </div>
            @endif
        </div>
        
        <div class="mt-8">
            <slot name="assistant"></slot>
        </div>
    </div>
</div>
