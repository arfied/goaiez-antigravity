# Zernio — vendor documentation (saved 2026-09-12)

Everything a lane needs to build against Zernio without guessing, fetched from Zernio's public sources on 2026-09-12 by the Track 1 supervisor at the owner's request.

| File | What it is | Source |
| :--- | :--- | :--- |
| `openapi.json` | The full OpenAPI 3.1 specification: Zernio API 1.0.4, base URL `https://zernio.com/api`, every path under `/v1`, Bearer API key auth, 53 declared webhook events | https://zernio.com/openapi.json |
| `ENDPOINTS.md` | Every operation, grouped by tag, generated from the spec (935 lines) | generated |
| `MATRIX.md` | The GMB / Facebook / Instagram / WhatsApp capability matrix: each blueprint feature against the Zernio operations that serve it | generated |
| `WEBHOOK-EVENTS.md` | The webhook event names the spec declares | generated |
| `llms-full.txt` | The complete docs site as text (7 MB, 1,372 sections) | https://docs.zernio.com/llms-full.txt |
| `llms.txt` | The docs site's short index | https://docs.zernio.com/llms.txt |
| `pages/*.md` | `llms-full.txt` split one file per page; `PAGES.md` is the index | generated |

## How a lane uses this (the standard)

1. **Cite the file, not the memory.** A brief that touches Zernio names the `pages/<page>.md` and the operation in `ENDPOINTS.md` it builds against. A report quotes the request and response shape from the spec, not from recollection.
2. **One recorded real payload per operation and per webhook**, sanitised, under `app/tests/Fixtures/vendors/zernio/`. The first call a lane makes against the real API is captured and committed as the fixture the contract test replays.
3. **Contract tests** parse those fixtures and assert exactly the fields the code reads; every webhook controller replays a recorded signed request.
4. **A smoke command**: `php artisan vendor:smoke zernio` makes one authenticated read (`GET /v1/accounts`) and prints the outcome, so the owner can prove credentials and wiring in seconds after any change.
5. **No silent refusals.** Every path that declines to act logs one line with the reason and an id.
6. **Refresh**: re-download `openapi.json` and `llms-full.txt` when Zernio's `info.version` changes; the changelog page is `pages/changelog.md`.

## Provenance

Downloaded with `curl` from the public URLs above; nothing here was typed from memory. The spec's `info.version` at download: 1.0.4. The docs site is a JavaScript application (Fumadocs); its pages are not served as markdown, which is why the LLM export is the saved form.
