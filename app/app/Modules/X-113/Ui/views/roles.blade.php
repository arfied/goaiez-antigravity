<div>
    <x-surface.sample-state module="staff, roles, granular permissions, and the document vault. ⛔ **No scoring, no ranking, no attendance.**" screen="roles" />
    <div class="roles-view p-4">
        <h3 class="text-lg font-bold">Roles & Permissions</h3>
        @if($roles->isEmpty())
            <p class="text-gray-500">No roles defined.</p>
        @else
            <ul>
                @foreach($roles as $r)
                    <li>#{{ $r->id }}: {{ $r->name }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
