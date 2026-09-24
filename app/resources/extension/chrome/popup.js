document.addEventListener('DOMContentLoaded', () => {
  const baseUrlInput = document.getElementById('baseUrl');
  const pixelKeyInput = document.getElementById('pixelKey');
  const connectBtn = document.getElementById('connect');
  const scanBtn = document.getElementById('scan');
  const statusEl = document.getElementById('status');

  chrome.storage.local.get(['baseUrl', 'pixelKey', 'sessionToken'], (result) => {
    if (result.baseUrl) baseUrlInput.value = result.baseUrl;
    if (result.pixelKey) pixelKeyInput.value = result.pixelKey;
  });

  connectBtn.addEventListener('click', async () => {
    const baseUrl = baseUrlInput.value;
    const pixelKey = pixelKeyInput.value;
    chrome.storage.local.set({ baseUrl, pixelKey });

    try {
      const response = await fetch(baseUrl + '/api/extension/' + encodeURIComponent(pixelKey) + '/session', {
        method: 'POST',
        headers: { 'Accept': 'application/json' }
      });

      if (response.status === 201) {
        const data = await response.json();
        chrome.storage.local.set({ sessionToken: data.session_token });
        statusEl.textContent = 'Connected';
      } else if (response.status === 404) {
        statusEl.textContent = 'That pixel key was not recognised';
      } else {
        statusEl.textContent = response.status + ' ' + response.statusText;
      }
    } catch (err) {
      statusEl.textContent = 'Error: ' + err.message;
    }
  });

  scanBtn.addEventListener('click', () => {
    chrome.storage.local.get(['baseUrl', 'pixelKey', 'sessionToken'], async (result) => {
      const { baseUrl, pixelKey, sessionToken } = result;
      if (!sessionToken) {
        statusEl.textContent = 'Session not found — connect again';
        return;
      }

      chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
        if (!tabs[0]) return;
        const tabId = tabs[0].id;
        chrome.scripting.executeScript(
          {
            target: { tabId },
            func: () => ({ url: location.href, dom: document.documentElement.outerHTML.slice(0, 200000) })
          },
          async (results) => {
            if (!results || !results[0] || !results[0].result) return;
            const { url, dom } = results[0].result;

            try {
              const response = await fetch(baseUrl + '/api/extension/' + encodeURIComponent(pixelKey) + '/scan', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'Accept': 'application/json'
                },
                body: JSON.stringify({ session_token: sessionToken, page_url: url, dom })
              });

              if (response.status === 200) {
                const data = await response.json();
                if (data.is_aborted) {
                  statusEl.textContent = 'This site showed a rate-limit or bot banner; the session was stopped. Connect again to start a new one.';
                  chrome.storage.local.remove('sessionToken');
                } else {
                  statusEl.textContent = 'Recorded scan ' + data.actions_count;
                }
              } else if (response.status === 404) {
                statusEl.textContent = 'Session not found — connect again';
              } else {
                statusEl.textContent = response.status + ' ' + response.statusText;
              }
            } catch (err) {
              statusEl.textContent = 'Error: ' + err.message;
            }
          }
        );
      });
    });
  });
});
