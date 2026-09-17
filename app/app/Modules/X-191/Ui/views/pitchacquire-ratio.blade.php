<div>
    <div class="pitchacquire-ratio-view p-4">
        <h2 class="text-lg font-bold text-ink">Outreach ratio</h2>
        @if($pitches === 0 && $earned === 0)
            <x-ui.empty-state heading="No outreach yet.">Pitches and the links they win are counted here.</x-ui.empty-state>
        @else
            <p class="text-ink-2 tabular-nums">{{ $pitches }} pitches sent · {{ $earned }} links earned · {{ $pitches > 0 ? (int) round($earned / $pitches * 100) : 0 }}% acquired</p>
        @endif
    </div>
</div>
