<div>
    <div class="p-4">
        <h2 class="text-xl font-bold text-ink">Drafts held back before publishing</h2>
        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 border border-surface">
            <h3 class="text-lg font-bold text-ink">Evaluate Draft</h3>
            <x-ui.toast kind="success" :message="$success" />
            <x-ui.toast kind="error" :message="$error" />
            @if($drafts->isEmpty())
                <p class="text-ink">No ungated drafts available. Drafts come from DraftReview.</p>
            @else
                <form wire:submit="gateDraft" class="flex gap-2">
                    <select wire:model="draftId" class="border rounded p-2 text-ink flex-1 bg-surface">
                        <option value="">Select a draft...</option>
                        @foreach($drafts as $draft)
                            <option value="{{ $draft->id }}">{{ $draft->title }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="bg-surface text-ink border rounded p-2">Gate Draft</button>
                </form>
            @endif
        </div>
        @if($rejections->isEmpty())
            <x-ui.empty-state icon="○" heading="Nothing held back">
                When a draft is held back before publishing, it appears here with the reason.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($rejections as $rejection)
                    <li class="text-ink">{{ $titles[$rejection->draft_id] }} · {{ preg_replace('/^R\d+:\s*/', '', (string) $rejection->rejection_reason) }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
