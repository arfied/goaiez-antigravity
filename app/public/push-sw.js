self.addEventListener('push', function(event) {
    if (!event.data) {
        return;
    }

    try {
        const payload = event.data.json();
        let title = 'GO AI EZ';
        let body = 'Something needs you in your account.';
        let url = payload.deep_link || '/account';

        if (payload.event_type === 'test_alert') {
            body = 'Alerts are working on this browser.';
        }

        event.waitUntil(
            self.registration.showNotification(title, {
                body: body,
                data: { url: url }
            })
        );
    } catch (e) {
        // invalid json or payload
    }
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    const urlToOpen = event.notification.data && event.notification.data.url ? event.notification.data.url : '/account';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(windowClients) {
            for (let i = 0; i < windowClients.length; i++) {
                let client = windowClients[i];
                if (client.url === urlToOpen && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});
