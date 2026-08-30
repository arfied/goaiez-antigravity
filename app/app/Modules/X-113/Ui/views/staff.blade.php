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
