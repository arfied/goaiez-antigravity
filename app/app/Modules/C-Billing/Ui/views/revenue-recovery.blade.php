<div>
    <h2 class="text-lg font-bold text-ink">Revenue recovery</h2>

    <x-ui.attention-card state="attention" heading="One account at a time">
        A cross-account roll-up is an operator view behind row-level security, and no cross-account read path is built in this checkout yet. Below is this account's ladder.
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
        <x-ui.empty-state heading="No ladder on this screen yet.">This screen reads its own dunning ladder, and nothing in this checkout writes to it yet. A missed payment on your subscription is retried on a separate schedule that this screen does not read, so an account can be in that schedule while this list is empty.</x-ui.empty-state>
    @else
        <ul class="space-y-4">
            @foreach($states as $state)
                <li class="bg-card overflow-hidden shadow rounded-[--radius-card] border border-rule p-4">
                    <div class="flex flex-wrap justify-between items-center gap-2">
                        <span class="font-semibold">Day {{ $state->day_in_cycle }} of 21</span>
                        <x-ui.status-pill :state="$state->day_in_cycle >= 21 ? 'attention' : 'ok'" :label="$dunningLabels[$state->status] ?? $state->status" />
                    </div>
                    <p class="text-sm text-ink-2 mt-1">Ladder setting: {{ $state->stays_on }} — recorded on this row and not applied anywhere yet; the phone and the AI are switched by other modules, which do not read it.</p>
                    <dl class="mt-2 grid grid-cols-2 gap-2 text-sm tabular-nums">
                        <dt class="text-ink-2">At risk</dt>
                        <dd>@if($monthly['cents'] === null) no agreed price recorded on the row @else {{ number_format($monthly['cents'] / 100, 2) }} a month @endif</dd>
                        <dt class="text-ink-2">Credit added</dt>
                        <dd>credit of {{ number_format($state->recovered_cents / 100, 2) }} added since the ladder started</dd>
                    </dl>
                    <p class="text-sm text-ink-2 mt-2">No money is counted here: the top-up on this row grants credit and takes no card, so no payment against the arrears is recorded anywhere in this module.</p>
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
