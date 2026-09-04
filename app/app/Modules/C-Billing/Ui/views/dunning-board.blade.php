<div>
    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-xl font-semibold leading-6 text-gray-900">Dunning Board</h1>
            <p class="mt-2 text-sm text-gray-700">Dunning Timeline</p>
        </div>

        <div wire:loading class="w-full text-center p-4">
            <span class="text-gray-500 text-sm">Loading...</span>
        </div>

        @if($error)
            <div role="alert" class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative text-sm">
                {{ $error }}
            </div>
        @endif

        <div wire:loading.remove class="mt-8 flow-root">
            @if($states->isEmpty())
                <div class="text-center p-8 bg-white rounded-lg border border-gray-200">
                    <h3 class="text-sm font-medium text-gray-900">No account is in dunning — nothing to chase.</h3>
                    <p class="mt-1 text-sm text-gray-500">Declines live on the Money screen.</p>
                </div>
            @else
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Day in Cycle</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Next Step</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($states as $state)
                                <tr>
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                                        Day {{ $state->day_in_cycle }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        {{ $state->next_step_words }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        {{ $state->status }}
                                    </td>
                                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                        <button wire:click="advance({{ $state->id }})" wire:loading.attr="disabled" class="text-indigo-600 hover:text-indigo-900 px-3 py-2 border border-gray-300 rounded-md text-sm shadow-sm bg-white font-medium">Advance</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
