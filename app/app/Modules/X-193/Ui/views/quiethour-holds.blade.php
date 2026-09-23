<div>
    <div class="quiethour-holds-view p-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold text-ink">Quiet-hour holds</h2>
            <div class="flex gap-2">
                <button wire:click="window(1)" class="p-2 border rounded {{ $days === 1 ? 'bg-surface text-ink' : 'text-ink-2' }}">1 day</button>
                <button wire:click="window(7)" class="p-2 border rounded {{ $days === 7 ? 'bg-surface text-ink' : 'text-ink-2' }}">7 days</button>
                <button wire:click="window(30)" class="p-2 border rounded {{ $days === 30 ? 'bg-surface text-ink' : 'text-ink-2' }}">30 days</button>
            </div>
        </div>

        @if(empty($holds))
            <x-ui.empty-state heading="No holds in the last {{ $days }} {{ Str::plural('day', $days) }}.">
                Marketing sends are held during the quiet window ({{ $windowStart }}:00 - {{ $windowEnd }}:00).
            </x-ui.empty-state>
        @else
            <ul class="divide-y divide-rule">
                @foreach($holds as $hold)
                    <li class="py-2">
                        <span class="font-mono text-sm text-ink">{{ $hold['what'] }}</span>
                        <span class="text-ink-2">{{ $hold['why'] }}</span>
                        <span class="text-sm text-ink-3">Held {{ \Carbon\Carbon::parse($hold['since'])->format('Y-m-d H:i') }}</span>
                        @if($hold['until'])
                            <span class="text-sm text-ink-3">until {{ \Carbon\Carbon::parse($hold['until'])->format('Y-m-d H:i') }}</span>
                        @endif
                        <span class="text-xs font-mono text-ink-4">({{ $hold['source'] }})</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
