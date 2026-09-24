<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            Build My Site
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            
            @if($error)
                <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
                    <p class="text-red-700">{{ $error }}</p>
                </div>
            @endif

            <!-- STEP 1: Crawl -->
            <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg p-6 border border-line">
                <h3 class="text-lg font-medium text-ink">1. Crawl</h3>
                <p class="mt-1 text-sm text-ink-2">We analyze your current website to copy structure, text, and images.</p>
                
                <div class="mt-4">
                    <button wire:click="runBuild" class="btn btn-primary">
                        Run Build Pipeline
                    </button>
                </div>

                @if($buildStatus)
                    <div class="mt-4 p-4 border border-line rounded-md">
                        <p class="font-medium text-ink">Status: {{ $buildStatus }}</p>
                        @if($buildStatus === 'refused')
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

            <!-- STEP 3: Publish -->
            <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg p-6 border border-line">
                <h3 class="text-lg font-medium text-ink">3. Publish</h3>
                <p class="mt-1 text-sm text-ink-2">Publish all drafted pages to the platform address.</p>
                
                <div class="mt-4">
                    <button wire:click="publishAll" class="btn btn-primary">
                        Publish All Pages
                    </button>
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

            <!-- STEP 4: Your domain -->
            <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg p-6 border border-line">
                <h3 class="text-lg font-medium text-ink">4. Your domain</h3>
                <p class="mt-1 text-sm text-ink-2">Your site is live at {{ $domainStatus['platform_address'] }}. To use your own domain, enter it here — we record the request and the platform operator connects it and issues the certificate. Nothing on this screen can make that green on its own.</p>
                
                <div class="mt-4 flex space-x-4">
                    <input type="text" wire:model.defer="domainName" placeholder="e.g. example.com" class="shadow-sm block w-full sm:text-sm border-line rounded-md text-ink">
                    <button wire:click="useDomain" class="btn btn-primary">
                        Use Domain
                    </button>
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
                            <button wire:click="verifyDomain" class="btn btn-secondary text-sm">
                                Check
                            </button>
                        </div>
                        @if($domainStatus['status'] === 'verified' && count($deployments) > 0)
                            <p class="text-sm text-green-600 mt-2">
                                Your site is live.
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <!-- STEP 5: This week's suggestions -->
            <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg p-6 border border-line">
                <h3 class="text-lg font-medium text-ink">5. This week's suggestions</h3>
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
                                    <button wire:click="askRecommendation({{ $r->id }})" class="btn btn-primary text-sm">Ask the AI to do it</button>
                                    <button wire:click="dismissRecommendation({{ $r->id }})" class="btn btn-secondary text-sm">Dismiss</button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

        </div>
    </div>
</div>
