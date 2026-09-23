<div>
    <div class="roles-view p-4">
        <h3 class="text-lg font-bold">Role & Permission Matrices</h3>
        @if($roles->isEmpty())
            <p class="text-ink-2">No roles configured.</p>
        @else
            <ul>
                @foreach($roles as $role)
                    <li>Role: {{ $role->role }} (Active: {{ $role->is_active ? 'Yes' : 'No' }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
