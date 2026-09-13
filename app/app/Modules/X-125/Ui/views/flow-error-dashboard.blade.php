<div>
    <h2>Automation errors</h2>

    @if($paused->isEmpty() && $errors->isEmpty())
        <p>No automation has failed</p>
    @else
        @if($paused->isNotEmpty())
            <div>
                <h3>Paused automatically</h3>
                <ul>
                    @foreach($paused as $flow)
                        <li>{{ $flow->name }} - paused after {{ $flow->consecutive_errors }} errors</li>
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
                        <li>{{ $run->flow->name }} - {{ $run->error_message }} - {{ $run->created_at->diffForHumans() }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>
