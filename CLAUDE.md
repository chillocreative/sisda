# SISDA — Sistem Data Pengundi

Malaysian voter-data and election-operations platform. Laravel 12 + React 18 (Inertia) + MySQL, deployed to cPanel. Production: https://sistemdatapengundi.com

## Hard rules

- **All user-facing text is Bahasa Melayu.** No i18n layer — strings are hardcoded inline. Match surrounding copy.
- **Never let AI invent numbers.** Every figure shown to a user is computed server-side (`buildFactPayload()` and friends). Claude writes narrative only.
- **Unknown is not zero.** `pemilih`/`berdaftar` of `0` means "genuinely zero"; absent data must stay `null` and render as `—`. Coercing null→0 has repeatedly produced fabricated "-100% decline" claims. Watch for `?? 0` and `null >= 0` (true in JS).
- **Don't trust `sisda-design.md` / `sisda.md`.** Both stop at 2025-12-04, predate the entire Pilihanraya subsystem, and contradict the live system in places. Historical changelogs, not specs.

## Architecture you must know

**Geography is string-matched, not foreign-keyed.** The hierarchy is Negeri → Bandar (=Parlimen) → Kadun (=DUN) → DaerahMengundi → Lokaliti. But canvass and roll tables store `negeri`/`parlimen`/`kadun` as *free text*, matched by an uppercase-trim `nameKey()` helper reimplemented in several services. DPT names are uppercase; canvass names are mixed-case. `keanggotaan` uses cached `matched_*` columns (populated by IC lookup), not FKs. There is no referential integrity — a rename or typo silently orphans data. Most "no data for this seat" bugs start here.

**Authorization lives in controllers, not middleware.** Routes are gated by `auth` only; each method does its own check. Follow the convention exactly:

```php
$user = auth()->user();
if (!$user->isSuperAdmin() && !$user->isAdmin()) {
    abort(403, 'Unauthorized action.');
}
```

Roles: `super_admin` (everything) → `admin` (scoped to their Bandar) → `super_user` / `user` (scoped to their Kadun; may only edit own submissions). Login is by **telephone**, not email. New accounts are `pending` until approved.

**Claude config is in the database, not `.env`.** API key + model live on `claude_settings` (encrypted), managed at `/settings/claude`. There is no `ANTHROPIC_*`/`CLAUDE_*` env var. `document_model` is a separate vision-capable override for scoresheets/PDFs. Usage/cost is logged to `ai_usage_logs`.

**Two-phase AI pattern** (`ElectionComparisonService`): call 1 uses `chatWithWebSearch` for prose; call 2 re-submits that prose to a tool-free `chat()` for strict JSON. Tool-using models write poor JSON mid-loop. Every AI path has a deterministic fallback and never throws.

## Danger zones

- **Migrations run on every deploy** (`migrate --force`). Production holds live Borang 14 vote data. Never `Schema::drop`+recreate — reshape in place. Read `2026_07_16_100001_reshape_borang14_forms.php` before touching that schema: it documents MySQL error 1553 (drop FK → drop index → drop column, in that order), the SQLite cascade-delete-on-rebuild trap (`PRAGMA foreign_keys=OFF` is a no-op inside a transaction), and a `down()` that refuses to run rather than lose data. Copy that pattern.
- **`NegeriSeeder` is not idempotent** (bare `create()` in a loop). Re-running duplicates all 16 states and poisons the string-matching root. Every other geography seeder uses `firstOrCreate`/`updateOrCreate`. `DaerahMengundiSeeder` hard-deletes Kepala Batas rows.
- **`db:seed` alone does not produce a working system** — geography/master-data seeders must be run explicitly.
- **No DB transactions in the HTTP layer.** Multi-row writes (`Borang14Controller::uploadCommit()`, bulk imports) are unwrapped. Wrap new ones.
- **CI runs SQLite; production is MySQL.** Raw `ALTER ... MODIFY` needs a driver branch. Some paths (`REGEXP`, `TIMESTAMPDIFF`) are MySQL-only and therefore untestable in CI.
- **`sisda_app/` (Flutter) is a live client** on `/api/mobile/*` with Sanctum tokens and **zero test coverage**. Changing web auth or IC lookup can silently break it.

## Testing

`php artisan test`. **20 pre-existing failures are expected** — `UserFactory` doesn't set the NOT NULL `telephone` column, breaking stock Breeze tests. Baseline is 20 failed / 127 passed; only worry if that count grows.

Coverage is deliberately lopsided: Borang 14 and Analisa are well covered (incl. migration-safety and PDF-render tests). Master Data, Reports, Uploads, Call Center, Keanggotaan, and the mobile API have none.

## Deploy

Push to `main` → GitHub Actions builds assets, SSHes in, `git reset --hard`, `composer install --no-dev`, `migrate --force`, seeds `JohorSeeder`, SCPs `public/build/`. Verify with the live `manifest.json`.

`.cpanel.yml` also exists and targets the same directory without building assets — believed legacy. If cPanel's auto-deploy is still enabled, the two can race.

## Known dead code

`mobile/` (accidental Android scaffold — not the Flutter app) · War Gaming cluster: `ScenarioChat`, `SliderPanel`, `ResourcePanel`, `BriefingViewer`, `ForecastGauge`, `simulation/whatIfModel.js` (reference only each other; note `ElectionForecastService.php:409` still warns about syncing with `whatIfModel.js`) · `Components/Dropdown.jsx` · `Pages/Dashboard.jsx`. `CallCenter/*` is live in the nav but is hardcoded mock data.

## Known duplication

`HasilCulaan/Create.jsx` and `Edit.jsx` share ~800 lines of verbatim taxonomy JSX — edit both or it drifts. The 14 `MasterData/*/Index.jsx` files are one CRUD pattern copy-pasted (~4,800 lines). `canModifyRecord` is byte-identical across the two Reports index pages.
