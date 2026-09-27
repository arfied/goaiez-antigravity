<div>
    <h2 class="text-lg font-bold text-ink">Invoice thread</h2>

    @if($error)
        <x-ui.error-panel heading="We couldn't record that">
            {{ $error }}
        </x-ui.error-panel>
    @endif

    <x-ui.toast kind="success" :message="$success" />

    <div wire:loading>
        <x-ui.skeleton label="Reading the thread…" />
    </div>

    @if(! $invoice)
        <x-ui.empty-state heading="Nothing unpaid.">No invoice is open for this account. Nothing in this checkout raises one from a completed job, and a draft is never issued, so nothing reaches this screen yet.</x-ui.empty-state>
    @else
        @if($invoices->count() > 1)
            <ul class="flex flex-wrap gap-2 mb-4">
                @foreach($invoices as $other)
                    <li>
                        <x-ui.button wire:click="pick({{ $other->id }})" variant="{{ $other->id === $invoice->id ? 'secondary' : 'quiet' }}" size="default">{{ $other->invoice_number }}</x-ui.button>
                    </li>
                @endforeach
            </ul>
        @endif

        @if($escalation)
            <x-ui.attention-card state="alert" heading="This one needs a human">
                {{ $escalation->reason }} — recorded {{ $escalation->created_at->diffForHumans() }}. Nothing is sent from here: this module has no reminder sequence and no way to contact anyone.
            </x-ui.attention-card>
        @endif

        <div class="grid gap-4 md:grid-cols-2 mt-4">
            <section class="border rounded p-4 shadow bg-card">
                <div class="flex justify-between items-center mb-2">
                    <span class="font-semibold">{{ $invoice->invoice_number }}</span>
                    <x-ui.status-pill :state="$invoice->days_overdue > 0 ? 'attention' : 'ok'" :label="$invoice->days_overdue > 0 ? $invoice->days_overdue.' days overdue' : 'not yet due'" />
                </div>
                <p class="text-sm text-ink-2">Due {{ $invoice->due_date->toDateString() }} · {{ number_format($invoice->balance_cents / 100, 2) }} owed of {{ number_format($invoice->total_cents / 100, 2) }}</p>
                @if($customer)
                    <p class="text-sm text-ink-2">{{ $customer['first_name'] ?? '' }} {{ $customer['last_name'] ?? '' }}</p>
                @endif
                <ul class="mt-3 space-y-1">
                    @foreach($lines as $line)
                        <li class="flex justify-between text-sm"><span>{{ $line->description }} × {{ $line->quantity }}</span><span class="tabular-nums">{{ number_format($line->subtotal_cents / 100, 2) }}</span></li>
                    @endforeach
                </ul>
            </section>

            <section class="border rounded p-4 shadow bg-card">
                <h3 class="font-semibold text-ink">The thread</h3>
                @if($messages->isEmpty())
                    <x-ui.empty-state heading="No messages yet.">Nothing has been said with {{ $customer ? ($customer['first_name'] ?? '') : 'this customer' }} on any channel.</x-ui.empty-state>
                @else
                    @if($threadTruncated)
                        <p class="text-sm text-ink-2">The 50 most recent messages are shown. Older messages in this conversation are not on this page.</p>
                    @endif
                    <ul class="space-y-2">
                        @foreach($messages as $message)
                            <li class="text-sm">
                                <x-ui.status-pill :state="$message->direction === 'inbound' ? 'attention' : 'ok'" :label="$message->direction === 'inbound' ? 'they said' : 'we said'" />
                                <span class="ml-2">{{ $message->body }}</span>
                                <span class="block text-ink-3">{{ $message->created_at->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <section class="mt-4 border rounded p-4 shadow bg-card">
            <h3 class="font-semibold text-ink">Why is it unpaid?</h3>
            <form wire:submit="recordReason({{ $invoice->id }})" class="flex flex-wrap items-center gap-2">
                <select wire:model="reason.{{ $invoice->id }}" class="border rounded px-2 py-1">
                    <option value="">Pick a reason</option>
                    @foreach($reasons as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-ui.submit target="recordReason({{ $invoice->id }})" busy="Recording…">Record why</x-ui.submit>
            </form>

            @if($actions->isNotEmpty())
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach($actions as $act)
                        <li>{{ $act->reason }} <span class="text-ink-3">· {{ $actionLabels[$act->action] ?? $act->action }} · {{ $act->created_at->diffForHumans() }}</span></li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</div>
