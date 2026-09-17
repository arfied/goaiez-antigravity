<div>
    <h2 class="text-lg font-bold text-ink">Saved views</h2>
    @if ($errorMessage)
        <x-ui.error-panel heading="We could not load your saved views.">
            {{ $errorMessage }}
        </x-ui.error-panel>
    @else
        @if ($saveError)
            <div class="mb-4">
                <x-ui.error-panel heading="We could not save your view." retry="saveView">
                    {{ $saveError }}
                </x-ui.error-panel>
            </div>
        @endif
        @if ($defaultError)
            <div class="mb-4">
                <x-ui.error-panel heading="We could not update your default view." retry="clearDefaultError" retryLabel="Dismiss">
                    {{ $defaultError }}
                </x-ui.error-panel>
            </div>
        @endif
        <div class="mb-6">
            <form wire:submit="saveView">
                <input type="text" wire:model="newViewName" placeholder="New view name">
                <x-ui.button type="submit">Save View</x-ui.button>
                @error('newViewName') <span class="text-red-500">{{ $message }}</span> @enderror
            </form>
        </div>

        @if ($views->isEmpty())
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
                                <a href="{{ route('x-194.any-view-it', ['viewId' => $view->id]) }}" class="text-blue-600 hover:underline"><strong>{{ $view->view_name }}</strong></a>
                                @if($view->is_default)
                                    <span class="ml-2 text-xs text-ink-2">(Default)</span>
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
    @endif
</div>
