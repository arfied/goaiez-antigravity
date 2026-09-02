<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <nav class="flex mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
                    <li><a href="{{ route('advanced.home') }}" class="text-indigo-400 hover:text-indigo-800">Advanced</a></li>
                    <li class="text-gray-400">/</li>
                    <li class="text-gray-500 dark:text-gray-400">Citations</li>
                </ol>
            </nav>
            <h1 class="text-2xl font-bold leading-7 text-gray-900 dark:text-white sm:text-3xl">
                NAP Consistency & Local Directory Citations
             <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Preview — not live</span></h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Search engines require exact Name, Address, and Phone (NAP) match across all directories to rank your business locally.
            </p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <button wire:click="runScan" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                <span wire:loading.remove>Scan Directories Now</span>
                <span wire:loading>Scanning Directories...</span>
            </button>
        </div>
    </div>

    @if ($scanMessage)
        <div class="mb-6 p-4 rounded-md bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-sm">
            {{ $scanMessage }}
        </div>
    @endif

    <!-- Health Summary -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-4 mb-8">
        <div class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500">Overall Health</div>
            <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $healthPercentage }}%</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500">Consistent</div>
            <div class="text-3xl font-bold text-emerald-600">{{ $consistentCount }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500">Mismatches</div>
            <div class="text-3xl font-bold text-amber-600">{{ $mismatchCount }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500">Missing Listings</div>
            <div class="text-3xl font-bold text-rose-600">{{ $missingCount }}</div>
        </div>
    </div>

    <!-- Citations Table -->
    <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Directory</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Listing Details</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">NAP Status</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Checked</th>
                    <th scope="col" class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($citations as $citation)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-medium text-gray-900 dark:text-white">{{ $citation->directory }}</div>
                            @if ($citation->directory_url)
                                <a href="{{ $citation->directory_url }}" target="_blank" class="text-xs text-indigo-400 dark:text-indigo-400 hover:underline">View Listing &rarr;</a>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            <div><strong>Name:</strong> {{ $citation->listing_name ?? 'N/A' }}</div>
                            <div><strong>Phone:</strong> {{ $citation->listing_phone ?? 'N/A' }}</div>
                            <div><strong>Address:</strong> {{ $citation->listing_address ?? 'N/A' }}</div>
                            @if ($citation->mismatch_details)
                                <div class="mt-1 text-xs text-amber-700 dark:text-amber-400 font-medium">
                                    Issue: {{ json_encode($citation->mismatch_details) }}
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if ($citation->nap_status === 'consistent')
                                <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">
                                    Consistent
                                </span>
                            @elseif ($citation->nap_status === 'mismatch')
                                <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300">
                                    Mismatch
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-300">
                                    Missing
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ $citation->last_checked_at ? $citation->last_checked_at->diffForHumans() : 'Never' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            @if ($citation->nap_status !== 'consistent')
                                <button wire:click="markResolved({{ $citation->id }})" class="text-indigo-400 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 text-xs font-semibold">
                                    Mark Fixed
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>