<div>
    <div class="p-4 sm:p-6 lg:p-8 max-w-lg mx-auto">
        <div class="mb-8">
            <h2 class="text-xl font-semibold leading-6 text-ink">Payment Declines & Exceptions</h2>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div class="bg-card px-4 py-5 shadow sm:rounded-[--radius-card] border border-rule">
                    <dt class="truncate text-sm font-medium text-ink-2">{{ $showAll ? 'All declines' : 'Declines this week' }}</dt>
                    <dd class="mt-1 text-3xl font-semibold tracking-tight text-ink tabular-nums">{{ $declinesCount }}</dd>
                </div>
                <div class="bg-card px-4 py-5 shadow sm:rounded-[--radius-card] border border-rule">
                    <dt class="truncate text-sm font-medium text-ink-2">Recovered</dt>
                    <dd class="mt-1 text-3xl font-semibold tracking-tight text-ink tabular-nums">{{ $recoveredCount }}</dd>
                </div>
            </div>
        </div>

        <div class="mt-8 flow-root">
            <div wire:loading>
                <x-ui.skeleton label="Reading this week's declines…" lines="3" />
            </div>

            @if($error)
                <x-ui.error-panel :heading="$errorHeading ?? 'Could not make that pay link'">{{ $error }}</x-ui.error-panel>
            @endif
            
            @if(empty($declines))
                <div wire:loading.remove>
                    <x-ui.empty-state :heading="$showAll ? 'No declines at all.' : 'No declines this week.'" :action="$showAll ? 'Show this week only' : 'Show all'" target="toggleShowAll">
                        You have no declined payments to review. A decline is written when a charge on a card on file is refused, and nothing in this checkout charges a card on file yet.
                    </x-ui.empty-state>
                </div>
            @else
                <div wire:loading.remove class="space-y-6">
                    @foreach($declines as $decline)
                        <div class="overflow-hidden shadow ring-1 ring-rule rounded-[--radius-card] bg-card">
                            <div class="p-4 border-b border-rule">
                                <h3 class="text-base font-medium text-ink">the bank didn't authorise it — happens all the time</h3>
                                <p class="mt-1 text-sm text-ink-2">Amount: <span class="tabular-nums font-semibold">{{ number_format($decline['amount_cents'] / 100, 2) }} {{ $decline['currency'] }}</span> on {{ \Illuminate\Support\Carbon::parse($decline['created_at'])->format('M j, Y g:i A') }}</p>
                                <p class="mt-1 text-sm text-ink-2">ID: {{ $decline['gateway_charge_id'] ?: 'no gateway id' }}</p>
                                @if($decline['recovered'])
                                    <p class="mt-1">
                                        <x-ui.status-pill state="ok" label="Recovered {{ \Illuminate\Support\Carbon::parse($decline['recovered']['created_at'])->format('M j, g:i A') }}" />
                                    </p>
                                @else
                                    <p class="mt-1">
                                        <x-ui.status-pill state="attention" label="Not recovered" />
                                        @if($decline['deferred'])
                                            <x-ui.status-pill state="unknown" label="Deferred" />
                                        @endif
                                    </p>
                                @endif
                                <p class="mt-3 text-base text-ink">no decline code was recorded for this attempt — switch method</p>
                            </div>
                            <div class="bg-paper px-4 py-4 sm:flex sm:flex-row-reverse sm:px-6">
                                <x-ui.button size="default" wire:loading.attr="disabled" wire:target="sendPayLink({{ $decline['id'] }})" wire:click="sendPayLink({{ $decline['id'] }})" class="sm:ml-3 sm:w-auto">Make a pay link</x-ui.button>
                                @if(!$decline['deferred'])
                                <x-ui.button size="default" variant="secondary" wire:loading.attr="disabled" wire:target="settleUpLater({{ $decline['id'] }})" wire:click="settleUpLater({{ $decline['id'] }})" class="sm:mt-0 sm:w-auto mt-3">Settle up later</x-ui.button>
                                @endif
                            </div>
                            @if($decline['pay_link'])
                            <div class="p-4 bg-paper border-t border-rule">
                                <p class="text-base">Pay link ready — send it by text or email:</p>
                                <a href="{{ $decline['pay_link']['url'] }}" class="break-all underline text-base">{{ $decline['pay_link']['url'] }}</a>
                            </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="mt-6">
                    <x-ui.button size="default" variant="quiet" wire:click="toggleShowAll">
                        {{ $showAll ? 'Show this week only' : 'Show all' }}
                    </x-ui.button>
                </div>
            @endif
        </div>
    </div>
</div>
