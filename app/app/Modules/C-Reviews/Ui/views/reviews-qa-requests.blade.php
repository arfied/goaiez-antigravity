<div>
    <div class="reviews-container p-4">
        <h3 class="text-lg font-bold">Review Requests & Feedback</h3>
        @if($requests->isEmpty())
            <p class="text-gray-500">No review requests recorded.</p>
        @else
            <ul>
                @foreach($requests as $r)
                    <li>#{{ $r->id }} ({{ $r->platform }} - {{ $r->rating }}★) [{{ $r->status }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
