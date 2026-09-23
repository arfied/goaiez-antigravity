<x-ui.row>
    <div class="flex items-center justify-between w-full">
        <div>
            <div class="font-medium text-ink">{{ $apt->start_time->format('H:i') }} - {{ $apt->end_time->format('H:i') }}</div>
            <div class="text-ink-2">
                {{ $apt->first_name }} {{ $apt->last_name }}
                @if ($apt->is_member)
                    <span class="ml-2 text-xs uppercase tracking-wide text-ok">Member</span>
                @endif
            </div>
            <div class="text-sm text-ink mt-1">{{ $apt->service_name }}</div>
        </div>
        <div class="flex flex-col items-end gap-2">
            @php
                $statusState = match($apt->status) {
                    'booked' => 'ok',
                    'reminded' => 'attention',
                    'cancelled' => 'alert',
                    'completed' => 'unknown',
                };
            @endphp
            <x-ui.status-pill :state="$statusState" :label="ucfirst($apt->status)" />
            
            @if ($apt->status !== 'cancelled')
                <x-ui.button wire:click="cancelAppointment({{ $apt->id }})" size="sm" variant="default">
                    Cancel
                </x-ui.button>
            @endif
        </div>
    </div>
</x-ui.row>
