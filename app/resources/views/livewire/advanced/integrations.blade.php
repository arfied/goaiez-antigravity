<div class="space-y-6 sm:space-y-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Advanced</a></li>
                    <li class="text-ink-2">/</li>
                    <li class="text-ink-2">POS & Invoicing Integrations</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">Point-of-Sale & Invoicing Review Triggers <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-400">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-ink-2">Automatically trigger review requests within minutes of customer invoice payment or checkout.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <button wire:click="triggerTestPayment" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                <span wire:loading.remove>⚡ Simulate Test Payment</span>
                <span wire:loading>Simulating Event...</span>
            </button>
        </div>
    </div>

    @if ($testNotification)
        <div class="mb-6 p-4 rounded-md bg-ok-bg border border-ok text-emerald-600 dark:text-emerald-400 text-sm flex items-center justify-between">
            <span>{{ $testNotification }}</span>
            <button wire:click="$set('testNotification', null)" class="text-emerald-600 dark:text-emerald-400 hover:underline text-xs">Dismiss</button>
        </div>
    @endif

    <!-- Integrations Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Stripe Card -->
        <div class="bg-card shadow rounded-lg p-6 border border-rule flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div class="font-bold text-lg text-ink flex items-center gap-2">
                        <span class="p-1.5 rounded bg-indigo-600 text-white font-black text-xs">S</span>
                        <span>Stripe Payments</span>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">Active</span>
                </div>
                <p class="text-xs text-ink-2">
                    Triggers automated feedback asks upon successful <code>charge.succeeded</code> or customer invoice settlement.
                </p>
            </div>
            <div class="mt-6 pt-4 border-t border-rule flex justify-between items-center text-xs">
                <span class="text-ink-2">Last Synced: 5 mins ago</span>
                <button class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">Configure</button>
            </div>
        </div>

        <!-- QuickBooks Card -->
        <div class="bg-card shadow rounded-lg p-6 border border-rule flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div class="font-bold text-lg text-ink flex items-center gap-2">
                        <span class="p-1.5 rounded bg-emerald-800 text-white font-bold text-xs">QB</span>
                        <span>QuickBooks Online</span>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Ready</span>
                </div>
                <p class="text-xs text-ink-2">
                    Dispatches review asks when paid receipts or closed job work orders are recorded in QuickBooks.
                </p>
            </div>
            <div class="mt-6 pt-4 border-t border-rule flex justify-between items-center text-xs">
                <span class="text-ink-2">Not Connected</span>
                <button class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">Connect Account</button>
            </div>
        </div>

        <!-- Square Card -->
        <div class="bg-card shadow rounded-lg p-6 border border-rule flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div class="font-bold text-lg text-ink flex items-center gap-2">
                        <span class="p-1.5 rounded bg-gray-900 text-white font-black text-xs">SQ</span>
                        <span>Square POS</span>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Ready</span>
                </div>
                <p class="text-xs text-ink-2">
                    Connects in-person terminal card swipes, register receipts, and appointment checkouts.
                </p>
            </div>
            <div class="mt-6 pt-4 border-t border-rule flex justify-between items-center text-xs">
                <span class="text-ink-2">Not Connected</span>
                <button class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">Connect Account</button>
            </div>
        </div>
    </div>

    <!-- Webhook URL & Configuration -->
    <div class="bg-card shadow rounded-lg p-6 border border-rule">
        <h2 class="text-lg font-bold text-ink mb-2">Custom POS & CRM Webhook Endpoint</h2>
        <p class="text-xs text-ink-2 mb-4">Post custom JSON payloads from any billing software or custom CRM to trigger instant review outreach.</p>

        <div class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-ink-2 mb-1">Your Dedicated Webhook URL</label>
                <div class="flex items-center gap-2">
                    <input aria-label="Your Dedicated Webhook URL" type="text" readonly value="{{ $webhookUrl }}" class="w-full font-mono text-xs p-2.5 rounded-md border border-rule-strong bg-paper text-ink select-all">
                    <button type="button" class="px-4 py-2 bg-paper text-ink-2 text-xs font-semibold rounded-md hover:bg-rule">Copy</button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-semibold text-ink-2 mb-1">Dispatch Cadence After Payment</label>
                    <select aria-label="Trigger Timing" wire:model.live="triggerTiming" class="w-full text-xs p-2.5 rounded-md border border-rule-strong bg-card text-ink">
                        <option value="instant">Immediate (Within 60 seconds)</option>
                        <option value="15m">15 Minutes Delay (Recommended for service completion)</option>
                        <option value="2h">2 Hours Delay (Post-appointment window)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-ink-2 mb-1">Carrier TCPA Quiet Hours Guard</label>
                    <div class="p-2.5 rounded-md bg-paper text-xs text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-2">
                        <span>🛡️ Enforced: Payments after 9:00 PM automatically hold until 8:00 AM</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
