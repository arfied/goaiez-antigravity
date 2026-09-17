<div>
    <div class="links-earned-view p-4">
        <h2 class="text-lg font-bold text-ink">Earned links</h2>
        @if($placements->isEmpty())
            <x-ui.empty-state heading="No earned links yet.">A link you win through outreach is recorded here with the page that carries it.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($placements as $p)
                    <li class="py-2">
                        <span class="font-mono text-sm">{{ $p->placed_url }}</span>
                        <span class="text-ink-2">{{ $p->anchor_text }}</span>
                        <span class="text-sm text-ink-2">{{ $p->is_active ? 'live' : 'lost' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
