<div>
    <div class="whatsapp-card-view p-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-ink">WhatsApp templates</h2>
            <button wire:click="refresh" class="text-sm border rounded px-2 py-1 bg-surface">Refresh from Meta</button>
        </div>
        
        <x-ui.toast kind="error" :message="$error ?? null" />
        
        @if($templates->isEmpty())
            <x-ui.empty-state heading="No templates yet.">Templates are reviewed by Meta through Zernio, usually within a day. Connect WhatsApp first to submit one.</x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($templates as $t)
                    <li class="py-2" wire:key="tpl-{{ $t->id }}">
                        <span class="font-semibold">{{ $t->name }}</span>
                        <span class="text-sm text-ink-2">{{ $t->status }}</span>
                        <span class="text-sm text-ink-2">{{ $t->category }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
