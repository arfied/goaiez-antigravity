---
name: goaiez-schema
description: Migrations, tenancy and row-level security. Use before writing any migration or when the schema stage is red.
---

# SCHEMA, TENANCY AND RLS

> ⛔⛔ **TENANCY RETROFITTED IS TENANCY BROKEN.** A `where tenant_id` added later
> is not the same control as one the table was built with.

## THE APP CONNECTS AS A NON-OWNER ROLE

⛔ **RLS the table owner bypasses is decoration.** The owner role migrates; the
app role runs. If the app connects as the owner, every isolation test passes
locally and the control does not exist in production — **the worst possible
ordering.**

## EVERY TENANT-OWNED TABLE

`ENABLE` **and** `FORCE ROW LEVEL SECURITY`, with a policy, **in the same
migration that creates the table.**

Without `FORCE`, the owner bypasses every policy — the exact silent no-op nothing
else would catch.

## ⛔ THE TWO THAT COST THIS PROGRAMME REAL TIME

| ⛔⛔⛔ | **`ALTER ROLE … BYPASSRLS` switched isolation off platform-wide** — and the schema stage still reported **0**, because it checked TABLES and not ROLES |
| :--- | :--- |
| ⛔⛔ | **`db:bootstrap` forced RLS and never GRANTED.** It secured the database and made it unusable |

⭐ **`permission denied` is a GRANT error, not an RLS error.** Run
`runtime/goaiez-grants.sql`. **Never reach for `BYPASSRLS`** — it looks like the
fix and it removes the property.

## ⛔ A BACKFILL CANNOT BE AN `UPDATE` IN A MIGRATION

A migration establishes no tenant, so an `UPDATE` over a FORCE-RLS table
**matches zero rows and reports success.**

⭐ **This decides designs, not just migrations.** The DDL escape for a
self-contained computation — a generated column, then `DROP EXPRESSION` — cannot
join another table. **So a stored column whose value must come from a second
table is not backfillable at all**, and the choice between storing it and asking
at read time is settled by the schema before anybody weighs it on taste.

## ⛔ AND A DIAGNOSTIC COUNT UNDER RLS ANSWERS ZERO, NOT AN ERROR

An audit query on the runtime role returns **0 rows** where it should error.
Name the migration connection, or the check certifies a bad deploy.

## NEVER A DATABASE `enum` COLUMN

A `string` cast to a PHP backed enum. A DB enum is a second source of truth that
drifts, and Postgres enum values cannot be dropped or reordered once added.
