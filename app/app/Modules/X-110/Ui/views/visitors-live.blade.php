<div>
    <div wire:loading.delay>
        <x-ui.skeleton label="Loading live visitors..." />
    </div>

    <div wire:loading.remove>
        @if(isset($loadError) && $loadError)
            <x-ui.error-panel heading="Could not load live visitors">
                {{ $loadError }}
            </x-ui.error-panel>
        @elseif($isSample)
            <x-ui.sample />
        @elseif($sessions->isEmpty())
            <x-ui.empty-state icon="👀" heading="Nobody on the site right now" action="Install pixel" href="{{ route('account.pixel-install') }}">
                @if($installVerified)
                    The tag is installed and listening.
                @else
                    Pixel not verified.
                @endif
            </x-ui.empty-state>
        @else
            <div class="space-y-6">
                <h2 class="text-xl font-bold text-ink">Real-time Visitors</h2>

                <x-ui.row-list>
                    @foreach($sessions as $session)
                        <x-ui.row class="flex flex-col gap-3 sm:flex-row items-start sm:items-center">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-ink">
                                    <span class="font-mono text-xs">{{ $session->visitor_id }}</span>
                                    <span class="text-ink-2 font-normal ml-2">{{ $session->started_at->diffForHumans() }}</span>
                                </p>
                                <p class="text-sm text-ink-2 truncate mt-1">
                                    {{ $session->landing_page }}
                                    @if($session->utm_source || $session->utm_medium || $session->utm_campaign)
                                        <span class="inline-block ml-2 px-1.5 py-0.5 bg-paper rounded text-xs border border-rule">
                                            {{ collect([$session->utm_source, $session->utm_medium, $session->utm_campaign])->filter()->implode(' / ') }}
                                        </span>
                                    @endif
                                </p>
                            </div>
                            <div class="w-full sm:w-auto mt-2 sm:mt-0">
                                <x-ui.button variant="secondary" size="default" type="button" class="w-full sm:w-auto">View Events</x-ui.button>
                            </div>
                        </x-ui.row>
                    @endforeach
                </x-ui.row-list>
            </div>
        @endif
    </div>
</div>
