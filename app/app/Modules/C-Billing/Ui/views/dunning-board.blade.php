<div>
    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto">
        <div class="mb-8">
            <h2 class="text-lg font-bold text-ink">Dunning board</h2>
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
                <x-ui.empty-state heading="Nothing on this board yet.">This board reads its own dunning ladder, and nothing in this checkout writes to it yet. A missed payment on your subscription is retried on a separate schedule that this board does not read, so an account can be in that schedule while this board is empty. Declines live on the Money screen.</x-ui.empty-state>
            @else
                <div class="overflow-hidden shadow border border-rule sm:rounded-lg">
                    <table class="min-w-full divide-y divide-rule">
                        <thead class="bg-paper">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-ink sm:pl-6">Day in Cycle</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Next step (planned)</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Status</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-rule bg-card">
                            @foreach($states as $state)
                                <tr>
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-ink sm:pl-6">
                                        Day {{ $state->day_in_cycle }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">
                                        {{ $state->next_step_words }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">
                                        <x-ui.status-pill :state="$dunningPillStates[$state->status] ?? 'unknown'" :label="$dunningLabels[$state->status] ?? $state->status" />
                                    </td>
                                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                        <x-ui.button size="default" variant="secondary" wire:click="advance({{ $state->id }})" wire:loading.attr="disabled" wire:target="advance({{ $state->id }})">Advance</x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-sm text-ink-2">No step on this ladder runs by itself: Advance is a button, and nothing in this checkout switches a phone, an agent or a number when a day is reached.</p>
            @endif
        </div>
    </div>
</div>
