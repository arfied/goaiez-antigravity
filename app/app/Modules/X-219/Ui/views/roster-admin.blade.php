<div>
    <div class="roster-admin-container p-4">
        <h3 class="text-lg font-bold">Model Roster Administration</h3>
        @if($models->isEmpty())
            <p class="text-gray-500">No models registered in tenant roster.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach($models as $model)
                    <li class="py-2">
                        <span class="font-mono text-sm font-semibold">{{ $model->model_name }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
