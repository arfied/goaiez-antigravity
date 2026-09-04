<x-surface.sample-state module="persistent chat on every page. **OPERATE** *("text everyone who called last week")*" screen="todays_recommendation_strip" />
<div>
    <div class="rec-strip-view p-4">
        <h3 class="text-lg font-bold">Today's Recommendations</h3>
        @if($recs->isEmpty())
            <p class="text-gray-500">No active recommendations today.</p>
        @else
            <ul>
                @foreach($recs as $r)
                    <li>#{{ $r->id }}: {{ $r->title }} [{{ $r->action_key }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
