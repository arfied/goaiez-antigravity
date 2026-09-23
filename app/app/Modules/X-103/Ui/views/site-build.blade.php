<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
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
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">1. Crawl</h3>
                <p class="mt-1 text-sm text-gray-500">We analyze your current website to copy structure, text, and images.</p>
                
                <div class="mt-4">
                    <button wire:click="runBuild" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                        Run Build Pipeline
                    </button>
                </div>

                @if($buildStatus)
                    <div class="mt-4 p-4 bg-gray-50 rounded-md">
                        <p class="font-medium text-gray-900">Status: {{ $buildStatus }}</p>
                        @if($buildStatus === 'refused')
                            <p class="text-red-600 mt-2">Reason: {{ $buildReason }}</p>
                        @endif
                        
                        @if(!empty($ledger))
                            <div class="mt-4">
                                <h4 class="text-sm font-medium">Ledger:</h4>
                                <pre class="mt-2 text-xs text-gray-600 overflow-x-auto">{{ json_encode($ledger, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <!-- STEP 2: Draft -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">2. Draft</h3>
                <p class="mt-1 text-sm text-gray-500">Pages we drafted based on your inventory. Click to edit.</p>
                
                @if($pages->isNotEmpty())
                    <ul class="mt-4 border-t border-gray-200 divide-y divide-gray-200">
                        @foreach($pages as $page)
                            <li class="py-4 flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $page->title }} ({{ $page->slug }})</p>
                                    <p class="text-xs text-gray-500">{{ count($page->draft_blocks ?? []) }} blocks drafted</p>
                                </div>
                                <div>
                                    <a href="{{ route('x-103.pages') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">Edit in Pages &rarr;</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-4 text-sm text-gray-500 italic">No pages drafted yet. Run the build pipeline first.</p>
                @endif
            </div>

            <!-- STEP 3: Publish -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">3. Publish</h3>
                <p class="mt-1 text-sm text-gray-500">Publish all drafted pages to the platform address.</p>
                
                <div class="mt-4">
                    <button wire:click="publishAll" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700">
                        Publish All Pages
                    </button>
                </div>
                
                @if($pages->isNotEmpty())
                    <ul class="mt-4 border-t border-gray-200 divide-y divide-gray-200">
                        @foreach($pages as $page)
                            @if(isset($deployments[$page->id]) && $deployments[$page->id]->status === 'deployed')
                                <li class="py-4">
                                    <p class="text-sm font-medium text-gray-900">{{ $page->title }}</p>
                                    <a href="{{ url('/sites/'.$businessId.'/'.$deployments[$page->id]->deploy_hash) }}" target="_blank" class="text-xs text-indigo-600 hover:underline">
                                        {{ url('/sites/'.$businessId.'/'.$deployments[$page->id]->deploy_hash) }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </div>

            <!-- STEP 4: Your domain -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">4. Your domain</h3>
                <p class="mt-1 text-sm text-gray-500">Connect your custom domain to serve the published pages.</p>
                
                <div class="mt-4 flex space-x-4">
                    <input type="text" wire:model.defer="domainName" placeholder="e.g. example.com" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <button wire:click="useDomain" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                        Use Domain
                    </button>
                </div>
                
                @if($zone)
                    <div class="mt-6 p-4 bg-gray-50 rounded-md">
                        <h4 class="text-sm font-medium text-gray-900">DNS Instructions</h4>
                        <p class="mt-2 text-sm text-gray-600">
                            Your current site is untouched until these records point here. 
                            To complete setup, configure the following DNS records for <strong>{{ $zone->domain_name }}</strong>:
                        </p>
                        <ul class="mt-2 list-disc list-inside text-sm text-gray-600">
                            <li><strong>CNAME</strong> for <code>www</code> pointing to <code>sites.goaiez.com</code></li>
                            <li><strong>A</strong> for <code>@</code> pointing to <code>192.0.2.1</code></li>
                        </ul>
                        <p class="mt-2 text-sm text-gray-600">
                            SSL is {{ $zone->has_valid_ssl ? 'valid and ready' : 'pending' }}.
                        </p>
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>
