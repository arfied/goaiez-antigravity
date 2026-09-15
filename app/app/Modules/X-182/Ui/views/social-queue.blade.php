<div>
    <div class="social-queue-view p-4">
        <h2 class="text-lg font-bold text-ink">Social queue</h2>
        @if($posts->isEmpty())
            <p class="text-ink-2">No posts queued.</p>
        @else
            <ul>
                @foreach($posts as $post)
                    <li>{{ $post->content_text }} {{ $post->comments->count() }} comments</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
