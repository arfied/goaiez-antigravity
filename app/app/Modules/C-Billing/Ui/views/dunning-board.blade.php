<div>
    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto">
        <div class="mb-8">
            <h2 class="text-lg font-bold text-ink">Dunning board</h2>
            <p class="mt-2 text-sm text-ink-2">Dunning Timeline</p>
        </div>

        <div wire:loading>
            <x-ui.skeleton label="Reading the dunning cycle…" lines="3" />
        </div>

        <div wire:loading.remove class="mt-8 flow-root">
            @if($attempts->isEmpty())
                <x-ui.empty-state heading="Nothing on this board yet.">No subscription payment on this account has been declined. A row appears here the first time a renewal fails, and the schedule adds another for each retry it makes.</x-ui.empty-state>
            @else
                <div class="overflow-hidden shadow border border-rule sm:rounded-lg">
                    <table class="min-w-full divide-y divide-rule">
                        <thead class="bg-paper">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-ink sm:pl-6">Run</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Attempt</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Outcome</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Reason</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Attempted</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Next attempt</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-rule bg-card">
                            @foreach($attempts as $attempt)
                                <tr>
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-ink sm:pl-6">
                                        {{ $attempt->sequence }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">
                                        {{ $attempt->attempt }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">
                                        @php($o = $outcomes[$attempt->outcome->value] ?? ['Unknown outcome', 'unknown'])
                                        <x-ui.status-pill :state="$o[1]" :label="$o[0]" />
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">
                                        {{ $attempt->reason_code ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">
                                        {{ $attempt->attempted_at?->format('j M Y H:i') ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">
                                        {{ $attempt->next_attempt_at?->format('j M Y H:i') ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-sm text-ink-2">This board is a record, not a control. Retries run on their own schedule and a card that is fixed with the gateway closes the run without anything being pressed here.</p>
            @endif
        </div>
    </div>
</div>
