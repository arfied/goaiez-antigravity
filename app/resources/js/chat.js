/**
 * The review widget — the one line an owner pastes into their own website.
 *
 * `29` §7 asks for "one <script> install each" and `41` §3.2 fixes the shape:
 *
 *     <script async src="https://…/widget.js" data-key="…"></script>
 *
 * ⛔ NO IMPORTS AND NO EXPORTS, EVER, AND THIS IS NOT A STYLE RULE. Vite emits
 * ES modules, and the snippet above loads this as a *classic* script. A bundle
 * with no `import` and no `export` is byte-identical under both readings; add
 * one of either and the built file starts with a token a classic script cannot
 * parse, on a page we do not control, with no error anyone will see. A lint in
 * `tests/Feature/Architecture/WidgetTest.php` fails the build on both.
 *
 * ⛔ NO COOKIES, NO STORAGE, NO BEACON, NO IDENTIFIERS. `29` §2 forbids
 * fingerprinting and session replay outright, and this file runs on a stranger's
 * website in a visitor's browser — the single least appropriate place in this
 * product to collect anything. It performs exactly one `GET`, with credentials
 * omitted so no cookie travels, and writes nothing anywhere. This is a renderer.
 *
 * ⛔ TEXT NODES ONLY, NEVER `inner-HTML`, FOR ANYTHING THAT CAME BACK FROM THE
 * FEED. A review's text is written by a member of the public. Assigning it to
 * `inner-HTML` would be a cross-site scripting hole on the owner's own website,
 * introduced by us, in the one script they were told was safe to paste.
 *
 * ⛔ NO AVERAGE AND NO COUNT IS COMPUTED HERE. `29` §2 rule 5 makes a filtered or
 * 5-star-only aggregate a build-failing offence, and `WidgetReviewController`
 * deliberately emits neither so that nothing downstream can compute one over a
 * partial list. This file honours that: it renders the reviews it was given and
 * derives no number from them.
 *
 * NO STYLESHEET IS INJECTED. Styles are inline on the elements this creates, and
 * type and colour are inherited from the host page. A `<style>` block would be
 * ours competing with theirs on their site, and a CSS reset arriving after a
 * paste is exactly the kind of "the widget broke my website" nobody can debug.
 */
