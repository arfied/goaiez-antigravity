<div>
    <div class="social-queue-view p-4">
        <h2 class="text-lg font-bold text-ink">Social queue</h2>
        @if($posts->isEmpty())
            <x-ui.empty-state heading="No posts queued">Publishing a post is not built here yet; this queue fills only once a post exists.</x-ui.empty-state>
        @else
            <ul>
                @foreach($posts as $post)
                    <li>{{ $post->content_text }} {{ $post->comments->count() }} comments</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
