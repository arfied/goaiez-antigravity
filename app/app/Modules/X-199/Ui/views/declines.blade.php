<div>
    <div class="p-4 sm:p-6 lg:p-8 max-w-lg mx-auto">
        <div class="mb-8">
            <h1 class="text-xl font-semibold leading-6 text-gray-900">Payment Declines & Exceptions</h1>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div class="bg-white px-4 py-5 shadow sm:rounded-lg border border-gray-200">
                    <dt class="truncate text-sm font-medium text-gray-500">Declines this week</dt>
                    <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 tabular-nums">{{ $declinesCount }}</dd>
                </div>
                <div class="bg-white px-4 py-5 shadow sm:rounded-lg border border-gray-200">
                    <dt class="truncate text-sm font-medium text-gray-500">Recovered</dt>
                    <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 tabular-nums">{{ $recoveredCount }}</dd>
                </div>
            </div>
        </div>

        <div class="mt-8 flow-root">
            <div wire:loading class="w-full text-center p-4">
                <span class="text-gray-500 text-base">Loading...</span>
            </div>
            
            @if($declines->isEmpty())
                <div wire:loading.remove class="text-center p-8 bg-white rounded-lg border border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">No declines this week.</h3>
                    <button wire:click="toggleShowAll" class="mt-4 text-indigo-600 hover:text-indigo-900 text-base">Show all</button>
                </div>
            @else
                <div wire:loading.remove class="space-y-6">
                    @foreach($declines as $decline)
                        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 rounded-lg bg-white">
                            <div class="p-4 border-b border-gray-200">
                                <h3 class="text-base font-medium text-gray-900">the bank didn't authorise it — happens all the time</h3>
                                <p class="mt-1 text-sm text-gray-500">Amount: <span class="tabular-nums font-semibold">{{ number_format($decline->amount_cents / 100, 2) }} {{ $decline->currency }}</span> on {{ $decline->created_at->format('M j, Y g:i A') }}</p>
                                <p class="mt-1 text-sm text-gray-500">ID: {{ $decline->gateway_charge_id ?: 'no gateway id' }}</p>
                                @if($decline->recovered)
                                    <p class="mt-1 text-sm text-green-600">Recovered: {{ $decline->recovered->created_at->format('M j, Y g:i A') }}</p>
                                @else
                                    <p class="mt-1 text-sm text-red-600">Recovered: not yet</p>
                                @endif
                                <p class="mt-3 text-base text-gray-700">no decline code was recorded for this attempt — switch method</p>
                            </div>
                            <div class="bg-gray-50 px-4 py-4 sm:flex sm:flex-row-reverse sm:px-6">
                                <button wire:click="sendPayLink({{ $decline->id }})" class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-4 py-3 text-base font-semibold text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto">Send pay link</button>
                                <button wire:click="settleUpLater({{ $decline->id }})" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-4 py-3 text-base font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Settle up later</button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-6">
                    <button wire:click="toggleShowAll" class="text-indigo-600 hover:text-indigo-900 text-base">
                        {{ $showAll ? 'Show this week only' : 'Show all' }}
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
