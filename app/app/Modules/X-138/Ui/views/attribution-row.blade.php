<div>
    
    @if ($queries->isEmpty() && $snapshots->isEmpty())
        <x-ui.empty-state icon="💰" heading="No attribution data yet">
            When a page earns revenue from a tracked job, it will appear here.
        </x-ui.empty-state>
    @else
        <div class="space-y-8">
            <div>
                <h3 class="text-xl font-bold text-ink mb-4">Page Earnings</h3>
                <x-ui.row-list>
                    @foreach($queries as $q)
                        @php
                            $touches = is_string($q->touches) ? json_decode($q->touches, true) : (array)$q->touches;
                            $value = $q->job_value !== null ? '$' . number_format($q->job_value / 100, 2) : '--';
                            $jobLabel = $q->job_id ? "Job #{$q->job_id}" : "Unknown job";
                        @endphp
                        <x-ui.row class="p-4 flex items-center justify-between">
                            <div class="flex-1">
                                <p class="font-medium text-ink">
                                    <span class="text-ink-2">{{ $jobLabel }} &mdash;</span> 
                                    This job earned {{ $value }}
                                </p>
                                <div class="text-sm text-ink-2 mt-1">
                                    @foreach($touches as $t)
                                        <div class="truncate">{{ $t['source'] ?? 'Unknown page' }}</div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="ml-4">
                                <span class="text-xs px-2 py-1 rounded bg-paper border border-rule text-ink-2 uppercase tracking-wide">
                                    {{ $q->attribution_status }}
                                </span>
                            </div>
                        </x-ui.row>
                    @endforeach
                </x-ui.row-list>
            </div>
            
            @if($snapshots->isNotEmpty())
                <div>
                    <h3 class="text-lg font-bold text-ink mb-4">Campaign ROI</h3>
                    <x-ui.row-list>
                        @foreach($snapshots as $s)
                            <x-ui.row class="p-4 flex items-center justify-between">
                                <div class="flex-1">
                                    <p class="font-medium text-ink">{{ $s->campaign_name }}</p>
                                    <p class="text-sm text-ink-2 mt-1">
                                        Ad Spend: ${{ number_format($s->ad_spend_cents / 100, 2) }}
                                    </p>
                                </div>
                                <div class="ml-4 text-right">
                                    <p class="font-bold text-green-600">
                                        ${{ number_format($s->closed_revenue_cents / 100, 2) }} Revenue
                                    </p>
                                </div>
                            </x-ui.row>
                        @endforeach
                    </x-ui.row-list>
                </div>
            @endif
        </div>
    @endif
</div>
