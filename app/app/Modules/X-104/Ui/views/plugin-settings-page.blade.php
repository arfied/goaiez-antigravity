<div>
    <div class="plugin-settings-view p-4">
        <h2 class="text-lg font-bold text-ink">Plugin sites</h2>
        @if($installs->isEmpty())
            <p class="text-ink-2">No plugin sites connected yet.</p>
        @else
            <ul>
                @foreach($installs as $install)
                    <li>{{ $install->site_url }} — {{ $install->is_active ? 'active' : 'inactive' }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
