<div>
    <div class="calendar-view p-4">
        <h2>Content calendar</h2>
        @forelse($items as $item)
            <div>{{ $item->scheduled_date->format('D j M') }} - {{ $item->channel }} - {{ $item->topic_theme }} - {{ $item->is_scheduled ? 'planned' : 'proposed' }}</div>
        @empty
            <p>No content is planned yet</p>
        @endforelse
    </div>
</div>
