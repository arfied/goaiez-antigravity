<div>
    <div class="assistant-panel-view p-4">
        <h2>Field assistant</h2>
        <p>It listens, retrieves and suggests; it never speaks to the customer.</p>
        
        <form wire:submit="ask" class="flex gap-4 my-4">
            <input type="text" wire:model="question" placeholder="Ask the pricebook" class="border p-2 rounded">
            <x-ui.submit target="ask" busy="Asking…">Ask</x-ui.submit>
        </form>

        @if($lastAnswer !== null)
            <p class="mb-4">{{ $lastAnswer }}</p>
        @endif

        @if($suggestions->isEmpty())
            <p>No questions yet. Ask the pricebook from the job.</p>
        @else
            <table class="w-full text-left mt-8">
                <thead>
                    <tr>
                        <th>Question</th>
                        <th>Answer</th>
                        <th>Status</th>
                        <th>Sample</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($suggestions as $s)
                        <tr>
                            <td>{{ $s->query_text }}</td>
                            <td>{{ $s->response_text }}</td>
                            <td>
                                <x-ui.status-pill :state="$pill[$s->id][0]" :label="$pill[$s->id][1]" />
                            </td>
                            <td>
                                @if($s->is_sample)
                                    <x-ui.status-pill state="attention" label="Sample" />
                                @endif
                            </td>
                            <td>
                                <x-ui.button size="default" wire:click="askAgain({{ $s->id }})">Ask again</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
