<div>
    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto">
        <div class="mb-8 flex justify-between items-center">
            <h1 class="text-xl font-semibold leading-6 text-gray-900">Credits & Usage</h1>
            <button wire:click="topup" wire:loading.attr="disabled" class="inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Top Up
            </button>
        </div>

        <div wire:loading class="w-full text-center p-4">
            <span class="text-gray-500 text-sm">Loading...</span>
        </div>

        @if($error)
            <div role="alert" class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative text-sm">
                {{ $error }}
            </div>
        @endif

        @if($success)
            <div role="alert" class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative text-sm">
                {{ $success }}
            </div>
        @endif

        <div wire:loading.remove class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg border border-gray-200">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">AI Credits Balance</dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900 tabular-nums">
                        {{ number_format($aiBalance / 10000, 4) }}
                    </dd>
                </div>
            </div>
            
            @foreach(['sms' => 'SMS Segments', 'voice' => 'Voice Minutes', 'ai' => 'AI', 'email' => 'Email', 'lead' => 'Lead Credits'] as $type => $label)
                <div class="bg-white overflow-hidden shadow rounded-lg border border-gray-200">
                    <div class="px-4 py-5 sm:p-6">
                        <dt class="text-sm font-medium text-gray-500 truncate">{{ $label }}</dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900 tabular-nums">
                            {{ number_format(($meters[$type]->units_used ?? 0)) }}
                        </dd>
                        <dd class="text-xs text-gray-500 mt-1 tabular-nums">
                            Cost: {{ number_format(($meters[$type]->cost_hundredths_cents ?? 0) / 10000, 4) }}
                        </dd>
                    </div>
                </div>
            @endforeach
        </div>

        <div wire:loading.remove class="mt-8 flow-root">
            <h2 class="text-lg font-semibold leading-6 text-gray-900 mb-4">Ledger</h2>
            @if($entries->isEmpty())
                <div class="text-center p-8 bg-white rounded-lg border border-gray-200">
                    <h3 class="text-sm font-medium text-gray-900">No ledger entries yet.</h3>
                </div>
            @else
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Type</th>
                                <th scope="col" class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">Amount</th>
                                <th scope="col" class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">Balance After</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($entries as $entry)
                                <tr>
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-900 sm:pl-6">
                                        {{ $entry->entry_type }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 text-right tabular-nums">
                                        {{ number_format($entry->amount_hundredths_cents / 10000, 4) }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 text-right tabular-nums">
                                        {{ number_format($entry->balance_after_hundredths_cents / 10000, 4) }}
                                    </td>
                                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                        <button wire:click="explain({{ $entry->id }})" wire:loading.attr="disabled" class="text-indigo-600 hover:text-indigo-900">Explain</button>
                                    </td>
                                </tr>
                                @if($explainedEntryId === $entry->id && $explanation)
                                    <tr class="bg-gray-50">
                                        <td colspan="4" class="px-6 py-4">
                                            <p class="text-sm text-gray-900 font-medium mb-1">Explanation</p>
                                            <p class="text-sm text-gray-600">{{ $explanation['description'] }}</p>
                                            <p class="text-xs text-gray-500 mt-2">Ref: {{ $explanation['reference_id'] ?? 'none' }}</p>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
