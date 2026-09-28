<div>
    <h2>WhatsApp conversations</h2>

    <div class="mb-6">
        @if($connection === null)
            <p>WhatsApp inbound is not connected on this account; conversations appear here the day it is.</p>
            <x-ui.button wire:click="connect">Connect a number</x-ui.button>
        @elseif($connection->status === 'connected')
            <p>Connected as {{ $connection->display_label }}</p>
            <x-ui.button wire:click="disconnect">Disconnect</x-ui.button>
        @elseif($connection->status === 'pending')
            <p>Waiting for you to finish connecting at WhatsApp.</p>
            <x-ui.button wire:click="connect">Connect a number</x-ui.button>
        @elseif($connection->status === 'disconnected')
            <p>Disconnected — {{ $connection->last_error }}</p>
            <x-ui.button wire:click="connect">Connect a number</x-ui.button>
        @endif
    </div>

    @if($sessions->isEmpty())
        <a href="{{ route('c-whatsapp.template-status-card') }}">WhatsApp templates &rarr;</a>
    @else
        <table>
            <thead>
                <tr>
                    <th>Phone</th>
                    <th>Last Inbound</th>
                    <th>Window</th>
                    <th>Expires At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sessions as $session)
                    <tr>
                        <td>{{ $session->recipient_phone }}</td>
                        <td>{{ $session->last_inbound_at }}</td>
                        <td>{{ $session->is_window_open ? 'window open' : 'window closed' }}</td>
                        <td>{{ $session->session_window_expires_at }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
