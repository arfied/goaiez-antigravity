<div>
    <h1>Revenue recovery</h1>

    <x-ui.attention-card state="attention" heading="One account at a time">
        Recovered revenue across every account is an operator roll-up behind row-level security; it waits on an operator read path (OWNER ACTION 15). Below is this account's ladder.
    </x-ui.attention-card>

    @if($error)
        <x-ui.error-panel heading="We couldn't act on that case">
            {{ $error }}
        </x-ui.error-panel>
    @endif

    @if($success)
        <p>{{ $success }}</p>
    @endif

    <div wire:loading>
        <x-ui.skeleton label="Reading the ladder…" />
    </div>

    @if($states->isEmpty())
        <x-ui.empty-state heading="Nobody is in dunning.">This account is current; there is nothing to recover.</x-ui.empty-state>
    @else
        <ul class="space-y-4">
            @foreach($states as $state)
                <li class="border rounded p-4 shadow bg-white">
                    <div class="flex flex-wrap justify-between items-center gap-2">
                        <span class="font-semibold">Day {{ $state->day_in_cycle }} of 21</span>
                        <x-ui.status-pill :state="$state->day_in_cycle >= 21 ? 'attention' : 'ok'" :label="$state->status" />
                    </div>
                    <p class="text-sm text-ink-2 mt-1">{{ $state->stays_on }}</p>
                    <dl class="mt-2 grid grid-cols-2 gap-2 text-sm tabular-nums">
                        <dt class="text-ink-2">At risk</dt>
                        <dd>@if($monthly['cents'] === null) no agreed price on the row (3443) @else {{ number_format($monthly['cents'] / 100, 2) }} a month @endif</dd>
                        <dt class="text-ink-2">Came back</dt>
                        <dd>recovered {{ number_format($state->recovered_cents / 100, 2) }} since the ladder started</dd>
                    </dl>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-ui.button size="default" wire:click="topupNow({{ $state->id }})" wire:loading.attr="disabled" wire:target="topupNow({{ $state->id }})">Top up now</x-ui.button>
                        <x-ui.button size="default" variant="secondary" wire:click="advance({{ $state->id }})" wire:loading.attr="disabled" wire:target="advance({{ $state->id }})">Advance a day</x-ui.button>
                    </div>
                </li>
            @endforeach
        </ul>
        <p class="text-sm text-ink-2 mt-4">A ladder ends when the balance is back and the engine resets it; that reset is not on this screen yet.</p>
    @endif
</div>
