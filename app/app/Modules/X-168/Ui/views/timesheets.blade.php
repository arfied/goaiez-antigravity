<div>
    <header>
        <h2>Timesheets</h2>
        <p>it closes on the rule; the tenant may reopen it</p>
    </header>

    @if($timesheets->isEmpty())
        <x-ui.empty-state>No timesheets yet. Hours appear when a technician arrives on site.</x-ui.empty-state>
    @else
        <table>
            <thead>
                <tr>
                    <th>Technician</th>
                    <th>Period</th>
                    <th>Hours</th>
                    <th>Status</th>
                    <th>Sample</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($timesheets as $sheet)
                    <tr>
                        <td>{{ $names[$sheet->person_id]->name ?? 'Unknown' }}</td>
                        <td>{{ $sheet->period_start->format('Y-m-d') }} – {{ $sheet->period_end->format('Y-m-d') }}</td>
                        <td>{{ $hours[$sheet->id] }}</td>
                        <td>
                            <x-ui.status-pill :state="$sheet->status === 'approved' ? 'ok' : 'attention'" :label="$sheet->status" />
                        </td>
                        <td>
                            @if($sheet->is_sample)
                                <x-ui.status-pill state="attention" label="Sample" />
                            @endif
                        </td>
                        <td>
                            <x-ui.button size="default" wire:click="toggle({{ $sheet->id }})">Show entries</x-ui.button>
                            @if($sheet->status !== 'open')
                                <x-ui.button size="default" wire:click="reopen({{ $sheet->id }})">Reopen</x-ui.button>
                            @endif
                        </td>
                    </tr>
                    @if(!empty($expanded[$sheet->id]))
                        <tr>
                            <td colspan="6">
                                <table>
                                    @foreach($entries[$sheet->id] ?? [] as $entry)
                                        @php
                                            $eh = floor($entry->duration_minutes / 60);
                                            $em = $entry->duration_minutes % 60;
                                        @endphp
                                        <tr>
                                            <td>{{ $entry->state_window }}</td>
                                            <td>{{ $entry->started_at->format('Y-m-d H:i') }}</td>
                                            <td>{{ $entry->ended_at ? $entry->ended_at->format('Y-m-d H:i') : '' }}</td>
                                            <td>{{ sprintf('%d:%02d', (int) $eh, (int) $em) }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif
</div>
