<div>
    <div class="p-4">
        <h2 class="text-xl font-bold text-ink">Create an offer for your customers</h2>
        <p class="mt-1 text-base text-ink-2">Every offer has a limit on how many times it can be used, and it stops once it reaches it.</p>

        @if ($saved !== '')
            <p class="mt-3 text-base text-ink" role="status">
                {{ $saved }} is saved and running.
                <a href="{{ route('x-210.active-promotions') }}" class="underline">See the offers running now</a>
            </p>
        @endif

        <form wire:submit="save" class="mt-4">
            <label for="offer-code" class="block text-base text-ink">The code your customers type</label>
            <input
                id="offer-code"
                type="text"
                wire:model="code"
                placeholder="SPRING20"
                class="mt-1 w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
            />
            @if ($errors->has('code'))
                <p class="mt-1 text-base text-alert" role="alert">{{ $errors->first('code') }}</p>
            @endif

            <label for="offer-type" class="mt-4 block text-base text-ink">How it takes money off</label>
            <select
                id="offer-type"
                wire:model="discountType"
                class="mt-1 w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
            >
                <option value="percentage">A percent off</option>
                <option value="fixed_cents">A dollar amount off</option>
            </select>
            @if ($errors->has('discountType'))
                <p class="mt-1 text-base text-alert" role="alert">{{ $errors->first('discountType') }}</p>
            @endif

            <label for="offer-amount" class="mt-4 block text-base text-ink">How much it takes off, as a whole percent or in dollars</label>
            <input
                id="offer-amount"
                type="text"
                inputmode="decimal"
                wire:model="amount"
                placeholder="20"
                class="mt-1 w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
            />
            @if ($errors->has('amount'))
                <p class="mt-1 text-base text-alert" role="alert">{{ $errors->first('amount') }}</p>
            @endif

            <label for="offer-uses" class="mt-4 block text-base text-ink">How many times it can be used in all</label>
            <input
                id="offer-uses"
                type="text"
                inputmode="numeric"
                wire:model="maxUses"
                placeholder="50"
                class="mt-1 w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
            />
            @if ($errors->has('maxUses'))
                <p class="mt-1 text-base text-alert" role="alert">{{ $errors->first('maxUses') }}</p>
            @endif

                        <label for="offer-window" class="mt-4 block text-base text-ink">Measurement Window (Days)</label>
            <input
                id="offer-window"
                type="text"
                inputmode="numeric"
                wire:model="measurementWindowDays"
                class="mt-1 w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
            />
            @if ($errors->has('measurementWindowDays'))
                <p class="mt-1 text-base text-alert" role="alert">{{ $errors->first('measurementWindowDays') }}</p>
            @endif

            <div class="mt-4">
                <x-ui.submit target="save" busy="Saving…">Save offer</x-ui.submit>
            </div>
        </form>
    </div>
</div>