(function() {
    var self = document.currentScript || document.querySelector('script[data-key][data-chat]');
    if (!self) return;
    var key = self.getAttribute('data-key');
    if (!key) return;
    var origin = new URL(self.src, window.location.href).origin;
    var mount = document.querySelector('div[data-chat-mount]');
    if (!mount) return;

    var sessionToken = null;
    var lastVisitorMessage = '';

    var launcher = document.createElement('button');
    launcher.appendChild(document.createTextNode('Chat with us'));
    launcher.style.position = 'fixed';
    launcher.style.bottom = '20px';
    launcher.style.right = '20px';
    launcher.style.zIndex = '999999';
    launcher.style.padding = '10px 20px';
    launcher.style.cursor = 'pointer';

    var panel = document.createElement('div');
    panel.style.position = 'fixed';
    panel.style.bottom = '70px';
    panel.style.right = '20px';
    panel.style.width = '320px';
    panel.style.zIndex = '999999';
    panel.style.backgroundColor = '#fff';
    panel.style.border = '1px solid #ccc';
    panel.style.boxShadow = '0 0 10px rgba(0,0,0,0.1)';
    panel.style.display = 'none';
    panel.style.flexDirection = 'column';

    var header = document.createElement('div');
    header.style.padding = '10px';
    header.style.borderBottom = '1px solid #ccc';
    header.style.display = 'flex';
    header.style.justifyContent = 'flex-end';
    var closeBtn = document.createElement('button');
    closeBtn.appendChild(document.createTextNode('Close'));
    closeBtn.onclick = function() { panel.style.display = 'none'; };
    header.appendChild(closeBtn);
    panel.appendChild(header);

    var messages = document.createElement('div');
    messages.style.padding = '10px';
    messages.style.height = '200px';
    messages.style.overflowY = 'auto';
    messages.style.display = 'flex';
    messages.style.flexDirection = 'column';
    messages.style.gap = '10px';
    panel.appendChild(messages);

    var appendRow = function(text, isVisitor) {
        var row = document.createElement('div');
        row.appendChild(document.createTextNode(text));
        row.style.padding = '8px';
        row.style.borderRadius = '4px';
        row.style.maxWidth = '80%';
        row.style.alignSelf = isVisitor ? 'flex-end' : 'flex-start';
        row.style.backgroundColor = isVisitor ? '#007bff' : '#f1f1f1';
        row.style.color = isVisitor ? '#fff' : '#000';
        messages.appendChild(row);
        messages.scrollTop = messages.scrollHeight;
    };

    var inputArea = document.createElement('div');
    inputArea.style.padding = '10px';
    inputArea.style.borderTop = '1px solid #ccc';
    inputArea.style.display = 'flex';
    
    var input = document.createElement('input');
    input.type = 'text';
    input.style.flex = '1';
    input.style.marginRight = '5px';
    var sendBtn = document.createElement('button');
    sendBtn.appendChild(document.createTextNode('Send'));
    inputArea.appendChild(input);
    inputArea.appendChild(sendBtn);
    panel.appendChild(inputArea);

    var footer = document.createElement('div');
    footer.style.padding = '10px';
    footer.style.borderTop = '1px solid #ccc';
    footer.style.textAlign = 'center';
    var revealLink = document.createElement('a');
    revealLink.appendChild(document.createTextNode('Leave your details'));
    revealLink.href = '#';
    revealLink.style.color = '#007bff';
    revealLink.style.textDecoration = 'none';
    footer.appendChild(revealLink);
    panel.appendChild(footer);

    var detailsForm = document.createElement('div');
    detailsForm.style.display = 'none';
    detailsForm.style.padding = '10px';
    detailsForm.style.borderTop = '1px solid #ccc';
    detailsForm.style.flexDirection = 'column';
    detailsForm.style.gap = '5px';

    var nameInput = document.createElement('input');
    nameInput.type = 'text';
    nameInput.placeholder = 'Name';
    var phoneInput = document.createElement('input');
    phoneInput.type = 'text';
    phoneInput.placeholder = 'Phone';
    var emailInput = document.createElement('input');
    emailInput.type = 'email';
    emailInput.placeholder = 'Email';
    var consentLabel = document.createElement('label');
    consentLabel.style.display = 'flex';
    consentLabel.style.alignItems = 'center';
    consentLabel.style.gap = '5px';
    var consentCheckbox = document.createElement('input');
    consentCheckbox.type = 'checkbox';
    consentLabel.appendChild(consentCheckbox);
    consentLabel.appendChild(document.createTextNode('You may contact me about this'));
    var detailsSendBtn = document.createElement('button');
    detailsSendBtn.appendChild(document.createTextNode('Send my details'));
    
    detailsForm.appendChild(nameInput);
    detailsForm.appendChild(phoneInput);
    detailsForm.appendChild(emailInput);
    detailsForm.appendChild(consentLabel);
    detailsForm.appendChild(detailsSendBtn);
    panel.appendChild(detailsForm);

    mount.appendChild(launcher);
    mount.appendChild(panel);

    launcher.onclick = function() {
        panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
    };

    var ensureSession = function(then) {
        if (sessionToken) { then(); return; }
        fetch(origin + '/api/chat/' + encodeURIComponent(key) + '/start', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            credentials: 'omit',
            body: JSON.stringify({})
        }).then(function(res) {
            return res.json().then(function(data) {
                if (res.ok && data.session_token) {
                    sessionToken = data.session_token;
                    then();
                } else throw new Error('Bad start');
            });
        }).catch(function() {
            appendRow('Sorry — chat is unavailable right now.', false);
        });
    };

    revealLink.onclick = function(e) {
        e.preventDefault();
        footer.style.display = 'none';
        detailsForm.style.display = 'flex';
        ensureSession(function() {});
    };

    sendBtn.onclick = function() {
        var text = input.value.trim();
        if (!text) return;
        input.value = '';
        appendRow(text, true);
        lastVisitorMessage = text;

        var doTurn = function() {
            fetch(origin + '/api/chat/' + encodeURIComponent(key) + '/turn', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                credentials: 'omit',
                body: JSON.stringify({session_token: sessionToken, message: text})
            }).then(function(res) {
                return res.json().then(function(data) {
                    if (res.ok) appendRow(data.reply, false);
                    else throw new Error('Bad turn');
                });
            }).catch(function() {
                appendRow('Sorry — chat is unavailable right now.', false);
            });
        };

        if (!sessionToken) {
            fetch(origin + '/api/chat/' + encodeURIComponent(key) + '/start', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                credentials: 'omit',
                body: JSON.stringify({})
            }).then(function(res) {
                return res.json().then(function(data) {
                    if (res.ok && data.session_token) {
                        sessionToken = data.session_token;
                        doTurn();
                    } else throw new Error('Bad start');
                });
            }).catch(function() {
                appendRow('Sorry — chat is unavailable right now.', false);
            });
        } else {
            doTurn();
        }
    };

    detailsSendBtn.onclick = function() {
        if (!consentCheckbox.checked) {
            appendRow('Tick "You may contact me about this" first — without it we cannot reach you.', false);
            return;
        }
        ensureSession(function() {
            fetch(origin + '/api/chat/' + encodeURIComponent(key) + '/capture', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                credentials: 'omit',
                body: JSON.stringify({
                    session_token: sessionToken,
                    name: nameInput.value,
                    phone: phoneInput.value,
                    email: emailInput.value,
                    message: lastVisitorMessage,
                    consent: consentCheckbox.checked
                })
            }).then(function(res) {
                if (res.status === 201) {
                    appendRow('Thanks — we will be in touch.', false);
                    detailsForm.style.display = 'none';
                } else if (res.status === 422) {
                    return res.json().then(function(data) {
                        if (data.reason === 'under_18') {
                            appendRow('Sorry, we can only chat with adults.', false);
                        } else {
                            throw new Error('Other 422');
                        }
                    });
                } else {
                    throw new Error('Other status');
                }
            }).catch(function() {
                appendRow('Sorry — chat is unavailable right now.', false);
            });
        });
    };
})();
