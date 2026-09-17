<section class="site-editor-assistant-panel p-4">
    <h2 class="text-lg font-bold text-ink">Site editor</h2>
    @if($changes->isEmpty())
        <x-ui.empty-state heading="No design changes yet.">A change you ask for in plain words is recorded here with the block it touched and the contrast it kept.</x-ui.empty-state>
    @else
        <ul class="divide-y divide-rule">
            @foreach($changes as $c)
                <li class="py-2"><span class="font-semibold">{{ $c->change_type }}</span> <span class="text-ink-2">{{ $c->block_ref }}</span> <span class="text-sm text-ink-2 tabular-nums">contrast {{ $c->contrast_ratio }}:1</span> <span class="text-sm text-ink-2">{{ $c->status }}</span></li>
            @endforeach
        </ul>
    @endif
</section>
