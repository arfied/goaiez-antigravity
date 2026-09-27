<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            Build My Site
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            
            <x-ui.toast kind="error" :message="$error" />
            <x-ui.toast kind="success" :message="$success" />

            <!-- STEP 1: Crawl -->
            <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg p-6 border border-line">
                <h3 class="text-lg font-medium text-ink">1. Crawl</h3>
                <p class="mt-1 text-sm text-ink-2">We analyze your current website to copy structure, text, and images.</p>
                
                <div class="mt-4">
                    <x-ui.button wire:click="runBuild" size="default">
                        Run Build Pipeline
                    </x-ui.button>
                </div>

                @if($buildStatus)
                    <div class="mt-4 p-4 border border-line rounded-md">
                        <p class="font-medium text-ink">Status: {{ $buildStatus }}</p>
                        @if($buildStatus === 'refused' && $buildReason === 'no_website')
                            <p class="text-red-600 mt-2">Add and confirm your website on <a href="{{ route('account.locations') }}" class="underline">Locations</a> first — the build reads it from there.</p>
                        @elseif($buildStatus === 'refused')
                            <p class="text-red-600 mt-2">Reason: {{ $buildReason }}</p>
                        @endif
                        
                        @if(!empty($ledger))
                            <div class="mt-4">
                                <h4 class="text-sm font-medium text-ink">Ledger:</h4>
                                <pre class="mt-2 text-xs text-ink-2 overflow-x-auto">{{ json_encode($ledger, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <!-- STEP 2: Draft -->
            <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg p-6 border border-line">
                <h3 class="text-lg font-medium text-ink">2. Draft</h3>
                <p class="mt-1 text-sm text-ink-2">Pages we drafted based on your inventory. Click to edit.</p>
                <p class="mt-1 text-sm text-ink-2">
                    Starting point: 
                    @if($industry['family'] !== null)
                        {{ $industry['family']->label() }} — {{ $industry['source'] === 'owner' ? 'you chose it' : 'from what Google says about you' }} · 
                        @if($unansweredQuestions > 0)
                            {{ $unansweredQuestions }} industry {{ $unansweredQuestions === 1 ? 'question' : 'questions' }} still unanswered — <a href="{{ route('account.facts') }}" class="underline">answer them</a> and the next draft uses them
                        @else
                            every industry question answered
                        @endif
                    @else 
                        the general one — Google gave us nothing to go on; <a href="{{ route('account.facts') }}" class="underline">pick your industry</a> and the draft follows it
                    @endif
                    .
                </p>
                
                @if($pages->isNotEmpty())
                    <ul class="mt-4 border-t border-line divide-y divide-line">
                        @foreach($pages as $page)
                            <li class="py-4 flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-ink">{{ $page->title }} ({{ $page->slug }})</p>
                                    <p class="text-xs text-ink-2">{{ count($page->draft_blocks ?? []) }} blocks drafted</p>
                                </div>
                                <div>
                                    <a href="{{ route('x-103.pages') }}" class="text-sm font-medium">Edit in Pages &rarr;</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-4 text-sm text-ink-2 italic">No pages drafted yet. Run the build pipeline first.</p>
                @endif
            </div>

                        <!-- STEP 3: Pick a look -->
            <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg p-6 border border-line">
                <h3 class="text-lg font-medium text-ink">3. Pick a look</h3>
                <p class="mt-1 text-sm text-ink-2">Three looks for your home page from your industry's starting point, drawn from the words you have now. Pictures show grey here; they are real on the live site. Pick one and every page follows it.</p>
                @if($previews === null)
                    <p class="mt-4 text-sm text-ink-2 italic">Draft the site first — there is nothing to show yet.</p>
                @else
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach(['a' => 'Look A', 'b' => 'Look B', 'c' => 'Look C'] as $k => $label)
                            <div class="rounded border {{ $chosenVariant === $k ? 'border-ink' : 'border-line' }} p-2" wire:key="look-{{ $k }}">
                                <iframe title="{{ $label }} preview" srcdoc="{{ $previews[$k] }}" sandbox="" loading="lazy" class="w-full h-64 bg-surface border border-line"></iframe>
                                <div class="mt-2 flex items-center justify-between">
                                    <span class="text-sm text-ink">{{ $label }}@if($chosenVariant === $k) — yours @endif</span>
                                    <x-ui.button wire:click="chooseLook('{{ $k }}')" size="default" variant="secondary">Pick this</x-ui.button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- STEP 4: Publish -->
            <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg p-6 border border-line">
                <h3 class="text-lg font-medium text-ink">4. Publish</h3>
                <p class="mt-1 text-sm text-ink-2">Publish all drafted pages to the platform address.</p>
                
                <div class="mt-4">
                    <x-ui.button wire:click="publishAll" size="default">
                        Publish All Pages
                    </x-ui.button>
                </div>
                
                @if($pages->isNotEmpty())
                    <ul class="mt-4 border-t border-line divide-y divide-line">
                        @foreach($pages as $page)
                            @if(isset($deployments[$page->id]) && $deployments[$page->id]->status === 'deployed')
                                <li class="py-4">
                                    <p class="text-sm font-medium text-ink">{{ $page->title }}</p>
                                    <a href="{{ url('/sites/'.$businessId.'/'.$deployments[$page->id]->deploy_hash) }}" target="_blank" class="text-xs hover:underline">
                                        {{ url('/sites/'.$businessId.'/'.$deployments[$page->id]->deploy_hash) }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </div>

            <!-- STEP 5: Your domain -->
            <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg p-6 border border-line">
                <h3 class="text-lg font-medium text-ink">5. Your domain</h3>
                <p class="mt-1 text-sm text-ink-2">Your site is live at {{ $domainStatus['platform_address'] }}. To use your own domain, enter it here — we record the request and the platform operator connects it and issues the certificate. Nothing on this screen can make that green on its own.</p>
                
                <div class="mt-4 flex space-x-4">
                    <input type="text" wire:model.defer="domainName" placeholder="e.g. example.com" class="shadow-sm block w-full sm:text-sm border-line rounded-md text-ink">
                    <x-ui.button wire:click="useDomain" size="default">
                        Use Domain
                    </x-ui.button>
                </div>
                
                @if($domainStatus['requested_domain'])
                    <div class="mt-6 p-4 border border-line rounded-md">
                        <p class="text-sm text-ink-2 mb-2">
                            Requested {{ $domainStatus['requested_domain'] }} on {{ \Carbon\Carbon::parse($domainStatus['requested_at'])->format('Y-m-d') }}
                        </p>
                        <p class="text-sm font-medium text-ink mb-2">
                            Point a CNAME for {{ $domainStatus['requested_domain'] }} at {{ $domainStatus['platform_address'] }}, then check.
                        </p>
                        <div class="flex items-center space-x-4 mb-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-paper-2 text-ink-2">
                                @if($domainStatus['status'] === 'requested')
                                    requested
                                @elseif($domainStatus['status'] === 'unverified')
                                    unverified: {{ $domainStatus['failure_reason'] === 'no_cname' ? 'no CNAME found' : 'points at ' . str_replace('points_elsewhere:', '', $domainStatus['failure_reason']) }}
                                @elseif($domainStatus['status'] === 'verified')
                                    verified {{ \Carbon\Carbon::parse($domainStatus['verified_at'])->format('Y-m-d H:i') }}
                                @else
                                    {{ $domainStatus['status'] }}
                                @endif
                            </span>
                            <x-ui.button wire:click="verifyDomain" size="default" variant="secondary">
                                Check
                            </x-ui.button>
                        </div>
                        @if($domainStatus['status'] === 'verified' && count($deployments) > 0)
                            <p class="text-sm text-green-600 mt-2">
                                Your site is live.
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <!-- STEP 6: This week's suggestions -->
            <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg p-6 border border-line">
                <h3 class="text-lg font-medium text-ink">6. This week's suggestions</h3>
                @if($proposed)
                    <p class="mt-2 text-sm text-ink">{{ $proposed }} <a href="{{ route('x-103.pages') }}" class="underline">Open Pages</a></p>
                @endif
                @if($recommendations->isEmpty())
                    <p class="mt-1 text-sm text-ink-2">Nothing to suggest this week — the site is reading what the platform measures.</p>
                @else
                    <ul class="mt-4 border-t border-line divide-y divide-line">
                        @foreach($recommendations as $r)
                            <li class="py-4 flex items-center justify-between">
                                <p class="text-sm text-ink">{{ $r->text }}</p>
                                <div class="flex gap-2">
                                    <x-ui.button wire:click="askRecommendation({{ $r->id }})" size="default">Ask the AI to do it</x-ui.button>
                                    <x-ui.button wire:click="dismissRecommendation({{ $r->id }})" size="default" variant="secondary">Dismiss</x-ui.button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <!-- STEP 7: Learn from the top 5 nearby -->
            <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg p-6 border border-line">
                <h3 class="text-lg font-medium text-ink">7. Learn from the top 5 nearby</h3>
                <p class="mt-1 text-sm text-ink-2">
                    @if($peers !== null && $peers->isMeasured())
                        {{ $peers->sentence() }}
                    @else
                        {{ $peers?->absenceSentence() ?? 'Not measured yet — nearby businesses are found on the nightly schedule once your Google listing is connected.' }}
                    @endif
                </p>
                @if($peerTopics['read'] > 0)
                    <p class="mt-2 text-sm text-ink">{{ $peerTopics['read'] }} nearby {{ $peerTopics['read'] === 1 ? 'site was' : 'sites were' }} read as reference. What they cover:</p>
                    <ul class="mt-2 flex flex-wrap gap-2">
                        @foreach($peerTopics['topics'] as $topic)
                            <li class="text-xs px-2 py-1 rounded bg-paper border border-line text-ink-2">{{ $topic }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-2 text-sm text-ink-2">No nearby site has been read yet.</p>
                @endif
                <p class="mt-3 text-xs text-ink-2">Reference only: the AI reads what nearby businesses cover to know what to cover for you. Nothing of theirs is copied — not their words, not their pictures.</p>
            </div>


            <!-- STEP 8: Headline test -->
            <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg p-6 border border-line">
                <h3 class="text-lg font-medium text-ink">8. Try a headline</h3>
                <p class="mt-1 text-sm text-ink-2">Two versions of your home page headline, shown to half your visitors each, measured on booking requests. Never a price, never your own edits, never your business name. One tap puts everything back.</p>
                @if($trial)
                    <p class="mt-2 text-sm text-ink">{{ $trial }}</p>
                @endif
                @if($frozen)
                    <p class="mt-2 text-sm text-ink-2">You kept your headline and marked it left-alone. Nothing will propose a change to it.</p>
                @elseif($variant)
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach(['control' => 'Yours', 'variant' => 'The other one'] as $arm => $label)
                            <div class="rounded border border-line p-4">
                                <p class="text-xs uppercase tracking-wide text-ink-2">{{ $label }}</p>
                                <p class="mt-1 text-sm text-ink font-medium">{{ $arm === 'control' ? $variant['control_headline'] : $variant['variant_headline'] }}</p>
                                <p class="mt-2 text-sm text-ink-2">
                                    Seen {{ $variantResult[$arm]->served }} times — {{ $variantResult[$arm]->requests }} booking {{ $variantResult[$arm]->requests === 1 ? 'request' : 'requests' }}
                                    @if($variantResult[$arm]->isMeasured())
                                        ({{ number_format($variantResult[$arm]->ratePerTenThousand / 100, 1) }} per hundred visits)
                                    @else
                                        — not enough visits yet to say
                                    @endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                    @if($variantResult['leader'] !== null)
                        <p class="mt-3 text-sm text-ink">So far {{ $variantResult['leader'] === 'variant' ? 'the other one' : 'yours' }} is ahead.</p>
                    @endif
                    <div class="mt-4 flex gap-2">
                        <x-ui.button wire:click="stopHeadlineTest({{ $variant['id'] }})" size="default" variant="secondary">Stop — back to mine</x-ui.button>
                        <x-ui.button wire:click="keepMineAndFreeze({{ $variant['id'] }})" size="default" variant="secondary">Keep mine and leave it alone</x-ui.button>
                    </div>
                @else
                    <div class="mt-4">
                        <x-ui.button wire:click="proposeHeadlines" size="default" variant="secondary">Ask the AI for two headlines</x-ui.button>
                        @if(count($headlineOptions) > 0)
                            <div class="mt-3">
                                @foreach($headlineOptions as $i => $h)
                                    <label class="block text-sm text-ink mb-1"><input type="radio" wire:model="headlineChoice" value="{{ $h }}"> {{ $h }}</label>
                                @endforeach
                            </div>
                        @endif
                        <label class="block mt-3 text-sm text-ink-2" for="own-headline">Or type your own</label>
                        <input id="own-headline" type="text" wire:model="ownHeadline" maxlength="120" class="mt-1 w-full rounded border border-line px-2 py-1 text-sm">
                        <div class="mt-3">
                            <x-ui.button wire:click="startHeadlineTest" size="default">Try it</x-ui.button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
