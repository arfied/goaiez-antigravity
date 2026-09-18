<div>
    <div class="targeting-preview-view p-4">
        <h2 class="text-lg font-bold text-ink">Promotion targeting</h2>
        @if($scopes->isEmpty())
            <p class="text-ink-2">No targeting scopes yet.</p>
        @else
            <ul>
                @foreach($scopes as $scope)
                    <li>{{ $scope->scope_type }} {{ $scope->scope_value }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
