<div class="space-y-6 sm:space-y-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">Search visibility by location</h1>
        </div>
    </div>

    @if($rows->isEmpty())
        <div class="bg-card border border-rule rounded-card shadow-card p-6 text-center text-ink-2">
            Add a location to track its search visibility.
        </div>
    @else
        <div class="space-y-6">
            @foreach($rows as $row)
                <div class="bg-card border border-rule rounded-card shadow-card p-6">
                    <h2 class="text-lg font-bold text-ink mb-4">{{ $row['location']->name }}</h2>
                    
                    @if($row['current']->isMeasured())
                        @php
                            $totals = $row['current']->totals();
                            $clicks = $totals->clicks;
                            $impressions = $totals->impressions;
                            $ctr = $totals->clickThroughRate() * 100;
                            $impressionsFormatted = number_format($impressions);
                            $clicksFormatted = number_format($clicks);
                            $ctrFormatted = number_format($ctr, 2);
                            
                            $earlierImpressions = $row['earlier']->isMeasured() ? $row['earlier']->totals()->impressions : 0;
                            $movementWord = match($row['movement']) {
                                \App\Services\Visibility\VisibilityMovement::Up => 'up',
                                \App\Services\Visibility\VisibilityMovement::Down => 'down',
                                \App\Services\Visibility\VisibilityMovement::Unchanged => 'unchanged',
                                \App\Services\Visibility\VisibilityMovement::Indeterminate => 'indeterminate',
                            };
                        @endphp
                        
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-4">
                            <div>
                                <div class="text-xs font-medium text-ink-2 uppercase">Impressions</div>
                                <div class="mt-1 text-3xl font-bold text-ink">{{ $impressionsFormatted }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-medium text-ink-2 uppercase">Clicks</div>
                                <div class="mt-1 text-3xl font-bold text-ink">{{ $clicksFormatted }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-medium text-ink-2 uppercase">CTR</div>
                                <div class="mt-1 text-3xl font-bold text-ink">{{ $ctrFormatted }}%</div>
                            </div>
                        </div>
                        <div class="text-sm text-ink-2">
                            Movement: {{ $movementWord }}
                        </div>
                    @else
                        <div class="text-sm text-ink-2">
                            Connect Google Search Console to see search visibility.
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
    
    <div class="mt-8 text-sm text-ink-2 text-center">
        Map-pack rank tracking is not available yet.
    </div>
</div>
