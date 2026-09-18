<div>
    <div class="whatsapp-card-view p-4">
        <h2 class="text-lg font-bold text-ink">WhatsApp templates</h2>
        @if($templates->isEmpty())
            <x-ui.empty-state heading="No templates yet.">A message template has to be approved before it can be sent outside a conversation, and each one's status shows here.</x-ui.empty-state>
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
