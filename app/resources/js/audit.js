/**
 * The free instant audit, client side.
 *
 * NO FRAMEWORK, AND THAT IS THE POINT (decision 259). These pages carry no
 * Livewire runtime, and Alpine is not an npm dependency here — it only ships
 * inside Livewire's bundle. Adding one to do what is below would cost roughly
 * 15KB gzipped on the single page in this application with an LCP gate on it
 * (`29` §11.2 row 1, under 1.5 seconds).
 *
 * THIS FILE RENDERS NOTHING. Every pixel of a result comes from the server as
 * rendered Blade — see decision 258. The reason is decision 236: the gauge makes
 * its arc and its printed number structurally unable to disagree, using
 * pathLength="100" so the score *is* the dash length. A second renderer written
 * here would reintroduce exactly the bug that guarantee exists to prevent, and
 * it is the one bug nobody checks for, because both halves look plausible.
 *
 * So the job here is only: debounce, ask, swap innerHTML, decide whether to ask
 * again. `data-pending` on the fragment's root is the whole polling contract,
 * which means adding a fifth AuditStatus never touches this file.
 */

const SUGGEST_DEBOUNCE_MS = 300;
const MIN_QUERY_LENGTH = 3;
const POLL_INTERVAL_MS = 900;

// The audit's own budget is 20s (AuditEngine::BUDGET_SECONDS). A little past it
// so a slow-but-succeeding run is not abandoned one poll before it finishes.
const POLL_TIMEOUT_MS = 30000;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });

    return { status: response.status, data: await response.json().catch(() => ({})) };
}

