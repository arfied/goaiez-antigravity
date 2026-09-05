<div>
    <div wire:loading.delay>
        <x-ui.skeleton label="Loading verification..." />
    </div>

    <div wire:loading.remove>
        @if($isSample)
            <x-ui.sample />
        @else
            <div class="space-y-6">
                <h2 class="text-xl font-bold text-ink">Tag Installation & Verification</h2>

                <x-ui.row-list>
                    <x-ui.row>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink">1. First-party domain serving</p>
                            @if($verify['first_party'])
                                <p class="text-sm text-ink-2">Tag is served from {{ $verify['served_from'] }}</p>
                            @else
                                <p class="text-sm text-ink-2">The tag is being served from {{ $verify['served_from'] }}. Point analytics.{{ $domain }} at us with a CNAME and reload this page.</p>
                            @endif
                        </div>
                        <div>
                            <x-ui.status-pill :state="$verify['first_party'] ? 'ok' : 'alert'" label="{{ $verify['first_party'] ? 'Verified' : 'Failed' }}" />
                        </div>
                    </x-ui.row>

                    <x-ui.row>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink">2. Third-party cookies</p>
                            @if($verify['third_party_cookies_disabled'])
                                <p class="text-sm text-ink-2">Third-party cookies are not required.</p>
                            @else
                                <p class="text-sm text-ink-2">Turn off third-party cookie dependence in settings.</p>
                            @endif
                        </div>
                        <div>
                            <x-ui.status-pill :state="$verify['third_party_cookies_disabled'] ? 'ok' : 'alert'" label="{{ $verify['third_party_cookies_disabled'] ? 'Verified' : 'Failed' }}" />
                        </div>
                    </x-ui.row>

                    <x-ui.row>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink">3. Live events arriving</p>
                            @if($recentEvents->count() > 0)
                                <p class="text-sm text-ink-2">
                                    We see you. {{ $recentEvents->count() }} events in the last 60 seconds: 
                                    <span class="font-mono text-xs cursor-pointer" title="{{ $recentEvents->pluck('event_name')->implode(', ') }}">
                                        {{ $recentEvents->pluck('event_name')->first() }}...
                                    </span>
                                </p>
                            @else
                                <p class="text-sm text-ink-2">No events seen in the last 60 seconds. Check if the tag is on the page.</p>
                            @endif
                        </div>
                        <div>
                            <x-ui.status-pill :state="$recentEvents->count() > 0 ? 'ok' : 'alert'" label="{{ $recentEvents->count() > 0 ? 'Verified' : 'Failed' }}" />
                        </div>
                    </x-ui.row>

                    <x-ui.row>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink">4. Web vitals reporting</p>
                            @if($cwv)
                                <p class="text-sm text-ink-2">Latest LCP: {{ $cwv->lcp_ms }}ms, CLS: {{ $cwv->cls_score }}</p>
                            @else
                                <p class="text-sm text-ink-2">No core web vitals samples received yet. We will measure this automatically when visitors arrive.</p>
                            @endif
                        </div>
                        <div>
                            <x-ui.status-pill :state="$cwv ? 'ok' : 'alert'" label="{{ $cwv ? 'Verified' : 'Failed' }}" />
                        </div>
                    </x-ui.row>

                    <x-ui.row>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink">5. Snippet</p>
                            <code class="block text-xs bg-card p-2 mt-1">{{ $install['script_tag'] }}</code>
                        </div>
                        <div>
                            <x-ui.button variant="secondary" size="default" type="button">Copy</x-ui.button>
                        </div>
                    </x-ui.row>

                    <x-ui.row>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink">6. Tag performance budget</p>
                            <p class="text-sm text-ink-2">We need to observe the tag's execution time on your live site to measure this budget.</p>
                        </div>
                        <div>
                            <x-ui.status-pill state="unknown" label="Not measured" />
                        </div>
                    </x-ui.row>

                    <x-ui.row>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink">7. Call token swapping</p>
                            <p class="text-sm text-ink-2">We need a page view containing phone numbers to confirm swapping works.</p>
                        </div>
                        <div>
                            <x-ui.status-pill state="unknown" label="Not measured" />
                        </div>
                    </x-ui.row>
                </x-ui.row-list>
            </div>
        @endif
    </div>
</div>
