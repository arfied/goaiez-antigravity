<x-surface.sample-state module="staff, roles, granular permissions, and the document vault. ⛔ **No scoring, no ranking, no attendance.**" screen="staff" />
<div>
    <div class="staff-view p-4">
        <h3 class="text-lg font-bold">Staff Members</h3>
        @if($staff->isEmpty())
            <p class="text-gray-500">No staff members enrolled.</p>
        @else
            <ul>
                @foreach($staff as $s)
                    <li>#{{ $s->id }}: {{ $s->name }} ({{ $s->email }}) [{{ $s->is_active ? 'Active' : 'Deactivated' }}]</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