class AuditWidget {
    constructor(root) {
        this.root = root;
        this.form = root.querySelector('[data-audit-form]');
        this.input = root.querySelector('[data-audit-input]');
        this.suggestions = root.querySelector('[data-audit-suggestions]');
        this.target = root.querySelector('[data-audit-result-target]');
        this.error = root.querySelector('[data-audit-error]');
        this.turnstileMount = root.querySelector('[data-audit-turnstile]');
        this.submit = root.querySelector('[data-audit-submit]');

        this.debounce = null;
        this.chosenPlaceId = null;
        this.turnstileWidgetId = null;

        // A result already in the page means this is /audit/{token} rather than
        // the home page. Nothing to wire up except finishing the poll if the
        // audit was still running when the page was served.
        const existing = this.target?.querySelector('[data-audit-result]');

        if (existing) {
            this.resumeIfPending(existing);

            return;
        }

        this.form?.addEventListener('submit', (event) => this.start(event));
        this.input?.addEventListener('input', () => this.onType());
        this.input?.addEventListener('blur', () => window.setTimeout(() => this.closeSuggestions(), 150));
        this.root.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') this.closeSuggestions();
        });
    }

    url(name, token) {
        return this.root.dataset[name].replace('__TOKEN__', encodeURIComponent(token));
    }

    /* ------------------------------------------------------------------ *
     * Suggestions
     * ------------------------------------------------------------------ */

    onType() {
        // Typing invalidates a previous choice: the visitor is looking for a
        // different business, and submitting the stale place_id would audit
        // whichever one they had picked before.
        this.chosenPlaceId = null;
        this.hideError();

        window.clearTimeout(this.debounce);

        const query = this.input.value.trim();

        // The server enforces this too. Checking here as well is what stops the
        // debounce from billing Google 0.283c for two characters that could
        // never have matched anything useful.
        if (query.length < MIN_QUERY_LENGTH) {
            this.closeSuggestions();

            return;
        }

        this.debounce = window.setTimeout(() => this.suggest(query), SUGGEST_DEBOUNCE_MS);
    }

    async suggest(query) {
        const { data } = await postJson(this.root.dataset.suggestUrl, { query });

        // An empty list is the documented degradation — an exhausted budget or a
        // Google outage both land here, and neither is worth interrupting
        // somebody mid-word to explain.
        this.renderSuggestions(data.suggestions ?? []);
    }

    renderSuggestions(suggestions) {
        this.suggestions.replaceChildren();

        if (suggestions.length === 0) {
            this.closeSuggestions();

            return;
        }

        suggestions.forEach((suggestion) => {
            const item = document.createElement('li');
            const button = document.createElement('button');

            button.type = 'button';
            button.setAttribute('role', 'option');
            button.className =
                'block w-full px-4 py-3 text-left text-base text-ink hover:bg-paper focus-visible:bg-paper focus-visible:outline-none';

            const name = document.createElement('span');
            name.className = 'block font-medium';
            name.textContent = suggestion.name;
            button.append(name);

            if (suggestion.address) {
                const address = document.createElement('span');
                address.className = 'block text-sm text-ink-3';
                address.textContent = suggestion.address;
                button.append(address);
            }

            button.addEventListener('click', () => {
                this.chosenPlaceId = suggestion.place_id;
                this.input.value = suggestion.name;
                this.closeSuggestions();
                this.form.requestSubmit();
            });

            item.append(button);
            this.suggestions.append(item);
        });

        this.suggestions.hidden = false;
        this.input.setAttribute('aria-expanded', 'true');
    }

    closeSuggestions() {
        this.suggestions.hidden = true;
        this.suggestions.replaceChildren();
        this.input.setAttribute('aria-expanded', 'false');
    }

    /* ------------------------------------------------------------------ *
     * Running one audit
     * ------------------------------------------------------------------ */

    async start(event) {
        event.preventDefault();
        this.closeSuggestions();
        this.hideError();

        const query = this.input.value.trim();

        if (!this.chosenPlaceId && query.length < MIN_QUERY_LENGTH) {
            this.showError('Type a little more of the name — three letters at least.');

            return;
        }

        this.busy(true);

        const body = this.chosenPlaceId ? { place_id: this.chosenPlaceId } : { place_query: query };
        const token = await this.turnstileToken();

        if (token) body.turnstile_token = token;

        const { status, data } = await postJson(this.root.dataset.startUrl, body);

        // The second and later audits from one visitor need a captcha, and the
        // client cannot know in advance which this is — telling it up front
        // would leak whether the visitor had been here before. So the server
        // asks, and this retries once with a solved token.
        if (status === 422 && data.captcha_required) {
            this.busy(false);
            await this.showTurnstile();

            return;
        }

        if (data.candidates?.length) {
            this.busy(false);
            this.renderSuggestions(
                data.candidates.map((c) => ({ place_id: c.place_id, name: c.name, address: c.address })),
            );
            this.showError(data.message ?? 'Which one is yours?');

            return;
        }

        if (!data.token) {
            this.busy(false);
            this.showError(data.message ?? 'Something went wrong. Try again in a moment.');

            return;
        }

        this.poll(data.token, Date.now() + POLL_TIMEOUT_MS);
    }

    async poll(token, deadline) {
        const response = await fetch(this.url('resultUrl', token), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) {
            this.busy(false);
            this.showError('We lost track of that check. Try running it again.');

            return;
        }

        // innerHTML with a server response, which is safe here for reasons that
        // are worth stating because they are conditions rather than facts:
        //
        //   - the response is same-origin, from our own route, rendering our own
        //     Blade partial. It is never user-supplied markup
        //   - every dynamic value in that partial goes through `{{ }}`, which
        //     escapes. The one genuinely external string is `name_snapshot`,
        //     which is a business name from Google Places and could be anything
        //     at all — escaping is what makes that harmless
        //   - browsers do not execute <script> inserted via innerHTML, so the
        //     residual risk is attribute-based (an `onerror` on an img), which
        //     escaping also covers
        //
        // The condition that could break: a `{!! !!}` appearing in the partial.
        // `tests/Feature/MarketingPagesTest.php`'s "the only unescaped output in
        // a marketing view is a disclosure this codebase escaped itself" is what
        // holds it.
        //
        // ⛔ THIS CITED a `MarketingViewTest` AND NO FILE OF THAT NAME HAS EVER
        // EXISTED — CORRECTED 2026-08-25 (9662).
        //
        // ⚠️ AND IT SAID "asserts there is none", WHICH THAT TEST STOPPED SAYING
        // AT THE FOUR-LANE MERGE. It allowlists two expressions by name —
        // `$smsText` and `$emailText`, both built by `ConsentDisclosure`, which
        // escapes every argument before it builds a tag — and refuses every
        // other one in any file. So the fact this comment stands on is narrower
        // than "there is none" and is still the fact that matters here: nothing
        // reaches innerHTML unescaped that this codebase did not escape itself.
        this.target.innerHTML = await response.text();

        const result = this.target.querySelector('[data-audit-result]');
        const pending = result?.dataset.pending === 'true';

        if (!pending) {
            this.busy(false);
            this.finish(token);

            return;
        }

        if (Date.now() > deadline) {
            this.busy(false);
            this.showError('That is taking longer than it should. Try running it again.');

            return;
        }

        window.setTimeout(() => this.poll(token, deadline), POLL_INTERVAL_MS);
    }

    /**
     * Put the shareable link in the address bar once there is something to share.
     *
     * replaceState rather than pushState: the visitor has not navigated, and a
     * back button that returns to the same page minus its result is a small lie
     * about what happened.
     */
    finish(token) {
        window.history.replaceState({}, '', this.url('shareUrl', token));
    }

    /**
     * Finish a poll for a result the server rendered as still running.
     */
    resumeIfPending(existing) {
        if (existing.dataset.pending !== 'true') return;

        this.poll(existing.dataset.token, Date.now() + POLL_TIMEOUT_MS);
    }

    /* ------------------------------------------------------------------ *
     * Turnstile
     * ------------------------------------------------------------------ */

    /**
     * Loaded on first interaction, never on page load (decision 194).
     *
     * Two reasons, and both matter: a third-party script in the critical path
     * would spend the LCP budget on something no first-time visitor needs, and
     * §6.2 requires the challenge to fire after a consent banner rather than
     * racing it.
     */
    async showTurnstile() {
        const siteKey = this.root.dataset.turnstileKey;

        if (!siteKey) {
            this.showError('We could not verify that request. Try again in a moment.');

            return;
        }

        await this.loadTurnstileScript();

        this.turnstileMount.hidden = false;

        if (this.turnstileWidgetId === null) {
            this.turnstileWidgetId = window.turnstile.render(this.turnstileMount, {
                sitekey: siteKey,
                callback: () => this.form.requestSubmit(),
            });
        } else {
            window.turnstile.reset(this.turnstileWidgetId);
        }
    }

    loadTurnstileScript() {
        if (window.turnstile) return Promise.resolve();

        if (!this.turnstileScript) {
            this.turnstileScript = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
                script.async = true;
                script.onload = resolve;
                script.onerror = reject;
                document.head.append(script);
            });
        }

        return this.turnstileScript;
    }

    /**
     * A solved token, if one is waiting. Never blocks the first audit.
     */
    turnstileToken() {
        if (this.turnstileWidgetId === null || !window.turnstile) return Promise.resolve(null);

        return Promise.resolve(window.turnstile.getResponse(this.turnstileWidgetId) || null);
    }

    /* ------------------------------------------------------------------ *
     * State
     * ------------------------------------------------------------------ */

    busy(isBusy) {
        if (!this.submit) return;

        this.submit.disabled = isBusy;
        this.submit.setAttribute('aria-busy', isBusy ? 'true' : 'false');
        this.submit.textContent = isBusy ? 'Checking…' : 'Check it';
    }

    showError(message) {
        this.error.textContent = message;
        this.error.hidden = false;
    }

    hideError() {
        this.error.hidden = true;
        this.error.textContent = '';
    }
}

document.querySelectorAll('[data-audit]').forEach((root) => new AuditWidget(root));
