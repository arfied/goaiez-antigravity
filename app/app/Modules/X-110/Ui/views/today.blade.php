<div>
    <div class="mb-6 sm:mb-8">
        <h2 class="text-sm font-semibold text-ink-2 uppercase tracking-wider mb-3">Worth a minute</h2>
        
        <div wire:loading>
            <x-ui.skeleton label="Loading visitors..." />
        </div>
        
        <div wire:loading.remove>
            @if($loadError)
                <x-ui.error-panel heading="Could not load visitors">
                    {{ $loadError }}
                </x-ui.error-panel>
            @elseif(!$isVerified)
                <x-ui.empty-state icon="🌐" heading="Pixel not verified" action="Install and verify" href="{{ route('account.pixel-install') }}">
                    Your tracking pixel hasn't received any events yet.
                </x-ui.empty-state>
            @else
                <x-ui.row-list>
                    <x-ui.row >
                        <div class=" min-w-0 pr-4">
                            <p class="text-sm font-medium text-ink truncate">Today's Visitors</p>
                        </div>
                        <div class="text-sm font-semibold text-ink">
                            {{ $todayVisitsCount }}
                        </div>
                    </x-ui.row>
                </x-ui.row-list>
            @endif
        </div>
    </div>
</div>
