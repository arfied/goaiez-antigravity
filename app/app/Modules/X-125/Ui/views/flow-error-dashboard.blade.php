<div>
    <h2>Automation errors and pauses</h2>

    @if($error)
        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 text-red-600">
            {{ $error }}
        </div>
    @endif

    @if($success)
        <div class="mb-6 flex flex-col gap-2 bg-surface p-4 rounded mt-4 text-green-600">
            {{ $success }}
        </div>
    @endif

    @if($paused->isEmpty() && $errors->isEmpty())
        <p>No automation has failed or been paused</p>
    @else
        @if($paused->isNotEmpty())
            <div>
                <h3>Paused automatically or manually</h3>
                <ul>
                    @foreach($paused as $flow)
                        <li>
                            {{ $flow->name }} - 
                            @if($flow->status === 'paused_error')
                                stopped after repeated errors
                            @else
                                you paused this
                            @endif
                            <button wire:click="resumeFlow({{ $flow->id }})" class="bg-surface text-ink border rounded p-2">Resume</button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($errors->isNotEmpty())
            <div>
                <h3>Recent errors</h3>
                <p>{{ $errors->count() }} failed run(s)</p>
                <ul>
                    @foreach($errors as $run)
                        <li>
                            {{ $run->flow->name }} - {{ $run->error_message }} - {{ $run->created_at->diffForHumans() }}
                            <button wire:click="pauseFlow({{ $run->flow->id }})" class="bg-surface text-ink border rounded p-2">Pause</button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>
