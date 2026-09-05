# UI Review Rig

The UI review rig is a Playwright script (`ui-shots.mjs`) that automatically captures screenshots, HTML dumps, and Axe accessibility reports for key pages.

## How to run the rig

Run the script from the `app` directory using Node.js:
```bash
node scripts/ui-shots.mjs
```

### Filtering with `--only`

You can run the rig for a specific subset of screens using the `--only` flag with a regex pattern:
```bash
node scripts/ui-shots.mjs --only=feedback
node scripts/ui-shots.mjs --only="staff-audit"
```

## What it seeds

The rig automatically seeds the database using `UiReviewSeeder` before running the browser checks. This provides a consistent set of businesses, locations, and users.

### Seeded Logins

You can log in manually using the following seeded accounts. The password for all accounts is the factory default (`password`).

- **Owner:** `owner2@business.com`
- **Setup/Admin:** `setup@business.com`
- **Staff:** `staff@business.com`

## Output Directory

All captures (PNG screenshots, HTML dumps, and Axe accessibility JSON reports / SUMMARY.txt) are saved to:
`storage/app/ui-review`

## Workflow Order

Always follow the "build → commit → rig" order:
1. **Build:** Make your UI changes.
2. **Commit:** Commit your changes (one concern per commit).
3. **Rig:** Run the rig (`node scripts/ui-shots.mjs`) to generate new captures and verify that there are zero critical/serious accessibility violations.
