<div>
    <div class="forms-view p-4">
        <h2 class="text-lg font-bold text-ink">Forms</h2>
        @if($forms->isEmpty())
            <p class="text-ink-2"><span class="sr-only">No forms constructed yet.</span>No forms yet — create your first lead form below.</p>
        @else
            <ul class="space-y-4">
                @foreach($forms as $f)
                    <li class="border p-4 rounded flex flex-col md:flex-row md:justify-between md:items-center">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold">{{ $f->form_name }}</span>
                                <span class="text-xs px-2 py-1 rounded bg-surface">{{ $f->slug }}</span>
                            </div>
                            <div class="text-sm mt-1 text-ink-2">
                                {{ is_array($f->steps) ? count($f->steps) : 0 }} step(s)
                            </div>
                            <div class="text-sm mt-1 text-ink-2">
                                {{ $f->submissions_count }} submissions • {{ $f->spam_count }} spam
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mt-8 border-t pt-4">
            <form wire:submit.prevent="createForm" class="flex gap-2 items-center">
                <input type="text" wire:model="newFormName" class="border rounded px-2 py-1" required>
                <button type="submit" class="bg-blue-600 text-canvas px-4 py-1 rounded">Create</button>
            </form>
        </div>
    </div>
</div>
