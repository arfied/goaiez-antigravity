# 03 — THE MODULE CONTRACT

## THE EIGHT FIELDS

`make:module` **refuses a header with fewer than eight**. Fewer than these is not
a module, it is a note.

```
@intent · @provides · @emits · @consumes · @owns_table · @renders · ships: · TEST ANCHOR
```

A short header produces a brief with holes, and a brief with holes is what an
agent builds from.

## ⛔ R242 — ONE MODULE, ONE DIRECTORY, HYPHEN INCLUDED

```
app/Modules/X-126/          ✅
app/Modules/X126/           ⛔
```

**PHP namespaces cannot contain a hyphen**, so these autoload by **CLASSMAP, not
PSR-4**. If a class will not resolve, check `composer.json` before anything else.

## ⛔ GENERATED FILES

`manifest.php` and `capabilities.php` come from the plan and the tracker. The
next scaffold erases whatever you wrote there. **Edit the source and regenerate.**

## ⛔ P-163 — ONE TABLE, ONE OWNER

A module never writes another module's table — **and a test that does is the
breach wearing a test's clothes.**

## `@intent` DECIDES THE AUTONOMY FLOOR

| `SERVE` | ships autonomous on day one — **the only direction that does** |
| :--- | :--- |
| `RECOVER` `INFORM` `OBSERVE` `GROW` | do not |

⛔ **This is a real trap, and it has been sprung once already.** A module scored
`SERVE 3 / GROW 3` — a tie — and it was an **outbound dialler**. Classified
`SERVE` it ships at L2, and a new tenant starts dialling strangers autonomously
in its first hour.

**On a tie, take the lower autonomy and say so.**

## THE EVENT VOCABULARY IS TWO VOCABULARIES

Measured across the roster: **`consumes` resolves against `emits` 197 times and
against `provides` twice.** They are disjoint sets — no token is in both.

| `provides` / `consumes` | a **CALL**. Ordered: the callee must exist |
| :--- | :--- |
| `emits` / `consumes` | a **SUBSCRIPTION**. Not ordered — build the subscriber, it does not fire yet |

⛔ **Do not invent a publisher** for an event nothing emits. Record
`UNRESOLVED capability <module> — consumes <event>, nothing emits it`. There are
28 such events; the largest is `subscription.renewed`, consumed by six modules.

## `@renders` AND THE SURFACES GATE

Gate 6 refuses an **undeclared** surface. Declare what a module renders, or the
surface fails a gate every other surface passes.
