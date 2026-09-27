<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Facebook reviews</h1>
    </div>

    @if ($reviews->isEmpty())
        <x-ui.empty-state heading="No Facebook reviews yet.">Connect your Facebook Page so we can bring its reviews in.</x-ui.empty-state>
    @else
        <ul class="space-y-4">
            @foreach ($reviews as $review)
                <li class="rounded-[--radius-panel] border border-rule bg-card p-5 space-y-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="font-display text-lg font-semibold text-ink">
                            ★ {{ $review->rating }}
                            @if ($review->recommendation === 'positive')
                                Recommends
                            @elseif ($review->recommendation === 'negative')
                                Does not recommend
                            @endif
                            @if ($review->reviewer_name)
                                — {{ $review->reviewer_name }}
                            @endif
                        </p>
                        @if ($review->review_create_time)
                            <p class="text-sm text-ink-2">{{ $review->review_create_time->format('M j, Y') }}</p>
                        @endif
                    </div>
                    @if ($review->comment)
                        <blockquote class="border-l-2 border-rule pl-3 text-base text-ink-2">
                            {{ $review->comment }}
                        </blockquote>
                    @endif
                    @if (data_get($review->raw_payload, 'has_owner_reply'))
                        <p class="text-sm text-ink-3">Replied on Facebook</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    
    <p class="text-sm text-ink-3">Recommendations without a star rating are not imported yet.</p>
</div>
