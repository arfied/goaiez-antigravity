<div>
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-ink-2">Your current website inventory.</p>
        <button wire:click="crawl" class="btn btn-primary">
            Crawl Website
        </button>
    </div>

    @if($pages->isEmpty())
        <div class="rounded-lg border border-rule bg-paper p-6 text-center">
            <h2 class="text-sm font-medium text-ink">No pages found</h2>
            <p class="mt-1 text-sm text-ink-2">
                Pressing "Crawl Website" will fetch your website's home page and follow its internal links to build an inventory of your current content.
            </p>
        </div>
    @else
        <div class="overflow-hidden shadow ring-1 ring-rule ring-opacity-5 sm:rounded-lg">
            <table class="min-w-full divide-y divide-rule">
                <thead class="bg-paper">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-ink">Title</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">URL</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Images</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Fetched At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-rule bg-paper">
                    @foreach($pages as $page)
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-ink">
                                {{ $page->title ?? 'Untitled' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">
                                <a href="{{ $page->url }}" target="_blank" class="text-indigo-600 hover:text-indigo-900">{{ $page->url }}</a>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">
                                {{ $page->images_count }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">
                                {{ $page->fetched_at ? $page->fetched_at->diffForHumans() : 'Never' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="mt-8 mb-4 flex items-center justify-between">
        <p class="text-sm text-ink-2">Images found on your website.</p>
        <button wire:click="copyImages" class="btn btn-primary">
            Copy Images
        </button>
    </div>

    @if($images->isEmpty())
        <div class="rounded-lg border border-rule bg-paper p-6 text-center">
            <h2 class="text-sm font-medium text-ink">No images stored</h2>
            <p class="mt-1 text-sm text-ink-2">
                Pressing "Copy Images" will fetch images from your inventory and store them.
            </p>
        </div>
    @else
        <div class="overflow-hidden shadow ring-1 ring-rule ring-opacity-5 sm:rounded-lg mb-8">
            <table class="min-w-full divide-y divide-rule">
                <thead class="bg-paper">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-ink">Host</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Status</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-rule bg-paper">
                    @php
                        $groupedImages = $images->groupBy(function($img) {
                            return $img->attribution . '|' . $img->status . '|' . $img->refusal_reason;
                        });
                    @endphp
                    @foreach($groupedImages as $group)
                        @php
                            $first = $group->first();
                            $reasonEnum = $first->refusal_reason ? \App\Enums\FetchRefusalReason::tryFrom($first->refusal_reason) : null;
                            $reasonText = $first->refusal_reason;
                            if ($reasonEnum) {
                                if ($reasonEnum->namesTheOriginsOwnRule()) {
                                    $reasonText = 'The origin\'s own robots.txt was read and refuses this path.';
                                } elseif ($reasonEnum->isThisPlatformsOwnDoing()) {
                                    $reasonText = 'This platform decided the refusal out of its own configuration and its own ledger, with nothing about the origin consulted.';
                                } else {
                                    $reasonText = 'The origin\'s robots.txt could not be obtained or parsed, so no rule of theirs was read at all.';
                                }
                            } elseif ($first->status === 'failed') {
                                $reasonText = 'Failed: ' . $first->refusal_reason;
                            }
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-ink">
                                {{ $first->attribution }} ({{ $group->count() }})
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">
                                {{ ucfirst($first->status) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">
                                @if($first->status === 'refused' || $first->status === 'failed')
                                    {{ $reasonText }}
                                @else
                                    Stored successfully
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @php $storedImages = $images->where('status', 'stored')->sortBy('id'); @endphp
        @if($storedImages->isNotEmpty())
            <p class="text-sm text-ink-2 mb-2">Describe each stored picture in a few words — a screen reader says this instead of the picture, and the next draft carries it.</p>
            <table class="min-w-full divide-y divide-rule mb-8">
                <thead class="bg-paper">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-ink">Picture</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Description</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-rule bg-paper">
                    @foreach($storedImages as $img)
                        <tr wire:key="alt-{{ $img->id }}">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-ink">{{ basename(parse_url($img->source_url, PHP_URL_PATH) ?? $img->source_url) }}</td>
                            <td class="px-3 py-4 text-sm text-ink-2"><label class="sr-only" for="alt-{{ $img->id }}">Description for {{ basename(parse_url($img->source_url, PHP_URL_PATH) ?? $img->source_url) }}</label><input id="alt-{{ $img->id }}" type="text" maxlength="160" wire:model="alts.{{ $img->id }}" class="w-full rounded border border-rule px-2 py-1 text-sm"></td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm"><button type="button" wire:click="saveAlt({{ $img->id }})" class="btn btn-primary">Save</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    <div class="mt-8 mb-4 flex items-center justify-between">
        <p class="text-sm text-ink-2">Your draft pages.</p>
        <button wire:click="draftSite" class="btn btn-primary">
            Draft Site
        </button>
    </div>

    @if($draftPages->isEmpty())
        <div class="rounded-lg border border-rule bg-paper p-6 text-center">
            <h2 class="text-sm font-medium text-ink">No draft pages</h2>
            <p class="mt-1 text-sm text-ink-2">
                Pressing "Draft Site" will build draft pages from your inventory.
            </p>
        </div>
    @else
        <div class="overflow-hidden shadow ring-1 ring-rule ring-opacity-5 sm:rounded-lg mb-8">
            <table class="min-w-full divide-y divide-rule">
                <thead class="bg-paper">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-ink">Title</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Slug</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Link</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-rule bg-paper">
                    @foreach($draftPages as $page)
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-ink">
                                {{ $page->title }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">
                                {{ $page->slug }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">
                                <a href="{{ route('x-103.pages') }}" class="text-indigo-600 hover:text-indigo-900">View Page</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="mt-8 mb-4">
        <h2>Your opening hours</h2>
        <table class="min-w-full divide-y divide-rule mt-4">
            <thead class="bg-paper">
                <tr>
                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-ink">Day</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Open</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Close</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Closed</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-rule bg-paper">
                @foreach($hours as $i => $row)
                    <tr>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-ink">{{ $row['day'] }}</td>
                        <td class="whitespace-nowrap px-3 py-4"><input type="time" wire:model="hours.{{ $i }}.open"></td>
                        <td class="whitespace-nowrap px-3 py-4"><input type="time" wire:model="hours.{{ $i }}.close"></td>
                        <td class="whitespace-nowrap px-3 py-4"><input type="checkbox" wire:model="hours.{{ $i }}.closed"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">
            <button wire:click="saveHours" class="btn btn-primary">Save hours</button>
            <p class="text-sm text-ink-2 mt-2">Shown in the contact section of every drafted page.</p>
        </div>
    </div>

    <div class="mt-8 mb-4">
        <h2>What your site is still missing</h2>
        @if (count($missing) === 0)
            <p class="text-sm text-ink-2 mt-2">Nothing — every section of the draft has something to show.</p>
        @else
            <p class="text-sm text-ink-2 mt-2">The draft leaves a section out when it cannot find the fact behind it. Fill these in and draft again.</p>
            <table class="min-w-full divide-y divide-rule mt-4">
                <thead class="bg-paper">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-ink">Missing</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Why it matters</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-ink">Where to add it</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-rule bg-paper">
                    @foreach ($missing as $row)
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-ink">{{ $row['label'] }}</td>
                            <td class="px-3 py-4 text-sm text-ink-2">{{ $row['hint'] }}</td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-ink-2">
                                @if ($row['route'] !== null)
                                    <a href="{{ route($row['route']) }}" class="text-indigo-600 hover:text-indigo-900">Open</a>
                                @else
                                    On this page
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
