<div>
    <div class="comment-thread-view p-4">
        <h3 class="text-lg font-bold">Document Revision Comment Thread</h3>
        @if($comments->isEmpty())
            <p class="text-gray-500">No comments posted.</p>
        @else
            <ul>
                @foreach($comments as $c)
                    <li>#{{ $c->id }}: [{{ $c->author_email }}] {{ $c->comment_text }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
