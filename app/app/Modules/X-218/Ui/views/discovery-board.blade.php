<div>
    <div class="discovery-board-view p-4">
        <h3 class="text-lg font-bold">Influencer & Creator Discovery Board</h3>
        <ul>
            @foreach($influencers as $influencer)
                <li>{{ $influencer->handle }}</li>
            @endforeach
        </ul>
    </div>
</div>
