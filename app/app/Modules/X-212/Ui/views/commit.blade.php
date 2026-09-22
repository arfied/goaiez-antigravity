<div>
    <div class="commit-view p-4">
        <h2 class="text-lg font-bold">Migration Commit</h2>
        <ul>
            @foreach($runs as $run)
                <li>{{ $run->source_system }}</li>
            @endforeach
        </ul>
    </div>
</div>
