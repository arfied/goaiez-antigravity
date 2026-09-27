<div>
    <div class="pitchacquire-ratio-view p-4">
        <h2 class="text-lg font-bold text-ink">Outreach ratio</h2>
        @if($pitches === 0 && $earned === 0)
            <x-ui.empty-state heading="No outreach yet.">Pitches and the links they win are counted here.</x-ui.empty-state>
        @else
            <p class="text-ink-2 tabular-nums">{{ $pitches }} pitches sent · {{ $earned }} links earned · {{ $pitches > 0 ? (int) round($earned / $pitches * 100) : 0 }}% acquired</p>
        @endif

        <x-ui.toast kind="success" :message="$success" />
        
        <x-ui.toast kind="error" :message="$error" />

        <div class="mt-8 bg-surface p-4 rounded border">
            <h3 class="font-bold text-ink mb-4">Prospect Target</h3>
            <form wire:submit="prospect" class="flex flex-col gap-4">
                <div>
                    <label class="block text-ink-2 mb-1">Domain</label>
                    <input type="text" wire:model="domain" class="border rounded p-2 text-ink w-full bg-surface">
                </div>
                <div>
                    <label class="block text-ink-2 mb-1">Target URL</label>
                    <input type="text" wire:model="targetUrl" class="border rounded p-2 text-ink w-full bg-surface">
                </div>
                <div>
                    <label class="block text-ink-2 mb-1">DA Score</label>
                    <input type="number" wire:model="daScore" class="border rounded p-2 text-ink w-full bg-surface">
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" wire:model="isPbn" id="isPbn" class="border rounded text-ink bg-surface">
                    <label for="isPbn" class="text-ink-2">Is PBN / Toxic?</label>
                </div>
                <button type="submit" class="bg-surface text-ink border rounded p-2 self-start mt-2">Prospect</button>
            </form>
        </div>

        <div class="mt-8 bg-surface p-4 rounded border">
            <h3 class="font-bold text-ink mb-4">Send Pitch</h3>
            <form wire:submit="pitch" class="flex flex-col gap-4">
                <div>
                    <label class="block text-ink-2 mb-1">Target ID</label>
                    <input type="text" wire:model="targetId" class="border rounded p-2 text-ink w-full bg-surface">
                </div>
                <div>
                    <label class="block text-ink-2 mb-1">Pitch Body</label>
                    <textarea wire:model="pitchBody" class="border rounded p-2 text-ink w-full bg-surface" rows="3"></textarea>
                </div>
                <div>
                    <label class="block text-ink-2 mb-1">Page-Specific Fact</label>
                    <input type="text" wire:model="pageSpecificFact" class="border rounded p-2 text-ink w-full bg-surface">
                </div>
                <button type="submit" class="bg-surface text-ink border rounded p-2 self-start mt-2">Send Pitch</button>
            </form>
        </div>
    </div>
</div>
