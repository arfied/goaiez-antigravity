/**
 * T3 injection — the client half. `BUILD-PLAN` §2.11.3 slice I.
 *
 * ⛔ **THIS IS A SEPARATE FILE BECAUSE `pixel.js` IS A PURE COLLECTOR AND MUST
 * STAY ONE.** §2.11.5 conflict 4 decided that outright: injection needs a
 * payload fetch and DOM writes, and teaching the collector to do them *"would
 * put DOM-writing code in every visitor's browser on every tenant site whether
 * or not actuation is on"*. This file is served from `/s/<key>.js` **only to a
 * tenant with live T3 change sets** — everybody else is served an empty body, so
 * the code below never reaches their visitors at all.
 *
 * ⛔ NO IMPORTS AND NO EXPORTS, EVER — `pixel.js` and `widget.js`'s rule, for
 * their reason: this is loaded by a classic `<script async>` on a page we do not
 * control, Vite emits ES modules, and a file with neither reads identically
 * under both. A lint holds it.
 *
 * ⛔ **NO USER-AGENT BRANCHING, NOT ONE READ.** `29` §12.1 and §19.7's
 * no-cloaking gate. This file never touches `navigator` at all — not
 * `userAgent`, not `userAgentData`, not `webdriver`, not `platform` — so it
 * cannot behave one way for Googlebot and another for a person even by
 * accident, and a lint fails the build on the word. The server half is held the
 * same way and its response is asserted byte-identical under a crawler agent and
 * a browser agent.
 *
 * ⛔ **NO HTML SINK EXISTS IN THIS FILE.** No `innerHTML`, no `outerHTML`, no
 * `insertAdjacentHTML`, no `document.write`, no `createContextualFragment`, no
 * `eval`, no `new Function`. Everything reaches the page through
 * `createElement`, `textContent` and `setAttribute`. **That is what makes
 * free-form HTML unrepresentable rather than merely rejected**: a payload value
 * full of markup lands on the page as visible text, because there is nothing
 * here that parses it. The other two layers are the closed
 * `App\Enums\T3InjectionKind` and the typed operation classes behind it.
 *
 * ⛔ **NOTHING IS EVER HIDDEN.** No `style`, no `hidden`, no off-screen
 * positioning, no `display:none` — a lint refuses the words. Injected text that
 * a crawler can read and a person cannot **is** cloaking, whatever the
 * user-agent rule says, and it is the way this file would fail the gate above
 * without ever mentioning a user agent.
 *
 * ⛔ **NO SELECTOR IS EVER BUILT FROM PAYLOAD DATA.** The two lookups below walk
 * `getElementsByTagName` and compare attributes; a selector assembled from a
 * value is a small language reaching arbitrary elements on somebody else's page,
 * which is the injection surface in a different costume.
 *
 * ⚠️ **IT COLLECTS NOTHING AND SENDS NOTHING.** One request, a `GET`, to the
 * origin this file was fetched from. No beacon, no identifier, no storage, no
 * cookie — `widget.js`'s collect-nothing discipline (2961). The pixel is the
 * sensor; this is the actuator, and keeping them apart is what lets each be
 * argued about on its own.
 *
 * ⚠️ **SECOND-WAVE INDEXED, WHICH IS WHY IT RUNS AS LATE AS IT DOES.** Doc `41`
 * Part 1's T3 row says so out loud, and it is a property of the tier rather than
 * of this implementation: a crawler that does not execute JavaScript sees the
 * page the tenant's own CMS produced. Nothing here can change that, and nothing
 * this platform says to an owner may imply otherwise.
 */
