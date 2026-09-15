<div>
    <header>
        <h2>Approvals</h2>
        <p>the week closes AUTOMATICALLY … it closes on the rule; the tenant may reopen it</p>
        <p>mobile and batch approvals</p>
    </header>

    @if($timesheets->isEmpty())
        <x-ui.empty-state>Nothing waiting for approval. Weeks close on their own and land here.</x-ui.empty-state>
    @else
        <x-ui.button size="default" wire:click="approveAll">Approve all shown</x-ui.button>
        <table>
            <thead>
                <tr>
                    <th>Technician</th>
                    <th>Period</th>
                    <th>Hours</th>
                    <th>Status</th>
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
                            <x-ui.button size="default" wire:click="approve({{ $sheet->id }})">Approve</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
