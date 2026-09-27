<div>
    <h2>Credit terms</h2>

    <p class="text-base text-ink-2">A commercial customer on terms keeps getting service past their limit: the card on file absorbs the overflow, and paying the invoice reverses it.</p>

    @if($error)
        <x-ui.error-panel heading="We couldn't set those terms">
            {{ $error }}
        </x-ui.error-panel>
    @endif

    <x-ui.toast kind="success" :message="$success" />

    <div wire:loading>
        <x-ui.skeleton label="Reading the terms…" />
    </div>

    @if($terms->isEmpty())
        <x-ui.empty-state heading="Nobody is on terms yet.">A commercial customer gets terms at their first invoice. Nothing in this checkout raises one from a completed job, and a draft is never issued, so no terms row is created yet.</x-ui.empty-state>
    @else
        <ul class="space-y-4">
            @foreach($terms as $term)
                <li class="border rounded p-4 shadow bg-card">
                    <div class="flex flex-wrap justify-between items-center gap-2">
                        <div>
                            <span class="font-semibold">{{ $term->customer_name }}</span>
                            <span class="text-sm text-ink-2 ml-2">{{ $term->label }}</span>
                        </div>
                        @if($term->headroom_cents > 0)
                            <x-ui.status-pill state="ok" :label="number_format($term->headroom_cents / 100, 2).' headroom'" />
                        @else
                            <x-ui.status-pill state="attention" label="over the limit" />
                        @endif
                    </div>
                    <dl class="mt-2 grid grid-cols-2 gap-2 text-sm tabular-nums">
                        <dt class="text-ink-2">Limit</dt><dd>{{ number_format($term->credit_limit_cents / 100, 2) }}</dd>
                        <dt class="text-ink-2">Outstanding</dt><dd>{{ number_format($term->current_outstanding_cents / 100, 2) }}</dd>
                        <dt class="text-ink-2">Overflow</dt><dd>{{ $term->overflow }}</dd>
                    </dl>
                    <form wire:submit="setTerms({{ $term->id }})" class="mt-3 flex flex-wrap items-center gap-2">
                        <select wire:model="termsType.{{ $term->id }}" class="border rounded px-2 py-1">
                            @foreach(\App\Modules\X199\Ui\Credits::LABELS as $value => $label)
                                <option value="{{ $value }}" @selected($value === $term->terms_type)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="number" min="0" step="1" wire:model="limit.{{ $term->id }}" placeholder="Limit, whole dollars" class="border rounded px-2 py-1 w-40">
                        <x-ui.submit target="setTerms({{ $term->id }})" busy="Saving…">Set terms</x-ui.submit>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</div>
