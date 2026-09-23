<div>
    <h2>WhatsApp conversations</h2>
    @if($sessions->isEmpty())
        <p>No WhatsApp conversations yet. WhatsApp inbound is not connected on this account; conversations appear here the day it is.</p>
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
