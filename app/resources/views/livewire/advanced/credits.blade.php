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
        <!-- Balance Card -->
        <div class="bg-card border border-rule rounded-card shadow-card p-6">
            <div class="text-sm font-medium text-ink-2">Current Credit Balance</div>
            <div class="mt-2 text-4xl font-extrabold text-ink">2,450</div>
            <div class="text-xs text-ink-2 mt-1">Equivalent to ~2,450 SMS segments</div>
            <div class="mt-6">
                <a href="{{ route('account.credit') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 w-full justify-center">
                    Purchase Credit Pack
                </a>
            </div>
        </div>

        <!-- Auto-Refill Settings -->
        <div class="bg-card border border-rule rounded-card shadow-card p-6 col-span-2">
            <h2 class="text-lg font-bold text-ink mb-2">Automated Refill Safeguard</h2>
            <p class="text-xs text-ink-2 mb-4">Never let an outreach campaign stall. When your credit balance falls below the threshold, auto-refill triggers seamlessly.</p>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 bg-paper border border-rule rounded-lg">
                    <div class="text-xs text-ink-2">Auto-Refill Threshold</div>
                    <div class="text-lg font-bold text-ink mt-1">200 Credits</div>
                </div>
                <div class="p-4 bg-paper border border-rule rounded-lg">
                    <div class="text-xs text-ink-2">Refill Pack Size</div>
                    <div class="text-lg font-bold text-ink mt-1">1,000 Credits ($10.00)</div>
                </div>
            </div>
        </div>
    </div>
</div>