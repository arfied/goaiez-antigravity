<div>
    <h1>Sign up</h1>
    
    @if ($errorMessage)
        <div class="text-red-500">{{ $errorMessage }}</div>
    @endif
    
    @if ($isSuccess)
        <div class="text-green-500">Sign up successful!</div>
    @else
        <form wire:submit.prevent="startSignup">
            <input type="text" wire:model="businessName" placeholder="Business Name">
            <input type="text" wire:model="contactPhone" placeholder="Phone">
            <button type="submit">Start</button>
        </form>
    @endif
</div>