(function () {
    'use strict';

    var doc = document;

    /** The script tag that loaded this file — `pixel.js`'s own resolution. */
    var self = doc.currentScript || doc.querySelector('script[data-k]');

    if (!self) {
        return;
    }

    /** Public key only. Never a secret, never tenant data, never end-user PII. */
    var key = self.getAttribute('data-k');

    if (!key) {
        return;
    }

    /**
     * Derived from the address the browser actually fetched this from, never
     * compiled in: this application answers on more than one hostname over its
     * life, and a baked-in origin would point a tenant's page at the wrong one
     * for ever.
     */
    var origin;

    try {
        origin = new URL(self.src, window.location.href).origin;
    } catch (e) {
        return;
    }

    /**
     * The page this is, as the payload spells it. Trailing slashes are
     * normalised away on both sides of the wire — `T3Payloads::pathOf()` does
     * the same — because `/services` and `/services/` are one page to every CMS
     * and two strings to a comparison.
     */
    var path = window.location.pathname.replace(/\/+$/, '') || '/';

    /**
     * ⛔ **AND WHICH OF THE TENANT'S WEBSITES THIS IS.** One public key covers a
     * business; a change set belongs to one location's website. Without this
     * comparison a tenant with two sites had each of them applying the other's
     * `/about`, `/contact` and `/services` operations — a name, an address,
     * opening hours and a rating describing a different premises, on a real
     * public page under the owner's own domain.
     *
     * `hostname` rather than `host`, so a port is not part of the comparison,
     * and `T3Payloads::hostOf()` drops one on the server for the same reason.
     * The match is exact apart from case: `www.` is a different host here, on
     * the server, and to WordPress.
     */
    var host = (window.location.hostname || '').toLowerCase();

    /** The one container this file may add to the page. Built on first use. */
    var box = null;

    function container() {
        if (box) {
            return box;
        }

        if (!doc.body) {
            return null;
        }

        box = doc.createElement('div');
        box.setAttribute('data-goaiez', 't3');
        doc.body.appendChild(box);

        return box;
    }

    function isText(value) {
        return typeof value === 'string' && value !== '';
    }

    function textNode(tag, value) {
        var element = doc.createElement(tag);
        element.textContent = value;

        return element;
    }

    /**
     * One `<meta>` upsert.
     *
     * The attribute is `name` or `property` and nothing else — the server sends
     * which, and this refuses anything but those two, so the same fact is stated
     * on both sides of a public network hop.
     */
    function meta(op) {
        var attribute = op.a;

        if (attribute !== 'name' && attribute !== 'property') {
            return;
        }

        if (!isText(op.n) || !isText(op.c) || !doc.head) {
            return;
        }

        var tags = doc.head.getElementsByTagName('meta');

        for (var i = 0; i < tags.length; i++) {
            if (tags[i].getAttribute(attribute) === op.n) {
                tags[i].setAttribute('content', op.c);

                return;
            }
        }

        var tag = doc.createElement('meta');
        tag.setAttribute(attribute, op.n);
        tag.setAttribute('content', op.c);
        doc.head.appendChild(tag);
    }

    /**
     * One structured-data block.
     *
     * ⚠️ A `script` element whose type is not a JavaScript MIME type is a data
     * block the browser never runs, and `src` is never set. `JSON.stringify` is
     * what produces the bytes, from an object graph the server proved scalar —
     * so no string that arrived here is ever parsed as anything.
     */
    function jsonLd(op) {
        if (!op.d || typeof op.d !== 'object' || !doc.head) {
            return;
        }

        var block;

        try {
            block = JSON.stringify(op.d);
        } catch (e) {
            return;
        }

        var element = doc.createElement('script');
        element.setAttribute('type', 'application/ld+json');
        element.textContent = block;
        doc.head.appendChild(element);
    }

    /**
     * Alt text on one image, matched on the `src` the page already carries.
     *
     * The suffix match is what makes a CDN's absolute URL and the page's
     * relative one the same picture. Nothing but `alt` is ever set.
     */
    function altText(op) {
        if (!isText(op.i) || !isText(op.x)) {
            return;
        }

        var images = doc.getElementsByTagName('img');

        for (var i = 0; i < images.length; i++) {
            var src = images[i].getAttribute('src');

            if (src && src.length >= op.i.length && src.slice(-op.i.length) === op.i) {
                images[i].setAttribute('alt', op.x);
            }
        }
    }

    /**
     * One link to another page on the same site.
     *
     * ⚠️ The path is checked again here, having been checked at the type that
     * built it. `//evil.test` is a valid protocol-relative URL one character away
     * from a path, and a link is the one operation whose destination a person
     * clicks.
     */
    function internalLink(op) {
        if (!isText(op.p) || !isText(op.x)) {
            return;
        }

        if (op.p.charAt(0) !== '/' || op.p.charAt(1) === '/') {
            return;
        }

        var parent = container();

        if (!parent) {
            return;
        }

        var link = textNode('a', op.x);
        link.setAttribute('href', op.p);
        parent.appendChild(link);
    }

    /**
     * A question-and-answer block, one heading and one paragraph per pair.
     *
     * There is no way to express a list, a table or a link inside an answer, and
     * that is the point: every one of those is markup wearing a field name.
     */
    function faq(op) {
        if (!op.f || !op.f.length) {
            return;
        }

        var parent = container();

        if (!parent) {
            return;
        }

        var section = doc.createElement('section');

        for (var i = 0; i < op.f.length; i++) {
            var pair = op.f[i];

            if (!pair || !isText(pair.q) || !isText(pair.a)) {
                continue;
            }

            section.appendChild(textNode('h3', pair.q));
            section.appendChild(textNode('p', pair.a));
        }

        parent.appendChild(section);
    }

    function apply(payload) {
        if (!payload || !payload.p || !payload.p.length) {
            return;
        }

        for (var i = 0; i < payload.p.length; i++) {
            var page = payload.p[i];

            // ⛔ **THE WEBSITE FIRST, THEN THE PAGE.** A payload entry with no
            // `h` at all is refused rather than applied everywhere, so a
            // response cached before this comparison existed cannot outlive it.
            if (!page || page.h !== host || page.u !== path || !page.o) {
                continue;
            }

            for (var j = 0; j < page.o.length; j++) {
                var op = page.o[j];

                if (!op) {
                    continue;
                }

                if (op.t === 'meta') {
                    meta(op);
                } else if (op.t === 'json_ld') {
                    jsonLd(op);
                } else if (op.t === 'alt_text') {
                    altText(op);
                } else if (op.t === 'internal_link') {
                    internalLink(op);
                } else if (op.t === 'faq') {
                    faq(op);
                }
            }
        }
    }

    function run() {
        try {
            window
                .fetch(origin + '/api/site/' + encodeURIComponent(key), {
                    method: 'GET',
                    // ⛔ No cookie may travel from a third-party page, and there
                    // is nothing on our origin worth sending anyway.
                    credentials: 'omit',
                })
                .then(function (response) {
                    return response.ok ? response.json() : null;
                })
                .then(function (payload) {
                    try {
                        apply(payload);
                    } catch (e) {
                        // ⚠️ SILENT, DELIBERATELY, AND `pixel.js` SAYS WHY: every
                        // failure this can see is our problem rather than the
                        // visitor's, and the right behaviour on somebody else's
                        // website is to say nothing at all.
                    }
                })
                .catch(function () {});
        } catch (e) {
            // As above.
        }
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
