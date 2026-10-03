# Site themes — what a theme may style

A theme (`App\Modules\X103\Domain\SiteThemes`) is a stylesheet in this folder plus stored data: a palette, a font pair and
a layout per section. Every theme styles the SAME markup, which is what keeps the contact form, Google and AI-search data,
reviews, booking and tracking working under any theme. A theme stylesheet must:

- style only the class names and variables below;
- never change markup, never hide a section, never use `url()`, `@import`, `@font-face` or anything from outside the page
  (`SiteThemesTest` checks every theme for these);
- read colours and fonts from the variables, never hard-code a brand colour, so the owner's own colours still apply.

## Variables (set by the renderer from the site's colours and fonts)

| Variable | Meaning |
| :-- | :-- |
| `--color-canvas`, `--color-paper` | page background |
| `--color-card` | card background |
| `--color-band` | alternate section background |
| `--color-ink` | text |
| `--color-primary` | buttons and bands |
| `--color-on-primary` | text on the primary colour (always readable) |
| `--color-accent` | accent marks |
| `--color-accent-text` | accent used as text (always readable) |
| `--font-heading`, `--font-body` | the font pair — a system font or one of the modern families in `SiteFonts`, whose `@font-face` rules the renderer adds; a theme never declares a font file |

## Page frame

`.site-header`, `.site-header__name`, `.site-footer`, `.site-block` (every section), `.site-block__inner` (its content
column), `.site-block--band` (every other section), `.site-block--primary` (a section on the primary colour).

## Shared parts

`.stack`, `.lede`, `.eyebrow`, `.actions`, `.site-cta`, `.site-cta--primary`, `.site-cta--ghost`, `.card`, `.grid`,
`.grid--2`, `.grid--3`, `.media`, `.media--wide`, `.media--square`, `.media--portrait`.

## Sections and their layouts

Each section is `<div class="site-block TYPE …">`. A non-default layout adds one modifier class to that same element.
The first layout listed is the default and adds no class.

| Section | Element classes | Inner parts | Layouts (modifier) |
| :-- | :-- | :-- | :-- |
| Top banner | `.site-block.hero` | `.hero__grid`, `.hero__media`, `.hero-center`, `.hero-cover` | split · centered · cover (chosen by markup, not a modifier) |
| Numbers | `.site-block.stats` | `.stat-list` | row · cards (`stats--cards`) · bar (`stats--bar`) |
| Services | `.site-block.services` | `ul`, `li.card` | cards · list (`services--list`) · columns (`services--columns`) |
| About | `.site-block.about` | `.about-media` | plain · centered (`about--centered`) · split (`about--split`) |
| Reviews | `.site-block.reviews` | `.review-list`, `blockquote.card`, `cite` | cards · quote (`reviews--quote`) · row (`reviews--row`) |
| Questions | `.site-block.faq` (`#faq-x176`) | `.faq-item` | list · cards (`faq--cards`) · columns (`faq--columns`) |
| Call to action | `.site-block.cta.site-block--primary` | `.cta-band`, `.cta-photo` (`cta--photo` when it has a picture) | band |
| Booking | `.site-block.booking` | `.actions`, `.site-cta` | inline · banner (`booking--banner`) · card (`booking--card`) |
| Contact | `.site-block.contact` | `h3` + `p` pairs, `ul` | stack · columns (`contact--columns`) · card (`contact--card`) |

⛔ Never style `.faq-item`'s inner markup in a way that hides the question: X-176 reads FAQ items from the page to prove the
FAQ data Google sees is visible.

## Corners

A site's corner style (`square`, `soft`, `round`) is applied after the theme by `SiteThemes::cornersCss()`; a theme does not
need to handle it.
