<div>
    <div class="complaint-board-view p-4">
        <h2 class="text-lg font-bold">Complaint & Bounce Dashboard</h2>

        <div class="flex gap-2 my-4">
            <button wire:click="window(7)" class="px-3 py-1 rounded {{ $days === 7 ? 'bg-blue-600 text-paper' : 'bg-rule' }}">7 Days</button>
            <button wire:click="window(30)" class="px-3 py-1 rounded {{ $days === 30 ? 'bg-blue-600 text-paper' : 'bg-rule' }}">30 Days</button>
            <button wire:click="window(90)" class="px-3 py-1 rounded {{ $days === 90 ? 'bg-blue-600 text-paper' : 'bg-rule' }}">90 Days</button>
        </div>

        @if(count($rows) === 0)
            <div class="p-4 bg-paper rounded text-ink-2">
                No mail events in the last {{ $defaultDays }} days.
            </div>
        @else
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b">
                        <th class="p-2">Domain</th>
                        <th class="p-2">Sent</th>
                        <th class="p-2">Bounces</th>
                        <th class="p-2">Complaints</th>
                        <th class="p-2">Bounce Rate</th>
                        <th class="p-2">Complaint Rate</th>
                        <th class="p-2">Last Event</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr class="border-b">
                            <td class="p-2">{{ $row['domain_name'] }}</td>
                            <td class="p-2">{{ $row['sent'] }}</td>
                            <td class="p-2">{{ $row['bounces'] }}</td>
                            <td class="p-2">{{ $row['complaints'] }}</td>
                            <td class="p-2">{{ $row['bounce_rate'] }}%</td>
                            <td class="p-2">{{ $row['complaint_rate'] }}%</td>
                            <td class="p-2">
                                @if($row['last_event_at'])
                                    {{ \Carbon\Carbon::parse($row['last_event_at'])->diffForHumans() }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

