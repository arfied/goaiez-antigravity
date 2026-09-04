<div class="max-w-4xl mx-auto space-y-6">
    <!-- Portal Header & Customer Info -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 backdrop-blur-sm shadow-xl space-y-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        Zero-Credential Magic Portal (X-172)
                    </span>
                    <span class="text-xs text-slate-400">Token ID: <strong class="font-mono text-slate-300">{{ substr($token, 0, 10) }}...</strong></span>
                </div>
                <h1 class="text-2xl font-bold text-white mt-1">Customer Service & Approval Portal</h1>
                <p class="text-xs text-slate-400">Direct self-service approval, digital authorization, payments, and real-time appointment tracking.</p>
            </div>

            <!-- Customer Badge -->
            <div class="text-right sm:text-right">
                <div class="text-xs font-semibold text-white">{{ $customerName }}</div>
                <div class="text-[11px] text-slate-400">{{ $serviceAddress }}</div>
            </div>
        </div>

        @if($statusMessage)
            <div class="p-3.5 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-xs flex items-center justify-between">
                <span>{{ $statusMessage }}</span>
                <button wire:click="$set('statusMessage', null)" class="text-slate-400 hover:text-white">&times;</button>
            </div>
        @endif

        <!-- Tab Navigation -->
        <div class="flex items-center gap-2 border-b border-slate-800 pb-3 overflow-x-auto text-xs">
            <button wire:click="$set('activeTab', 'estimate')" class="px-3.5 py-1.5 rounded-lg font-medium transition {{ $activeTab === 'estimate' ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-400 hover:bg-slate-700' }}">
                1. Estimate Review
            </button>
            <button wire:click="$set('activeTab', 'sign')" class="px-3.5 py-1.5 rounded-lg font-medium transition {{ $activeTab === 'sign' ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-400 hover:bg-slate-700' }}">
                2. Electronic Signature {{ $isSigned ? '✓' : '' }}
            </button>
            <button wire:click="$set('activeTab', 'pay')" class="px-3.5 py-1.5 rounded-lg font-medium transition {{ $activeTab === 'pay' ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-400 hover:bg-slate-700' }}">
                3. Secure Payment {{ $isPaid ? '✓' : '' }}
            </button>
            <button wire:click="$set('activeTab', 'schedule')" class="px-3.5 py-1.5 rounded-lg font-medium transition {{ $activeTab === 'schedule' ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-400 hover:bg-slate-700' }}">
                4. Dispatch Time
            </button>
            <button wire:click="$set('activeTab', 'feedback')" class="px-3.5 py-1.5 rounded-lg font-medium transition {{ $activeTab === 'feedback' ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-400 hover:bg-slate-700' }}">
                5. Feedback
            </button>
        </div>

        <!-- TAB 1: Estimate Review -->
        @if($activeTab === 'estimate')
            <div class="space-y-5">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs text-slate-400 font-mono">Estimate #{{ $estimateNumber }}</span>
                        <h3 class="text-base font-bold text-white mt-0.5">{{ $jobTitle }}</h3>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $isSigned ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' }}">
                        {{ $isSigned ? 'Approved & Signed' : 'Awaiting Approval' }}
                    </span>
                </div>

                <!-- Line Items Table -->
                <div class="rounded-xl border border-slate-800 overflow-hidden">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-950/80 text-slate-400 uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="p-3">Service Description</th>
                                <th class="p-3 text-center">Qty</th>
                                <th class="p-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 bg-slate-900/40">
                            @foreach($lineItems as $item)
                                <tr>
                                    <td class="p-3 text-slate-200 font-medium">{{ $item['name'] }}</td>
                                    <td class="p-3 text-center text-slate-400">{{ $item['qty'] }}</td>
                                    <td class="p-3 text-right font-mono text-white">${{ number_format(($item['price'] * $item['qty']) / 100, 2) }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-slate-950/60 font-bold">
                                <td colspan="2" class="p-3 text-right text-slate-300">Total Estimate (USD):</td>
                                <td class="p-3 text-right font-mono text-emerald-400 text-sm">${{ number_format($estimateTotalCents / 100, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-2">
                    <button wire:click="$set('activeTab', 'sign')" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow-lg shadow-indigo-600/30 transition">
                        Proceed to Electronic Signature &rarr;
                    </button>
                </div>
            </div>
        @endif

        <!-- TAB 2: Electronic Signature Pad -->
        @if($activeTab === 'sign')
            <div class="space-y-5">
                <div>
                    <h3 class="text-base font-bold text-white">Digital Document Authorization</h3>
                    <p class="text-xs text-slate-400 mt-1">
                        By typing your full legal name below, you execute this agreement electronically under the U.S. Electronic Signatures in Global and National Commerce Act (E-SIGN).
                    </p>
                </div>

                @if($isSigned)
                    <div class="p-5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 space-y-2">
                        <div class="flex items-center gap-2 text-emerald-400 font-bold text-xs">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Document Signed & Sealed
                        </div>
                        <div class="text-xs text-slate-300">Signer: <strong class="text-white">{{ $signatureTyped }}</strong></div>
                        <div class="text-[11px] text-slate-400">Timestamp: {{ $signedAt }}</div>
                    </div>
                @else
                    <div class="p-5 rounded-xl bg-slate-950 border border-slate-800 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                                Type Full Legal Name as Signature <span class="text-rose-400">*</span>
                            </label>
                            <input 
                                type="text" 
                                wire:model.defer="signatureTyped" 
                                placeholder="e.g. Sarah Jenkins" 
                                class="w-full px-4 py-3 rounded-xl bg-slate-900 border border-slate-800 text-white font-serif italic text-base placeholder-slate-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                            />
                        </div>
                        
                        <div class="text-[11px] text-slate-400 leading-relaxed">
                            Explanation: I hereby authorize the plumbing dispatch team to perform the diagnostic & repair services itemized above for <strong>${{ number_format($estimateTotalCents / 100, 2) }}</strong>.
                        </div>

                        <button 
                            wire:click="approveAndSign" 
                            class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow-lg shadow-indigo-600/30 transition flex items-center justify-center gap-2"
                        >
                            Approve & Execute Signature &rarr;
                        </button>
                    </div>
                @endif
            </div>
        @endif

        <!-- TAB 3: Secure Card Payment -->
        @if($activeTab === 'pay')
            <div class="space-y-5">
                <div>
                    <h3 class="text-base font-bold text-white">Online Payment Terminal</h3>
                    <p class="text-xs text-slate-400 mt-1">PCI-DSS Level 1 compliant gateway processing via Stripe.</p>
                </div>

                @if($isPaid)
                    <div class="p-5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 space-y-2">
                        <div class="flex items-center gap-2 text-emerald-400 font-bold text-xs">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Invoice Paid in Full
                        </div>
                        <div class="text-xs text-slate-300">Amount Paid: <strong class="text-white">${{ number_format($estimateTotalCents / 100, 2) }}</strong></div>
                        <div class="text-[11px] text-slate-400">Card: •••• {{ $cardLast4 }} · Paid: {{ $paidAt }}</div>
                    </div>
                @else
                    <div class="p-5 rounded-xl bg-slate-950 border border-slate-800 space-y-4">
                        <div class="flex items-center justify-between text-xs border-b border-slate-800 pb-3">
                            <span class="text-slate-400">Total Due Today:</span>
                            <span class="text-base font-bold font-mono text-emerald-400">${{ number_format($estimateTotalCents / 100, 2) }}</span>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Card Number</label>
                                <div class="px-3 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-xs text-slate-300 font-mono flex items-center justify-between">
                                    <span>•••• •••• •••• {{ $cardLast4 }}</span>
                                    <span class="text-indigo-400 font-bold">VISA</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Exp Date</label>
                                    <div class="px-3 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-xs text-slate-300 font-mono">12 / 28</div>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">CVC</label>
                                    <div class="px-3 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-xs text-slate-300 font-mono">•••</div>
                                </div>
                            </div>
                        </div>

                        <button 
                            wire:click="submitPayment" 
                            class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs shadow-lg shadow-emerald-600/30 transition flex items-center justify-center gap-2"
                        >
                            Pay ${{ number_format($estimateTotalCents / 100, 2) }} Now &rarr;
                        </button>
                    </div>
                @endif
            </div>
        @endif

        <!-- TAB 4: Dispatch Schedule -->
        @if($activeTab === 'schedule')
            <div class="space-y-5">
                <div>
                    <h3 class="text-base font-bold text-white">Technician Dispatch & ETA</h3>
                    <p class="text-xs text-slate-400 mt-1">Live routing window allocated for your repair.</p>
                </div>

                <div class="p-5 rounded-xl bg-slate-950 border border-slate-800 space-y-4 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Scheduled Arrival Window:</span>
                        <span class="font-bold text-white">{{ $selectedSlot }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Assigned Technician:</span>
                        <span class="font-bold text-indigo-400">John M. (Master Plumber #402)</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Status:</span>
                        <span class="px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 font-medium">En Route / GPS Tracked</span>
                    </div>
                </div>
            </div>
        @endif

        <!-- TAB 5: Rating & Feedback -->
        @if($activeTab === 'feedback')
            <div class="space-y-5">
                <div>
                    <h3 class="text-base font-bold text-white">How was your service?</h3>
                    <p class="text-xs text-slate-400 mt-1">Your feedback helps us maintain our quality standards.</p>
                </div>

                @if($isFeedbackSubmitted)
                    <div class="p-5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-300">
                        Thank you for your rating of {{ $feedbackRating }}★! We appreciate your business.
                    </div>
                @else
                    <div class="p-5 rounded-xl bg-slate-950 border border-slate-800 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Select Rating</label>
                            <div class="flex items-center gap-2">
                                @foreach([1, 2, 3, 4, 5] as $star)
                                    <button 
                                        type="button" 
                                        wire:click="$set('feedbackRating', {{ $star }})" 
                                        class="text-2xl transition {{ $star <= $feedbackRating ? 'text-amber-400 scale-110' : 'text-slate-700 hover:text-amber-300' }}"
                                    >
                                        ★
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Your Feedback</label>
                            <textarea 
                                wire:model.defer="feedbackComment" 
                                rows="3" 
                                placeholder="Share details about your service experience..." 
                                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 text-xs text-white placeholder-slate-500 focus:border-indigo-500"
                            ></textarea>
                        </div>

                        <button 
                            wire:click="submitRating" 
                            class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs transition"
                        >
                            Submit Feedback &rarr;
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
