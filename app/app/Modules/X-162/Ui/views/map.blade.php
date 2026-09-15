<div>
    <h2>Route map</h2>
    <p>{{ $sentence }}</p>

    @if($routes->isEmpty())
        <x-ui.empty-state title="No routes yet. A route appears when a technician's stops are ordered." />
    @else
        @foreach($routes as $route)
            <section>
                <h3>Technician {{ $route->tech_id }}</h3>
                <p>{{ $distance[$route->id] }}</p>
                <table>
                    <thead>
                        <tr>
                            <th>Stop</th>
                            <th>Job</th>
                            <th>Status</th>
                            <th>Sample</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stops[$route->id] as $index => $stop)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $stop['title'] }}</td>
                                <td>
                                    <x-ui.status-pill :state="$stop['pill'][0]" :label="$stop['pill'][1]" />
                                </td>
                                <td>
                                    @if($stop['is_sample'])
                                        <x-ui.status-pill state="attention" label="Sample" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach
    @endif
</div>
