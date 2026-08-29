# 07 — THE TECH STACK

**Identical to `goaiez-review-system`. The owner chose these deliberately; do not
"correct" code back toward what a specification document says.**

⛔ **The master plan governs WHAT to build. This file governs HOW.** The plan
names no file in this repository and does not reach the stack.

## PLATFORM

| PHP | **8.4** |
| :--- | :--- |
| Laravel | **13** |
| UI | **Livewire 4 + Alpine.js** — ⛔ **not** Inertia, not React, not Vue |
| Database | **PostgreSQL 16 + pgvector** — ⛔ not MySQL |
| CSS | **Tailwind 4, CSS-first.** ⛔ **There is no `tailwind.config.js`** — tokens live in the `@theme` block of `resources/css/app.css` |
| Components | **hand-rolled Blade.** ⛔ no shadcn/ui, no Flux, no Filament |
| Toasts | `masmerise/livewire-toaster` `^2.10` — the approved mechanism, do not hand-roll one |
| Tests | **Pest 4** |
| Queue | **Horizon 5 on Redis** is the design. ⚠️ **Production runs `database` for queue, cache and session** — see below |
| Realtime | **Reverb** |
| Auth | **Fortify + Sanctum + Socialite.** Passkeys ship *inside* Fortify; magic-link stays as the fallback |
| Billing | **Cashier/Stripe + Authorize.Net**, both live behind one gateway seam (`X-198`) |
| Object storage | **Cloudflare R2** via Laravel's `s3` driver |
| Type | **Archivo** display · **Public Sans** body · **IBM Plex Mono** data — ⛔ **not Inter** |

⛔ **No `laravel/telescope`, no `laravel/pulse`.** A `require-dev` package cannot
serve observability (production installs `--no-dev`), and moving one to `require`
puts a request-payload recorder into the process handling end-customers' phone
numbers and review text.

## ⚠️ THE STACK DECISION AND THE RUNNING BOX DISAGREE, AND BOTH ARE TRUE

Redis and Horizon are the **design**. The deployment target is a **cPanel VPS**
where Redis is not installed and queue, cache and session all run on `database`.

⭐ **Build against the design; do not assume the box.** A worker's queue names are
a command line on a box, which is why ⛔ **you never route a job to a named
queue** — a job dispatched to a name nothing pops is written to `jobs` with its
payload and **never runs**.

## VENDORS — AND EVERY ONE OF THEM IS BEHIND A MODULE

⛔ **Never name a vendor inside a feature module.** That is a `boundary`
violation and it fails the COMMIT.

| carriers, SMS, 10DLC, WhatsApp, voice, brand registration | **Infobip**, behind `C-Telephony` |
| :--- | :--- |
| email | **Amazon SES**, over the stock `smtp` transport |
| models | **multi-provider from day one**, and the assignment is a ROW — `C-Ai` / `X-219` |
| gateways | Stripe + Authorize.Net, behind `X-198` |

## CROSS-CUTTING ENGINEERING RULES

| ⛔ | **Integer cents + a currency code, everywhere.** `17999`, never `179.99`. Retrofitting this means rewriting billing |
| :--- | :--- |
| ⛔ | **Never a database `enum` or `set` column.** A `string` cast to a PHP backed enum, validated with `Rule::enum()`. A DB enum is a second source of truth that drifts, and Postgres values cannot be dropped or reordered once added |
| ⛔ | **Never store a raw IP.** No fingerprinting, no session replay, no keystroke capture |
| ⛔ | **`env()` only inside `config/`.** Operational values are ROWS (P-193). The `boundary` stage fails the COMMIT on this |
| ⛔ | **Every tenant-owned table: `ENABLE` + `FORCE ROW LEVEL SECURITY`, in the creating migration.** The app connects as a **non-owner** role — see the `goaiez-schema` skill |
| ⭐ | **Do the Laravel way**: `php artisan make:*` with `--no-interaction`; validation in **form requests**; authorization in **policies**; named routes via `route()` |

## DESIGN

| ⭐ | **Colour is information, never decoration** — and never the sole indicator; always paired with an icon or a label |
| :--- | :--- |
| ⭐ | **Outcome language only** — every string names what the person controls, never how the system is built. A verb keeps its identity through the flow: Publish → Published |
| ⭐ | **WCAG 2.2 AA**, `prefers-reduced-motion` honoured, works at 320px, body text never below 16px |

## ⚠️ WHAT IS DELIBERATELY NOT INHERITED

The old tree's **rulings** — its prices, its thresholds, its consent lanes — are
**not** carried over. They belong to that build. The master plan is the authority
on every point of fact here.

**What is inherited is this file: the stack, and the engineering rules that are
properties of the stack rather than of the product.**
