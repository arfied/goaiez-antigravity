<div>
    <div class="margin-source-view p-4">
        <h2>Margin by source</h2>
        
        @if(empty($computedRows))
            <x-ui.empty-state>No margin by source yet. Costed jobs roll up here.</x-ui.empty-state>
        @else
            <table class="w-full text-left">
                <thead>
                    <tr>
                        <th>Source</th>
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
                            <td>{{ $row['source'] }}</td>
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
                                            <li>Job #{{ $job->job_id }} (v{{ $job->price_book_version }}) margin: {{ $jobMargin[$job->job_id] }}</li>
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
