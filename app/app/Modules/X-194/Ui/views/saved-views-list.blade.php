<div wire:init="load">
    @if ($errorMessage)
        <x-ui.error-panel heading="We could not load your saved views." retry="load">
            {{ $errorMessage }}
        </x-ui.error-panel>
    @elseif (! $ready)
        <x-ui.skeleton label="Loading views..." />
    @elseif ($views->isEmpty())
        <x-ui.empty-state heading="You have not saved a view yet.">
            When you save a view, it will appear here.
        </x-ui.empty-state>
    @else
        <h3>Saved Views</h3>
        <div class="space-y-4 mt-4">
            @foreach ($views as $view)
                <div class="border p-4 rounded" wire:key="view-{{ $view->id }}">
                    <div class="flex justify-between items-center">
                        <div>
                            <strong>{{ $view->view_name }}</strong>
                            @if($view->is_default)
                                <span class="ml-2 text-xs text-gray-500">(Default)</span>
                            @endif
                        </div>
                        @if(!$view->is_default)
                            <x-ui.button wire:click="makeDefault({{ $view->id }})">Make this the default view</x-ui.button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
