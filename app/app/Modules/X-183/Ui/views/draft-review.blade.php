<div>
    <div class="p-4">
        <h2 class="text-xl font-bold text-ink">Drafts written for you</h2>

        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4">
            <form wire:submit="submit" class="flex flex-col gap-2">
                <x-ui.toast kind="success" :message="$success" />
                <x-ui.toast kind="error" :message="$error" />
                <input type="text" wire:model="title" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Draft title">
                <textarea wire:model="bodyText" class="border rounded p-2 text-ink flex-1 bg-surface" placeholder="Draft body text" rows="4"></textarea>
                <label class="flex items-center gap-2 text-ink">
                    <input type="checkbox" wire:model="isCaseStudy" class="border rounded">
                    Is Case Study
                </label>
                <button type="submit" class="bg-surface text-ink border rounded p-2">Submit</button>
            </form>
        </div>

        @if($drafts->isEmpty())
            <x-ui.empty-state icon="○" heading="No drafts yet">
                When a draft is written for you, it appears here with whether it was published.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach($drafts as $draft)
                    <li class="text-ink">{{ $draft->title }} · {{ $draft->is_published ? 'Published' : ($draft->gateResults->isEmpty() ? 'Not checked yet' : 'Held back') }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
