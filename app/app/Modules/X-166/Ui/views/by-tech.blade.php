<div>
    <div class="margin-tech-view p-4">
        <h2>Margin by technician</h2>
        
        @if(empty($computedRows))
            <x-ui.empty-state>No margin by technician yet. Costed jobs roll up here.</x-ui.empty-state>
        @else
            <table class="w-full text-left">
                <thead>
                    <tr>
                        <th>Technician</th>
                        <th>Jobs</th>
                        <th>Revenue</th>
                        <th>Cost</th>
                        <th>Margin</th>
                        <th>Margin %</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($computedRows as $row)
                        <tr>
                            <td>{{ $row['tech_name'] }}</td>
                            <td>{{ $row['jobs_count'] }}</td>
                            <td>{{ $row['revenue'] }}</td>
                            <td>{{ $row['cost'] }}</td>
                            <td>{{ $row['margin'] }}</td>
                            <td>
                                <x-ui.status-pill 
                                    :state="$row['margin_pct'] < 20.0 ? 'attention' : 'ok'" 
                                    :label="$row['margin_pct_formatted']" 
                                />
                            </td>
                            <td>
                                <x-ui.button size="default" wire:click="toggle('{{ $row['key'] }}')">Show jobs</x-ui.button>
                            </td>
                        </tr>
                        @if(isset($expanded[$row['key']]))
                            <tr>
                                <td colspan="7" class="bg-surface p-2">
                                    <ul class="space-y-1">
                                        @foreach($row['jobs'] as $job)
                                            <li>Job #{{ $job->job_id }} (v{{ $job->price_book_version }}) margin: {{ number_format($job->gross_margin_cents / 100, 2, '.', '') }}</li>
                                        @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
