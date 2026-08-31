<div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-8">
    <div class="md:flex md:items-center md:justify-between mb-6">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs sm:text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Advanced</a></li>
                    <li class="text-gray-400">/</li>
                    <li class="text-gray-500 dark:text-gray-400">AI Website & Funnel Builder</li>
                </ol>
            </nav>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900 dark:text-white">Autonomous Local Website & Funnel Builder</h1>
            <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400">AI-generated landing pages with instant SMS lead dispatch, interactive before/after proof, and live reviews.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4 gap-2">
            <button wire:click="publishSite" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-xs sm:text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition">
                <span wire:loading.remove>🚀 Publish Website Live</span>
                <span wire:loading>Publishing Site...</span>
            </button>
        </div>
    </div>

    <!-- AI 1-Prompt Generator Bar -->
    <div class="bg-gradient-to-r from-indigo-900 to-indigo-800 text-white rounded-xl p-5 mb-6 shadow-md border border-indigo-700">
        <div class="flex items-center gap-2 mb-2">
            <span class="text-lg">🪄</span>
            <h2 class="text-sm font-bold uppercase tracking-wider text-indigo-200">1-Prompt AI Site Generator</h2>
        </div>
        <div class="flex flex-col sm:flex-row items-center gap-3">
            <input
                type="text"
                wire:model="aiPrompt"
                class="w-full text-xs sm:text-sm p-3 rounded-lg bg-indigo-950/80 border border-indigo-600 text-white placeholder-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                placeholder="Describe your business, specialty & location (e.g. 24/7 Emergency plumbing in Austin)..."
            />
            <button
                wire:click="generateWithAI"
                wire:loading.attr="disabled"
                class="w-full sm:w-auto px-5 py-3 rounded-lg bg-indigo-500 hover:bg-indigo-400 text-white font-bold text-xs sm:text-sm shrink-0 shadow transition flex items-center justify-center gap-1.5"
            >
                <span wire:loading.remove>✨ Generate Page</span>
                <span wire:loading>Writing Copy & SEO...</span>
            </button>
        </div>
    </div>

    @if ($aiNotification)
        <div class="mb-6 p-4 rounded-md bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-200 dark:border-indigo-800 text-indigo-900 dark:text-indigo-200 text-xs sm:text-sm flex items-center justify-between">
            <span>{{ $aiNotification }}</span>
            <button wire:click="$set('aiNotification', null)" class="text-indigo-600 hover:underline text-xs">Dismiss</button>
        </div>
    @endif

    @if ($leadDispatchNotification)
        <div class="mb-6 p-4 rounded-md bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200 text-xs sm:text-sm flex items-center justify-between">
            <span>{{ $leadDispatchNotification }}</span>
            <button wire:click="$set('leadDispatchNotification', null)" class="text-emerald-600 hover:underline text-xs">Dismiss</button>
        </div>
    @endif

    @if ($publishNotification)
        <div class="mb-6 p-4 rounded-md bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs sm:text-sm flex items-center justify-between">
            <span>{{ $publishNotification }}</span>
            <button wire:click="$set('publishNotification', null)" class="text-emerald-600 hover:underline text-xs">Dismiss</button>
        </div>
    @endif

    <!-- Builder Workspace Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
        <!-- Editor Controls (Left 4 cols) -->
        <div class="lg:col-span-4 space-y-5">
            <!-- Template & Industry -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 sm:p-5 border border-gray-200 dark:border-gray-700">
                <h3 class="text-xs sm:text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-2">Industry Template</h3>
                <select wire:model.live="template" class="w-full text-xs p-2.5 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                    <option value="modern_service">Modern Local Services (Contractors & Pro Services)</option>
                    <option value="healthcare_clean">Clean Medical / Dental / Wellness Clinic</option>
                    <option value="bold_contractor">Bold Trade Specialist (HVAC, Roofing, Plumbing)</option>
                    <option value="elegant_salon">Boutique Salon, Spa & Aesthetics</option>
                </select>
            </div>

            <!-- Content Customizer -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 sm:p-5 border border-gray-200 dark:border-gray-700 space-y-3.5">
                <h3 class="text-xs sm:text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">Hero Section & Copy</h3>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Main Headline</label>
                    <input type="text" wire:model.live.debounce.200ms="headline" class="w-full text-xs p-2.5 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Subheadline Description</label>
                    <textarea wire:model.live.debounce.200ms="subheadline" rows="3" class="w-full text-xs p-2.5 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Call to Action</label>
                        <input type="text" wire:model.live.debounce.200ms="ctaText" class="w-full text-xs p-2.5 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Direct Phone Call</label>
                        <input type="text" wire:model.live.debounce.200ms="phoneNumber" class="w-full text-xs p-2.5 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white font-mono">
                    </div>
                </div>
            </div>

            <!-- Dynamic Conversion Modules -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 sm:p-5 border border-gray-200 dark:border-gray-700 space-y-2.5">
                <h3 class="text-xs sm:text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-2">High-Conversion Modules</h3>

                <label class="flex items-center justify-between text-xs text-gray-700 dark:text-gray-300 cursor-pointer p-1.5 rounded hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <span>⚡ Instant Booking & Quote Form (with SMS alerts)</span>
                    <input type="checkbox" wire:model.live="showBookingForm" class="rounded border-gray-300 text-indigo-600">
                </label>

                <label class="flex items-center justify-between text-xs text-gray-700 dark:text-gray-300 cursor-pointer p-1.5 rounded hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <span>↔️ Interactive Before & After Photo Slider</span>
                    <input type="checkbox" wire:model.live="showBeforeAfter" class="rounded border-gray-300 text-indigo-600">
                </label>

                <label class="flex items-center justify-between text-xs text-gray-700 dark:text-gray-300 cursor-pointer p-1.5 rounded hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <span>⭐ Embed Live Google Reviews Feed</span>
                    <input type="checkbox" wire:model.live="showReviewsWidget" class="rounded border-gray-300 text-indigo-600">
                </label>

                <label class="flex items-center justify-between text-xs text-gray-700 dark:text-gray-300 cursor-pointer p-1.5 rounded hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <span>📱 Floating Mobile Speed-Dial Bar</span>
                    <input type="checkbox" wire:model.live="showStickySpeedDial" class="rounded border-gray-300 text-indigo-600">
                </label>

                <label class="flex items-center justify-between text-xs text-gray-700 dark:text-gray-300 cursor-pointer p-1.5 rounded hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <span>❓ Auto-Generated FAQ Accordion</span>
                    <input type="checkbox" wire:model.live="showFaqSection" class="rounded border-gray-300 text-indigo-600">
                </label>
            </div>
        </div>

        <!-- Live Visual Preview (Right 8 cols) -->
        <div class="lg:col-span-8">
            <!-- Viewport Switcher Toolbar -->
            <div class="bg-gray-100 dark:bg-gray-800 p-2.5 rounded-t-xl border border-b-0 border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <div class="flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                    <span class="h-2.5 w-2.5 rounded-full bg-yellow-400"></span>
                    <span class="h-2.5 w-2.5 rounded-full bg-green-400"></span>
                    <span class="text-[11px] text-gray-500 font-mono ms-2 truncate max-w-[150px] sm:max-w-none">https://{{ Str::slug($businessName) }}.goaiez.com</span>
                </div>
                <div class="flex items-center gap-1">
                    <button wire:click="$set('previewDevice', 'desktop')" class="px-2.5 py-1 text-xs rounded-md transition {{ $previewDevice === 'desktop' ? 'bg-white dark:bg-gray-700 text-indigo-600 font-bold shadow-xs' : 'text-gray-500 hover:text-gray-700' }}">🖥️ Desktop</button>
                    <button wire:click="$set('previewDevice', 'mobile')" class="px-2.5 py-1 text-xs rounded-md transition {{ $previewDevice === 'mobile' ? 'bg-white dark:bg-gray-700 text-indigo-600 font-bold shadow-xs' : 'text-gray-500 hover:text-gray-700' }}">📱 Mobile Mockup</button>
                </div>
            </div>

            <!-- Preview Canvas Shell -->
            <div class="bg-slate-100 dark:bg-slate-950 p-3 sm:p-8 rounded-b-xl border border-gray-200 dark:border-gray-700 flex justify-center min-h-[700px] overflow-hidden items-start">
                
                @if ($previewDevice === 'mobile')
                    <!-- Realistic Smartphone Mockup Frame -->
                    <div class="relative w-[340px] sm:w-[375px] rounded-[44px] border-[10px] border-slate-900 dark:border-slate-800 bg-white shadow-2xl overflow-hidden min-h-[660px] max-h-[720px] flex flex-col ring-1 ring-slate-900/10">
                        <!-- Dynamic Island / Speaker Notch -->
                        <div class="h-6 bg-slate-900 w-full flex items-center justify-center shrink-0">
                            <div class="h-3.5 w-24 bg-black rounded-full"></div>
                        </div>

                        <!-- Scrollable Mobile Content -->
                        <div class="overflow-y-auto flex-1 text-gray-900 pb-16">
                            <!-- Mobile Header -->
                            <header class="border-b border-gray-100 px-4 py-3 flex items-center justify-between sticky top-0 bg-white/95 backdrop-blur z-20">
                                <div class="font-display text-xs font-bold text-gray-900 flex items-center gap-1.5">
                                    <span class="h-5 w-5 rounded bg-indigo-600 text-white font-black text-[10px] flex items-center justify-center">AI</span>
                                    <span class="truncate max-w-[130px]">{{ $businessName }}</span>
                                </div>
                                <a href="#quote" class="px-2.5 py-1 rounded bg-indigo-600 text-white text-[11px] font-semibold">
                                    {{ $ctaText }}
                                </a>
                            </header>

                            <!-- Mobile Hero -->
                            <section class="px-4 py-8 text-center bg-gradient-to-b from-indigo-50/60 to-white">
                                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-bold mb-3 border border-emerald-200">
                                    <span>★ 4.9 Rating</span>
                                    <span>•</span>
                                    <span>Verified Local</span>
                                </div>

                                <h2 class="text-lg font-extrabold text-gray-900 leading-tight">
                                    {{ $headline }}
                                </h2>

                                <p class="text-xs text-gray-600 mt-2 leading-relaxed">
                                    {{ $subheadline }}
                                </p>

                                <div class="mt-4 flex flex-col gap-2">
                                    <button class="w-full py-2.5 rounded-lg bg-indigo-600 text-white text-xs font-bold shadow-sm">
                                        {{ $ctaText }} →
                                    </button>
                                </div>
                            </section>

                            <!-- Mobile Services -->
                            <section class="px-4 py-5 border-t border-gray-100 space-y-2">
                                @foreach ($services as $service)
                                    <div class="p-3 rounded-lg bg-gray-50 border border-gray-100 flex items-start gap-2.5">
                                        <span class="text-base">{{ $service['icon'] }}</span>
                                        <div>
                                            <div class="font-bold text-xs text-gray-900">{{ $service['title'] }}</div>
                                            <div class="text-[10px] text-gray-500">{{ $service['desc'] }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </section>

                            @if ($showBeforeAfter)
                                <!-- Mobile Before / After Slider -->
                                <section class="px-4 py-6 bg-slate-50 border-t border-gray-100">
                                    <div class="text-center mb-3">
                                        <div class="font-bold text-xs text-gray-900">Before & After Results</div>
                                        <div class="text-[10px] text-gray-500">Real verified job-site transformations</div>
                                    </div>

                                    <div class="relative rounded-lg overflow-hidden border border-gray-200 shadow-sm bg-gray-100 p-4 text-center">
                                        <div class="grid grid-cols-2 gap-2 text-xs font-bold mb-2">
                                            <span class="p-2 bg-rose-50 text-rose-700 rounded border border-rose-200">Before Inspection</span>
                                            <span class="p-2 bg-emerald-50 text-emerald-700 rounded border border-emerald-200">After Complete Work</span>
                                        </div>
                                        <input type="range" min="0" max="100" wire:model.live="sliderPosition" class="w-full accent-indigo-600 cursor-pointer">
                                        <div class="text-[10px] text-gray-500 mt-1">Slide to compare transformation ({{ $sliderPosition }}%)</div>
                                    </div>
                                </section>
                            @endif

                            @if ($showBookingForm)
                                <!-- Mobile Lead Form -->
                                <section id="quote" class="px-4 py-6 bg-indigo-50 border-t border-gray-100 text-left">
                                    <div class="mb-3">
                                        <div class="font-bold text-xs text-gray-900">Request Instant Estimate</div>
                                        <div class="text-[10px] text-gray-600">Get a response within 60 seconds via SMS</div>
                                    </div>

                                    <div class="space-y-2 text-xs">
                                        <input type="text" wire:model="leadName" placeholder="Your Name" class="w-full p-2 text-xs rounded border border-gray-300 bg-white">
                                        <input type="text" wire:model="leadPhone" placeholder="Mobile Phone" class="w-full p-2 text-xs rounded border border-gray-300 bg-white">
                                        <button wire:click="simulateLeadSubmission" class="w-full py-2.5 rounded bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700">
                                            Send Estimate Request (Simulate SMS)
                                        </button>
                                    </div>
                                </section>
                            @endif

                            @if ($showReviewsWidget)
                                <!-- Mobile Reviews -->
                                <section class="px-4 py-5 bg-white border-t border-gray-100">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="font-bold text-xs text-gray-900">Verified Google Reviews</div>
                                        <div class="text-[10px] font-bold text-emerald-600">★★★★★ 4.9</div>
                                    </div>
                                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-100 text-[10px] text-gray-600">
                                        "Outstanding service! Fast response and flawless execution." — <strong>Michael R.</strong>
                                    </div>
                                </section>
                            @endif

                            <!-- Mobile Footer -->
                            <footer class="bg-gray-900 text-white px-4 py-6 text-center text-[10px]">
                                <div class="font-bold">{{ $businessName }}</div>
                                <div class="text-gray-400 mt-0.5">{{ $phoneNumber }}</div>
                                <div class="text-gray-500 mt-2 text-[9px]">Powered by GO AI EZ</div>
                            </footer>
                        </div>

                        @if ($showStickySpeedDial)
                            <!-- Floating Mobile Sticky Speed-Dial Bar -->
                            <div class="absolute bottom-4 inset-x-3 bg-white/95 backdrop-blur-md p-2 rounded-xl shadow-lg border border-gray-200 flex items-center justify-between gap-2 z-30">
                                <a href="tel:{{ $phoneNumber }}" class="flex-1 py-2 px-3 rounded-lg bg-emerald-600 text-white text-[11px] font-bold text-center flex items-center justify-center gap-1">
                                    <span>📞 Call Now</span>
                                </a>
                                <a href="#quote" class="flex-1 py-2 px-3 rounded-lg bg-indigo-600 text-white text-[11px] font-bold text-center flex items-center justify-center gap-1">
                                    <span>💬 Text Us</span>
                                </a>
                            </div>
                        @endif

                        <!-- Mobile Home Bar Indicator -->
                        <div class="h-4 bg-slate-900 w-full flex items-center justify-center shrink-0">
                            <div class="h-1 w-28 bg-slate-600 rounded-full"></div>
                        </div>
                    </div>
                @else
                    <!-- Desktop Browser Canvas -->
                    <div class="w-full bg-white text-gray-900 rounded-lg shadow-xl overflow-y-auto max-h-[800px]">
                        <!-- Desktop Header -->
                        <header class="border-b border-gray-100 px-6 py-4 flex items-center justify-between sticky top-0 bg-white/95 backdrop-blur z-20">
                            <div class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                                <span class="h-7 w-7 rounded bg-indigo-600 text-white font-black text-xs flex items-center justify-center">AI</span>
                                <span>{{ $businessName }}</span>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="text-xs font-mono font-semibold text-gray-600">{{ $phoneNumber }}</span>
                                <a href="#quote-desktop" class="px-3.5 py-1.5 rounded-md bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition">
                                    {{ $ctaText }}
                                </a>
                            </div>
                        </header>

                        <!-- Desktop Hero -->
                        <section class="px-6 py-12 text-center bg-gradient-to-b from-indigo-50/50 to-white">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold mb-4 border border-emerald-200">
                                <span>★ 4.9 Rating</span>
                                <span>•</span>
                                <span>Verified Local Provider</span>
                            </div>

                            <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 max-w-xl mx-auto leading-tight">
                                {{ $headline }}
                            </h2>

                            <p class="text-sm text-gray-600 max-w-lg mx-auto mt-3">
                                {{ $subheadline }}
                            </p>

                            <div class="mt-6 flex flex-wrap justify-center gap-3">
                                <a href="#quote-desktop" class="px-5 py-2.5 rounded-md bg-indigo-600 text-white text-xs font-bold shadow-md hover:bg-indigo-700">
                                    {{ $ctaText }} →
                                </a>
                                <button class="px-5 py-2.5 rounded-md bg-white border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50">
                                    Call {{ $phoneNumber }}
                                </button>
                            </div>
                        </section>

                        <!-- Services Grid -->
                        <section class="px-6 py-10 border-t border-gray-100">
                            <div class="grid grid-cols-3 gap-4 text-left">
                                @foreach ($services as $service)
                                    <div class="p-4 rounded-lg bg-gray-50 border border-gray-100">
                                        <div class="text-xl mb-1">{{ $service['icon'] }}</div>
                                        <div class="font-bold text-xs text-gray-900">{{ $service['title'] }}</div>
                                        <div class="text-[11px] text-gray-500 mt-1">{{ $service['desc'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </section>

                        @if ($showBeforeAfter)
                            <!-- Before & After Transformation Slider Section -->
                            <section class="px-6 py-10 bg-slate-50 border-t border-gray-100 text-center">
                                <h3 class="font-bold text-base text-gray-900 mb-1">Recent Job Transformations</h3>
                                <p class="text-xs text-gray-500 mb-6">Interactive Before & After project verification</p>

                                <div class="max-w-xl mx-auto bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                                    <div class="grid grid-cols-2 gap-4 mb-4">
                                        <div class="p-4 rounded-lg bg-rose-50 border border-rose-200">
                                            <div class="text-xs font-bold text-rose-700 mb-1">❌ BEFORE</div>
                                            <div class="text-xs text-rose-900">Corroded pipe joints & water pressure drop</div>
                                        </div>
                                        <div class="p-4 rounded-lg bg-emerald-50 border border-emerald-200">
                                            <div class="text-xs font-bold text-emerald-700 mb-1">✅ AFTER</div>
                                            <div class="text-xs text-emerald-900">Seamless copper replacement & restored flow</div>
                                        </div>
                                    </div>
                                    <input type="range" min="0" max="100" wire:model.live="sliderPosition" class="w-full accent-indigo-600 cursor-pointer">
                                    <div class="text-xs text-gray-500 mt-2 font-mono">Comparison Slider Position: {{ $sliderPosition }}%</div>
                                </div>
                            </section>
                        @endif

                        @if ($showBookingForm)
                            <!-- Instant Quote & SMS Lead Dispatch Section -->
                            <section id="quote-desktop" class="px-6 py-10 bg-indigo-50/50 border-t border-gray-100">
                                <div class="max-w-xl mx-auto text-center">
                                    <h3 class="font-bold text-base text-gray-900 mb-1">Request Free Estimate</h3>
                                    <p class="text-xs text-gray-600 mb-4">Submit below to test instant owner SMS dispatch & customer confirmation</p>

                                    <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-3 text-left">
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-[11px] font-semibold text-gray-700 mb-1">Name</label>
                                                <input type="text" wire:model="leadName" class="w-full text-xs p-2 rounded border border-gray-300">
                                            </div>
                                            <div>
                                                <label class="block text-[11px] font-semibold text-gray-700 mb-1">Phone</label>
                                                <input type="text" wire:model="leadPhone" class="w-full text-xs p-2 rounded border border-gray-300 font-mono">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-semibold text-gray-700 mb-1">Service Needed</label>
                                            <input type="text" wire:model="leadService" class="w-full text-xs p-2 rounded border border-gray-300">
                                        </div>
                                        <button wire:click="simulateLeadSubmission" class="w-full py-2.5 rounded bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow transition">
                                            ⚡ Submit Estimate (Trigger Real-Time SMS Alert)
                                        </button>
                                    </div>
                                </div>
                            </section>
                        @endif

                        @if ($showReviewsWidget)
                            <!-- Reviews Showcase -->
                            <section class="px-6 py-10 bg-white border-t border-gray-100 text-left">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h3 class="font-bold text-sm text-gray-900">Recent Verified Google Reviews</h3>
                                        <p class="text-[11px] text-gray-500">Real feedback from clients in your local area</p>
                                    </div>
                                    <div class="text-xs font-bold text-emerald-600">★★★★★ 4.9 (128 reviews)</div>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="p-3.5 bg-gray-50 rounded-lg border border-gray-200 shadow-xs">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="font-bold text-xs text-gray-900">Michael R.</span>
                                            <span class="text-amber-500 text-xs">★★★★★</span>
                                        </div>
                                        <p class="text-[11px] text-gray-600">"Outstanding service! Came on short notice, explained everything clearly, and got the job done flawlessly."</p>
                                    </div>
                                    <div class="p-3.5 bg-gray-50 rounded-lg border border-gray-200 shadow-xs">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="font-bold text-xs text-gray-900">David & Elena S.</span>
                                            <span class="text-amber-500 text-xs">★★★★★</span>
                                        </div>
                                        <p class="text-[11px] text-gray-600">"Prompt, professional, and reliable. Will definitely be using {{ $businessName }} for all future work!"</p>
                                    </div>
                                </div>
                            </section>
                        @endif

                        @if ($showFaqSection)
                            <!-- FAQ Accordion -->
                            <section class="px-6 py-8 border-t border-gray-100 text-left">
                                <h3 class="font-bold text-sm text-gray-900 mb-3">Frequently Asked Questions</h3>
                                <div class="space-y-2 text-xs">
                                    @foreach ($faqs as $faq)
                                        <div class="p-3 rounded bg-gray-50 border border-gray-100">
                                            <div class="font-semibold text-gray-900">{{ $faq['q'] }}</div>
                                            <div class="text-gray-600 text-[11px] mt-0.5">{{ $faq['a'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        <!-- Footer -->
                        <footer class="bg-gray-900 text-white px-6 py-8 text-center text-xs">
                            <div class="font-bold text-sm">{{ $businessName }}</div>
                            <div class="text-gray-400 text-[11px] mt-1">{{ $phoneNumber }} • All Rights Reserved</div>
                            <div class="text-[10px] text-gray-500 mt-3">Powered by GO AI EZ</div>
                        </footer>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
