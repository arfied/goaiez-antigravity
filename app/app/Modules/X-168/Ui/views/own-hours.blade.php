<div>
    <h2>Your hours</h2>

    @if($timesheets->isEmpty())
        <x-ui.empty-state>No hours yet. Your hours start when you arrive on site at a job.</x-ui.empty-state>
    @else
        @foreach($timesheets as $sheet)
            @php
                $isExpanded = $expanded[$sheet->id] ?? true;
            @endphp
            <table class="card-like">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Hours</th>
                        <th>Status</th>
                        <th>Sample</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
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
                            <x-ui.button size="default" wire:click="toggle({{ $sheet->id }})">
                                {{ $isExpanded ? 'Hide entries' : 'Show entries' }}
                            </x-ui.button>
                        </td>
                    </tr>
                    @if($isExpanded)
                        <tr>
                            <td colspan="5">
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
                </tbody>
            </table>
        @endforeach
    @endif
</div>
