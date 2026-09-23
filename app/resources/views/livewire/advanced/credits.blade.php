<div class="space-y-6 sm:space-y-8">
    <div class="mb-8">
        <nav class="flex mb-2" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                <li class="text-ink-2">/</li>
                <li class="text-ink-2">Platform Credits & Usage</li>
            </ol>
        </nav>
        <h1 class="text-2xl font-bold text-ink sm:text-3xl">Credits Ledger & Automated Refills <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-attention-bg text-attention">Preview — not live</span></h1>
        <p class="mt-1 text-sm text-ink-2">Manage real-time credit balances for high-throughput SMS broadcasts, AI voice synthesis, and direct carrier deliveries.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        @foreach($balances as $productValue => $balance)
        <!-- Balance Card -->
        <div class="bg-card border border-rule rounded-card shadow-card p-6">
            <div class="text-sm font-medium text-ink-2">Current {{ $balance['name'] }} Balance</div>
            <div class="mt-2 text-4xl font-extrabold text-ink">{{ $balance['formatted'] }}</div>
            <div class="mt-6">
                <a href="{{ route('account.credit') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 w-full justify-center">
                    Purchase Pack
                </a>
            </div>
        </div>
        @endforeach

        <!-- Auto-Refill Settings -->
        <div class="bg-card border border-rule rounded-card shadow-card p-6 md:col-span-3">
            <h2 class="text-lg font-bold text-ink mb-2">Automated Refill Safeguard</h2>
            <p class="text-xs text-ink-2 mb-4">Never let an outreach campaign stall. When your credit balance falls below the threshold, auto-refill triggers seamlessly.</p>
            
            @if($arrangement)
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 bg-paper border border-rule rounded-lg">
                        <div class="text-xs text-ink-2">Auto-Refill Threshold</div>
                        <div class="text-lg font-bold text-ink mt-1">{{ $arrangementThreshold }}</div>
                    </div>
                    <div class="p-4 bg-paper border border-rule rounded-lg">
                        <div class="text-xs text-ink-2">Refill Pack Size</div>
                        <div class="text-lg font-bold text-ink mt-1">{{ $arrangementAmount }}</div>
                    </div>
                    <div class="p-4 bg-paper border border-rule rounded-lg col-span-2">
                        <div class="text-xs text-ink-2">Status</div>
                        <div class="text-lg font-bold text-ink mt-1">{{ $arrangementStatus }}</div>
                    </div>
                </div>
            @else
                <div class="p-4 bg-paper border border-rule rounded-lg">
                    <div class="text-sm font-medium text-ink">No automatic refill set up.</div>
                </div>
            @endif
        </div>
    </div>
    
    <div class="bg-card border border-rule rounded-card shadow-card p-6">
        <h2 class="text-lg font-bold text-ink mb-4">Ledger</h2>
        @if($entries->isEmpty())
            <div class="p-4 bg-paper border border-rule rounded-lg">
                <div class="text-sm font-medium text-ink">No credit activity yet.</div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-rule">
                    <thead>
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-ink-2 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-ink-2 uppercase tracking-wider">Product</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-ink-2 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-ink-2 uppercase tracking-wider">Description</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-ink-2 uppercase tracking-wider">Amount</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-ink-2 uppercase tracking-wider">Balance After</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-rule">
                        @foreach($mappedEntries as $entry)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-ink">{{ $entry['date'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-ink">{{ $entry['product'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-ink">{{ $entry['type'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-ink">{{ $entry['description'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-ink">{{ $entry['amount'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-ink">{{ $entry['balance_after'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
