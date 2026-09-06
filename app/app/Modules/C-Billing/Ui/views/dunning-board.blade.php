<div>
    <x-surface.sample-state module="C-Billing" screen="dunning_board" />
    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-xl font-semibold leading-6 text-ink">Dunning Board</h1>
            <p class="mt-2 text-sm text-ink-2">Dunning Timeline</p>
        </div>

        <div wire:loading>
            <x-ui.skeleton label="Reading the dunning cycle…" lines="3" />
        </div>

        @if($error)
            <x-ui.error-panel heading="We couldn't advance that case">{{ $error }}</x-ui.error-panel>
        @endif

        <div wire:loading.remove class="mt-8 flow-root">
            @if($states->isEmpty())
                <x-ui.empty-state heading="No account is in dunning — nothing to chase.">Declines live on the Money screen.</x-ui.empty-state>
            @else
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Day in Cycle</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Next Step</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($states as $state)
                                <tr>
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                                        Day {{ $state->day_in_cycle }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        {{ $state->next_step_words }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        <x-ui.status-pill :state="$state->status === 'active' ? 'attention' : 'unknown'" :label="$state->status" />
                                    </td>
                                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                        <x-ui.button size="default" variant="secondary" wire:click="advance({{ $state->id }})" wire:loading.attr="disabled" wire:target="advance({{ $state->id }})">Advance</x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
