<x-surface.sample-state module="white-labelling" screen="staff" />
<div>
    <div class="staff-view p-4">
        <h3 class="text-lg font-bold">Agency Staff Management</h3>
        @if($staff->isEmpty())
            <p class="text-gray-500">No agency staff enrolled.</p>
        @else
            <ul>
                @foreach($staff as $s)
                    <li>User #{{ $s->user_id }} - {{ $s->role }} ({{ $s->is_active ? 'Active' : 'Revoked' }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
