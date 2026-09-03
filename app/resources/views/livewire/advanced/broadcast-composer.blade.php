<div class="space-y-6 sm:space-y-8" x-data="{ messageText: 'Hi {First Name}, thanks for choosing us! We would love your quick feedback: {Link}', channel: 'sms' }">
    <div class="mb-8">
        <nav class="flex mb-2" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-300 hover:underline">Advanced</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="{{ route('advanced.broadcasts') }}" class="text-indigo-400 dark:text-indigo-400 hover:underline">Broadcasts</a></li>
                <li class="text-gray-400">/</li>
                <li class="text-gray-400 dark:text-gray-400">Composer</li>
            </ol>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">Compose Customer Broadcast <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Preview — not live</span></h1>
        <p class="mt-1 text-sm text-gray-400 dark:text-gray-400">Draft targeted SMS or email messages with dynamic merge tags, compliance opt-out, and live credit forecasting.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Form Column -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Campaign Parameters</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Campaign Title (Internal Reference)</label>
                        <input type="text" value="Summer Customer Check-in & Review Drive" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm px-3 py-2 border">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Channel</label>
                            <select x-model="channel" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm px-3 py-2 border">
                                <option value="sms">SMS Text Message (10DLC)</option>
                                <option value="email">Email Announcement</option>
                                <option value="multi">SMS + Email Fallback</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Target Audience</label>
                            <select class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm px-3 py-2 border">
                                <option>All Customers (Verified Consent) - 340 contacts</option>
                                <option>Customers visited in last 30 days - 86 contacts</option>
                                <option>5-Star Reviewers (VIP segment) - 142 contacts</option>
                                <option>Lapsed Customers (60+ days) - 112 contacts</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Message Content</label>
                            <div class="text-xs text-gray-400 dark:text-gray-400">
                                <span x-text="messageText.length"></span> chars · <span x-text="Math.ceil(messageText.length / 160) || 1"></span> segment(s)
                            </div>
                        </div>
                        <textarea x-model="messageText" rows="4" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm p-3 border font-sans"></textarea>
                        
                        <div class="mt-2 flex flex-wrap gap-2 text-xs">
                            <button type="button" @click="messageText += ' {First Name}'" class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600">+ First Name</button>
                            <button type="button" @click="messageText += ' {Business Name}'" class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600">+ Business Name</button>
                            <button type="button" @click="messageText += ' {Link}'" class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600">+ Short Link</button>
                            <button type="button" @click="messageText += ' Reply STOP to opt out.'" class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600">+ STOP wording</button>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <div class="text-xs text-gray-400 dark:text-gray-400">
                        🛡️ <strong>TCPA Compliance:</strong> Carrier quiet hours (9pm - 8am local) and automatic opt-out handling enforced.
                    </div>
                    <button type="button" class="inline-flex items-center px-5 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none">
                        Send Broadcast Now
                    </button>
                </div>
            </div>
        </div>

        <!-- Live Phone Preview Column -->
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider mb-4">Live Recipient Preview</h3>
                
                <!-- Phone Mockup Frame -->
                <div class="w-full max-w-[280px] mx-auto bg-gray-900 rounded-[2.5rem] p-3 shadow-xl border-4 border-gray-800">
                    <div class="bg-white dark:bg-gray-950 rounded-[2rem] p-4 min-h-[380px] flex flex-col justify-between">
                        <!-- Phone Header -->
                        <div class="text-center pb-2 border-b border-gray-100 dark:border-gray-800">
                            <div class="text-xs font-semibold text-gray-800 dark:text-gray-200">Verified Business SMS</div>
                            <div class="text-[10px] text-gray-400">10DLC Shortcode</div>
                        </div>

                        <!-- Bubble -->
                        <div class="my-auto space-y-2">
                            <div class="bg-indigo-600 text-white rounded-2xl rounded-tr-sm p-3 text-xs shadow-sm leading-relaxed" x-text="messageText.replace('{First Name}', 'Alex').replace('{Business Name}', 'Rachel Taylor').replace('{Link}', 'goai.ez/r9x2')">
                            </div>
                            <div class="text-[9px] text-gray-400 text-right">Delivered · Just now</div>
                        </div>

                        <!-- Phone Footer Input -->
                        <div class="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center gap-1">
                            <div class="h-6 flex-1 bg-gray-100 dark:bg-gray-800 rounded-full px-2 text-[10px] text-gray-400 flex items-center">iMessage / SMS</div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-900/50 rounded text-xs text-gray-600 dark:text-gray-400 space-y-1">
                    <div class="flex justify-between"><span>Recipients:</span> <strong>340 Contacts</strong></div>
                    <div class="flex justify-between"><span>Estimated Credit Cost:</span> <strong>340 Credits ($3.40)</strong></div>
                    <div class="flex justify-between"><span>Available Balance:</span> <strong>2,450 Credits</strong></div>
                </div>
            </div>
        </div>
    </div>
</div>