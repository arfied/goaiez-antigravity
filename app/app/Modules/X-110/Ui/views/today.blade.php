<div>
    <div class="mb-6 sm:mb-8">
        <h2 class="text-sm font-semibold text-ink-2 uppercase tracking-wider mb-3">Worth a minute</h2>
        @if(!$isVerified)
            <x-ui.empty-state icon="🌐" heading="Pixel not verified" action="Install and verify" href="/advanced/pixel">
                Your tracking pixel hasn't received any events yet.
            </x-ui.empty-state>
        @else
            <x-ui.row-list>
                <x-ui.row href="/advanced/visitors/today">
                    <div class="flex-1 min-w-0 pr-4">
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
