<div>
    <div class="discovery-board-view p-4">
        <h2 class="text-lg font-bold">Influencer & Creator Discovery Board</h2>
        <ul>
            @foreach($influencers as $influencer)
                <li>{{ $influencer->handle }}</li>
            @endforeach
        </ul>
    </div>
</div>
