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
 * ⛔ TEXT NODES ONLY, NEVER `innerHTML`, FOR ANYTHING THAT CAME BACK FROM THE
 * FEED. A review's text is written by a member of the public. Assigning it to
 * `innerHTML` would be a cross-site scripting hole on the owner's own website,
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
(function () {
    'use strict';

    /**
     * The script tag that loaded this file.
     *
     * `document.currentScript` is set while a classic script executes, async
     * included. The fallback covers a page that loaded this some other way —
     * better a widget that finds its key than one that silently does nothing.
     */
    var self = document.currentScript || document.querySelector('script[data-key]');

    if (!self) {
        return;
    }

    var key = self.getAttribute('data-key');

    if (!key) {
        return;
    }

    /**
     * The feed lives on whichever host served this file.
     *
     * Derived rather than compiled in: this application answers on more than one
     * hostname over its life, and a baked-in origin would send a tenant's page
     * to the wrong one for ever. `self.src` is the address the browser actually
     * fetched, so it is right by construction.
     */
    var origin;

    try {
        origin = new URL(self.src, window.location.href).origin;
    } catch (e) {
        return;
    }

    /**
     * Where the reviews go.
     *
     * Immediately after the script tag, so the owner controls placement by
     * choosing where to paste — no container to remember, which is the whole
     * promise of a one-line install. `data-target` overrides it for a page that
     * would rather place the block itself.
     */
    var mount = document.createElement('div');
    var target = self.getAttribute('data-target');
    var chosen = target ? document.querySelector(target) : null;

    if (chosen) {
        chosen.appendChild(mount);
    } else if (self.parentNode) {
        self.parentNode.insertBefore(mount, self.nextSibling);
    } else {
        return;
    }

    mount.setAttribute('data-goaiez-reviews', '');

    function el(tag, style, text) {
        var node = document.createElement(tag);

        if (style) {
            node.setAttribute('style', style);
        }

        if (text !== undefined && text !== null) {
            node.appendChild(document.createTextNode(String(text)));
        }

        return node;
    }

    /**
     * A rating as both a shape and a sentence.
     *
     * `22`: colour — and a row of glyphs is the same claim as colour — is never
     * the only indicator. The stars are marked `aria-hidden` and the number is
     * the accessible name, so a screen reader hears "4 out of 5" once rather
     * than four unpronounceable characters.
     */
    function rating(value) {
        var stars = Math.max(0, Math.min(5, Math.round(Number(value) || 0)));
        var row = el('p', 'margin:0 0 .35em;font-size:.95em');
        var glyphs = el('span', 'letter-spacing:.1em', '★★★★★'.slice(0, stars) + '☆☆☆☆☆'.slice(0, 5 - stars));

        glyphs.setAttribute('aria-hidden', 'true');
        row.appendChild(glyphs);
        row.appendChild(el('span', 'margin-left:.5em', stars + ' out of 5'));

        return row;
    }

    function posted(value) {
        if (!value) {
            return null;
        }

        var date = new Date(value);

        if (isNaN(date.getTime())) {
            return null;
        }

        return date.toLocaleDateString();
    }

    function render(reviews) {
        var list = el('ul', 'list-style:none;margin:0;padding:0;display:grid;gap:1em');

        for (var i = 0; i < reviews.length; i++) {
            var review = reviews[i] || {};
            var item = el('li', 'margin:0;padding:0 0 1em;border-bottom:1px solid currentColor;opacity:1');

            item.appendChild(rating(review.rating));

            if (review.comment) {
                item.appendChild(el('p', 'margin:0 0 .35em;line-height:1.5', review.comment));
            }

            // The author may legitimately be absent — the feedback form asks for
            // a name optionally, and `WidgetReviewResource` sends null rather
            // than inventing "Anonymous". The label is invented here, in the
            // renderer's own language, which is what that resource asked for.
            var when = posted(review.posted_at);
            var byline = (review.author || 'A customer') + (when ? ' · ' + when : '');

            item.appendChild(el('p', 'margin:0;font-size:.85em;opacity:.75', byline));
            list.appendChild(item);
        }

        mount.appendChild(list);
    }

    fetch(origin + '/api/widget/' + encodeURIComponent(key) + '/reviews', {
        // No cookie may travel to a public endpoint from a third-party page.
        credentials: 'omit',
        headers: { Accept: 'application/json' },
    })
        .then(function (response) {
            return response.ok ? response.json() : null;
        })
        .then(function (body) {
            if (body && Object.prototype.toString.call(body.data) === '[object Array]' && body.data.length) {
                render(body.data);
            }
        })
        .catch(function () {
            // ⚠️ SILENT, AND DELIBERATELY SO. Every failure this can see —
            // the domain is not on the list, the network is down, we are
            // rate-limited — is our problem rather than the visitor's, and the
            // correct behaviour on somebody else's website is to occupy no space
            // and say nothing. An error message here would be our diagnostics
            // printed on a business's homepage.
        });
})();
