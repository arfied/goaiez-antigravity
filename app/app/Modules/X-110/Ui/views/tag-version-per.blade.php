<div>
    <h2>Tag versions</h2>
    <p>Visitors to your site load the active pixel bundle{{ $canary ? ' — a canary is being served to '.number_format($canaryBp / 100, 2).'% of them' : '' }}.</p>

    @if($versions->isEmpty())
        <p>No pixel bundle has been published yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>sha</th>
                    <th>status</th>
                    <th>published</th>
                    <th>canary started</th>
                    <th>promoted</th>
                    <th>halted</th>
                    <th>actor</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($versions as $version)
                    <tr>
                        <td>{{ substr($version->sha, 0, 12) }}</td>
                        <td>{{ $version->status }}</td>
                        <td>{{ $version->published_at }}</td>
                        <td>{{ $version->canary_started_at }}</td>
                        <td>{{ $version->promoted_at }}</td>
                        <td>{{ $version->halted_at }}{{ $version->halt_reason ? ' (' . $version->halt_reason . ')' : '' }}</td>
                        <td>{{ $version->actor }}</td>
                        <td>
                            @if($active && $active->id === $version->id)
                                serving now
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <a href="{{ route('x-110.install-verify') }}">Is our tag working &rarr;</a>
</div>
