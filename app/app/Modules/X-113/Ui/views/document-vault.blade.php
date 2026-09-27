<div>
    <div class="doc-vault-view p-4 bg-surface text-ink border">
        <h2 class="text-lg font-bold">Employee Document Vault</h2>
        
        @if(! $hasDocuments)
            <p class="mt-4 mb-6">No documents are stored yet, but here is who will be able to view them.</p>
        @endif

        <x-ui.toast kind="success" :message="$success" />
        
        <x-ui.toast kind="error" :message="$error" />

        <form wire:submit="uploadDocument" class="mb-6 flex flex-col gap-2 bg-surface p-4 border rounded mt-4">
            <h3 class="font-bold">Upload a document</h3>
            <div class="flex gap-4 items-center mt-2">
                <select wire:model="selectedStaffId" class="border rounded p-2 text-ink flex-1 bg-surface">
                    <option value="">Select Staff Member</option>
                    @foreach($staff as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
                <input type="file" wire:model="file" class="border rounded p-2 text-ink flex-1 bg-surface">
                <button type="submit" class="bg-surface text-ink border rounded p-2">Upload</button>
            </div>
            @error('file') <span class="text-ink-2 text-sm">{{ $message }}</span> @enderror
        </form>

        @if($staff->isEmpty())
            <x-ui.empty-state heading="No staff yet.">Invite staff on the Staff screen.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($staff as $s)
                    <li class="py-2 flex flex-col gap-2" wire:key="staff-{{ $s->id }}">
                        <div class="flex justify-between">
                            <div>
                                <span class="font-semibold">{{ $s->name }}</span>
                                <span class="text-sm text-ink-2">({{ $s->role_name }})</span>
                            </div>
                            <div>
                                @if($s->can_view)
                                    <span class="text-sm text-ink-2">Can view documents</span>
                                @else
                                    <span class="text-sm text-ink-3">Not permitted</span>
                                @endif
                            </div>
                        </div>
                        @if($s->documents->isNotEmpty())
                            <div class="ml-4 pl-4 border-l border">
                                <h4 class="text-sm font-semibold mb-1">Documents:</h4>
                                <ul class="text-sm text-ink-2 flex flex-col gap-1">
                                    @foreach($s->documents as $doc)
                                        <li class="flex items-center gap-2">
                                            <span>{{ $doc->original_filename }} ({{ number_format($doc->size_bytes / 1024, 2) }} KB) - Uploaded {{ $doc->created_at->format('Y-m-d') }}</span>
                                            @if($s->can_view)
                                                <button type="button" wire:click="download({{ $doc->id }})" class="text-sm text-ink-2 underline">Download</button>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
