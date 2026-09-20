<div>

    <div class="staff-view p-4">
        <h2 class="text-lg font-bold">Agency Staff Management</h2>
        @if($staff->isEmpty())
            <p class="text-ink-2">No agency staff enrolled.</p>
        @else
            <ul>
                @foreach($staff as $s)
                    <li>User #{{ $s->user_id }} - {{ $s->role }} ({{ $s->is_active ? 'Active' : 'Revoked' }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
