<div>
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-gray-600">Your current website inventory.</p>
        <button wire:click="crawl" class="btn btn-primary">
            Crawl Website
        </button>
    </div>

    @if($pages->isEmpty())
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-6 text-center">
            <h2 class="text-sm font-medium text-gray-900">No pages found</h2>
            <p class="mt-1 text-sm text-gray-500">
                Pressing "Crawl Website" will fetch your website's home page and follow its internal links to build an inventory of your current content.
            </p>
        </div>
    @else
        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">Title</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">URL</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Images</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Fetched At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach($pages as $page)
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-900">
                                {{ $page->title ?? 'Untitled' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                <a href="{{ $page->url }}" target="_blank" class="text-indigo-600 hover:text-indigo-900">{{ $page->url }}</a>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                {{ is_array($page->image_urls) ? count($page->image_urls) : 0 }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                {{ $page->fetched_at ? $page->fetched_at->diffForHumans() : 'Never' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
