# Simulasi Pilihanraya — Design Spec

**Date:** 2026-07-15
**Author:** Chillo + Claude (planning by Fable, build by Opus)
**Status:** Approved for build

## Goal

Revamp the existing **Pusat Simulasi** page into **Simulasi Pilihanraya** — an
Excel-workbook-style, editable election simulator that mirrors the
`simulasi-PH-vs-BN-v2.xlsx` → `SIMULASI-2026` sheet, generalised from a fixed
PH-vs-BN 1-lawan-1 model to an N-corner contest (1v1 up to 6 penjuru) between
any picked parties/coalitions, and auto-fed with the latest DPPR voter roll for
the selected Parlimen/KADUN.

## Decisions (confirmed with user)

1. **Menu rename:** `Pusat Simulasi` → `Simulasi Pilihanraya`.
2. **Tab strategy:** *Replace* the existing `PH vs BN (1 lawan 1)` tab (relabel
   to `Simulasi Kerusi`) with the new generalised Excel table. The old
   `Simulasi1v1.jsx` slider view is superseded and deleted.
3. **Persistence:** Session-only (no DB migration). Instead, the user can
   **download the simulation result as a professional PDF**.
4. **Latest DPPR source:** the app's active upload batches (the same "current
   roll" every other Pilihanraya page reads via `rollQuery()` +
   `bangsaBucket()`), not the `DptUpload` gazette table — keeps it consistent.

## Tech context (confirmed)

Laravel 12 + Inertia v2 + React 18 + Tailwind 3, `lucide-react`, `recharts`,
dompdf for PDFs. Module theme tokens in
`resources/js/Pages/Pilihanraya/theme.js` (`usePilihanrayaTheme()` → `t.*`).
Shared palette/formatters in `resources/js/Pages/Pilihanraya/analisa/shared.js`
(`partyColor`, `KAUM_LABEL` incl. `lain: 'Lain-lain'`, `fmt`, `pct`, `safeDiv`).

## Workbook → app mapping

The `SIMULASI-2026` sheet has three sections plus a summary:

- **PENGUNDI (DPPR 2026)** — Melayu / Cina / India voter counts (blue inputs).
  → Section **PENGUNDI** (drop "DPPR 2026"), add **Lain-lain** row. Auto-filled
  from DPPR; still editable.
- **ANDAIAN SENARIO 2026** — per-kaum `% Turnout`, `% Sokongan PH`
  (blue/yellow). Note: `baki % = BN`.
  → Section **ANDAIAN SENARIO** (drop "2026"), one `% Sokongan` column per
  contesting party except the last (residual). Subtitle `baki % = {lastParty}`.
- **KEPUTUSAN SIMULASI** — per-kaum Undi Keluar / Undi PH / Undi BN / % PH,
  JUMLAH. → generalised to Undi per party + % per kaum + JUMLAH (computed).
- **Summary** — % Turnout Keseluruhan, Undi Diperlukan (50%+1),
  MAJORITI (top1 − top2), STATUS (`{winner} MENANG`).

### N-corner math (generalises the workbook)

For each kaum `k` and parties `p ∈ 1..N`:
```
keluar_k    = pengundi_k * turnout_k
undi_k[p]   = keluar_k * sokongan_k[p]        for p = 1..N-1   (inputs)
undi_k[N]   = keluar_k - Σ undi_k[1..N-1]     (residual = last party, "baki %")
```
Guard: Σ sokongan_k[1..N-1] clamped ≤ 1 so the residual never goes negative.
Totals: `undi[p] = Σ_k undi_k[p]`, `keluar = Σ_k keluar_k`,
`perlu = floor(keluar/2)+1`, winner = party with most votes,
`majoriti = top1 − top2`. With N=2, parties `[PH, BN]` reproduces the workbook
exactly (`Undi BN = Undi Keluar − Undi PH`).

Extracted to a pure, testable module `simulation/nCornerModel.js`.

## Files

### Rename (task 2)
- `resources/js/Layouts/AuthenticatedLayout.jsx` (~L196): label → `Simulasi Pilihanraya`.
- `resources/js/Pages/Pilihanraya/Simulasi.jsx`: `<Head>` title, shell `title`,
  TAB label `PH vs BN (1 lawan 1)` → `Simulasi Kerusi`.

### Backend (task 3)
- `app/Services/Pilihanraya/ElectionAnalyticsService.php`: new
  `pengundiByKaum(array $f): array` → `{melayu,cina,india,lain,jumlah,source}`
  using `rollQuery($f)->groupBy('bangsa')` + `bangsaBucket()`.
- `app/Http/Controllers/PilihanrayaController.php`:
  - `simulasiPengundi(Request)` → JSON via `f($request)`.
  - extend `simulasi()` props with `simulasiParties`, `penjuruOptions`.
  - `simulasiPdf(Request)` → dompdf download (task 6).
- `routes/web.php` (pilihanraya group): `GET /api/simulasi/pengundi`,
  `POST /simulasi/pdf`.

### Frontend (tasks 4–5)
- `resources/js/Pages/Pilihanraya/simulation/nCornerModel.js` (pure calc).
- `resources/js/Pages/Pilihanraya/components/SimulasiPilihanraya.jsx` (new tab).
- `resources/js/Pages/Pilihanraya/components/EditableCell.jsx` (shared blue
  input cell — number + percent modes, lifted/generalised from Borang14).
- delete `resources/js/Pages/Pilihanraya/components/Simulasi1v1.jsx`.

### PDF (task 6)
- `resources/views/pdf/simulasi-pilihanraya.blade.php` — dark header + accent
  bar + KPI cells + party bars + per-kaum table + SULIT badge, styled after
  `pdf/analisa-comparison.blade.php`.

## Party/penjuru options

Controller constant (no migration), mirroring Borang14's `PENJURU`:
`PH, BN, PN, PEJUANG, MUDA, BEBAS` with Malay labels; penjuru `2..6` labelled
`1 vs 1 / 3 Penjuru / … / 6 Penjuru`. Colours via `partyColor()`.

## Build sequence

1. Spec + commit → 2. Rename → 3. Backend endpoint + props → 4. nCornerModel →
5. SimulasiPilihanraya.jsx + EditableCell → 6. PDF → 7. wire tab, delete old,
`npm run build`, verify, commit & push (auto-deploys to cPanel).

## Edge cases

- No active batch / zero DPPR → endpoint returns zeros + `source: null`; cells
  stay editable with workbook defaults as placeholders; show empty-state note.
- Sokongan inputs clamp so residual ≥ 0; red hint when a kaum row hits 100%.
- Parlimen name resolution reuses `resolveFilters()` (id → name) already in the
  controller's `f()` helper.
