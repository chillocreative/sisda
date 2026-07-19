# Design: AI fallback extraction for Upload Database

Date: 2026-07-19
Status: Approved (pending written-spec review)

## Problem

The Upload Database flow (`/upload-database` → `UploadDatabaseController@store` →
`ProcessVoterUpload` job → `VoterDatabaseImport`) already parses spreadsheets with
a header-alias matcher plus a content-detection fallback. It handles clean files
and simple column reordering well, but silently produces ~0 rows on:

1. **Weird/unlabeled headers** — header names the alias list doesn't recognise.
2. **Title/junk rows & multiple tables** — header isn't in row 1; banners, blank
   rows, or several stacked tables confuse row detection.
3. **Freeform layout** — PDFs (including scanned/image PDFs) and spreadsheets
   with no real table structure.

Goal: when the fast parser can't read a file, an AI fallback reads it and
populates `pangkalan_data_pengundi` **automatically** (no confirm step).

## Non-negotiable principles

- **AI interprets structure or transcribes; it never invents.** For spreadsheets
  the AI returns only a *column mapping / region*; deterministic PHP moves the
  data across all rows. For freeform files the AI transcribes records literally.
  The row count is always `count()` of validated rows, never an AI-reported number.
- **IC validation is the hallucination guard.** Every AI-produced row passes the
  existing `normaliseIc()` rule (clean to 12 digits or drop). A fabricated or
  mis-typed IC that isn't 12 valid digits is discarded, not stored.
- **AI path is best-effort and never throws** (matches the existing SISDA AI
  architecture rule). On failure the batch completes with a recorded error note.
- **Unknown is not zero.** Absent fields stay `null` (render as `—`), never `0`.

## Decisions (from brainstorming)

| Question | Decision |
|---|---|
| Write flow | **Fully automatic** — no preview/confirm. Rely on retained raw file + deactivate/delete-batch escape hatch. |
| When AI runs | **Fallback only** — fast parser first (free/instant); AI engages only when it yields ~0 rows. |
| Freeform/PDF scope | **Full chunked extraction**, incl. scanned/image PDFs via the vision `document_model`. |

## Architecture

All work stays inside the existing `ProcessVoterUpload` job
(`dispatchAfterResponse`, so no Cloudflare 100s timeout). Batch lifecycle
unchanged: `processing → completed`, `is_active=true`, writes to
`pangkalan_data_pengundi`.

```
Upload (xlsx/xls/csv/zip/pdf)
  → VoterDatabaseImport (unchanged fast path)
      │
      ├─ rowsDetected() ≥ threshold  → done. $0, instant. (most files)
      │
      └─ rowsDetected() ~0  → AiVoterExtractor
            │
            ├─ spreadsheet (weird headers / junk rows / multi-table):
            │    • dump first ~20 rows as "Row i: a | b | c" preview → ONE chat() call
            │    • AI returns { header_row, columns{field→colIndex}, table_regions[] }
            │    • sanitizeMapping() coerces to safe fully-keyed structure
            │    • applyMapping() runs over ALL rows → bulk insert (deterministic)
            │
            └─ freeform / PDF (no table):
                 • text PDF: PdfParser text → chunk (~16k chars) → chat() per chunk
                 • scanned/image PDF: base64 document block → chat(documentModel())
                   (reuse ScoresheetExtractor:133-161 vision pattern), chunk by page-group
                 • AI returns { records:[{no_ic,nama,negeri,parlimen,kadun,
                   daerah_mengundi,lokaliti,bangsa,jantina,tahun_lahir}] }
                 • each record validated (normaliseIc) → bulk insert
```

**Threshold for "fast parser failed":** `rowsDetected() === 0`. (Conservative —
we only spend tokens when the deterministic path found literally nothing, so a
partially-read file is not needlessly re-processed. Revisit if partial reads
prove to be a real problem.)

## Components

### 1. `App\Services\Upload\AiVoterExtractor` (new)

Direct analog of `CommitteeImportMapper`, targeting `pangkalan_data_pengundi`.

- `FIELDS` = `no_ic, nama, lokaliti, kod_lokaliti, daerah_mengundi, kadun,
  parlimen, negeri, bangsa, jantina, tahun_lahir, pendaftaran_baru`.
- `ALIASES` — extended header aliases for the heuristic fallback (superset of the
  current `VoterDatabaseImport` aliases).
- `analyze(string $absPath, string $ext, ?string $filename): array` — returns
  `{ai_used, rows:[...], skipped:int, total:int, mapping|null, error|null}`.
- `analyzeSpreadsheet()` → `aiMapping()` (AI) with `heuristicMapping()` fallback →
  `sanitizeMapping()` → `applyMapping()`.
- `analyzeFreeform()` → `aiExtractRecords()` (text or vision), chunked → normalize.
- Uses `ClaudeService::chat($system, $userPrompt, $maxTokens, $timeout, $context,
  $model)` and `extractJson()`. Vision path passes `documentModel()` as `$model`
  and an array `$userPrompt` with a base64 `document`/`image` block.

### 2. `App\Imports\VoterDatabaseImport` (modify)

- Add `rowsDetected(): int` accessor exposing how many rows the fast path wrote,
  so the job can decide whether to escalate. No behavioural change otherwise.

### 3. `App\Jobs\ProcessVoterUpload` (modify)

- After each file's fast import, if `rowsDetected() === 0`, call
  `AiVoterExtractor::analyze()` and bulk-insert the returned rows (same 500-row
  chunked `PangkalanDataPengundi::insert()` the importer uses).
- Persist `ai_used`, the detected `mapping`, and any `error` for traceability
  (on the `UploadBatch` — see Data changes — and via the existing `ai_usage_logs`
  written by `ClaudeService::logUsage`). No numeric confidence is produced; the
  IC-validation drop count (`skipped`) is the quality signal.
- Retain the raw file (already retained today).
- Wrap the AI branch so it never throws; on failure record the error note and
  leave the batch `completed` with whatever count exists.

### 4. Sidebar consolidation (already implemented — related, out of scope for the plan)

`AuthenticatedLayout.jsx`: the three flat links (Upload Database, Upload DPT,
Upload Culaan) are now one collapsible **Upload** parent following the existing
Keanggotaan submenu pattern, with role-filtered submenu (super_admin sees all
three; admin sees Upload Culaan only) and auto-open on any upload route. Done in
this session; listed here only for completeness.

## Data changes

Add nullable columns to `upload_batches` for traceability (additive, idempotent
migration — reshape-in-place, no drop/recreate):

- `ai_used` boolean default 0
- `ai_detail` json nullable — `{path: 'spreadsheet'|'freeform'|'vision', mapping:
  {...}, chunks: int, skipped: int, error: ?string}`

Live production holds real upload history, so migration is strictly additive with
a `down()` that only drops the two new columns.

## Error handling

- Claude disabled / API error / bad JSON → `AiVoterExtractor` returns
  `ai_used=false` (spreadsheet) or empty rows (freeform) with an `error`; the job
  records it and the batch completes (possibly 0 rows) rather than 500-ing.
- ZIP-slip guard and per-file try/catch in `ProcessVoterUpload` are unchanged.
- Invalid ICs dropped per-row; `skipped` counts them.

## Testing

CI is SQLite and cannot reach the live Claude API, so tests fake it:

- **`AiVoterExtractor::applyMapping()`** — pure/deterministic. Unit-test that a
  given (rows, mapping) yields the expected `pangkalan_data_pengundi` rows,
  junk/title rows are skipped, and invalid ICs are dropped. No AI needed.
- **`heuristicMapping()`** — alias matching over sample headers.
- **Freeform normalize** — feed a canned AI `records` array, assert validated rows.
- **AI calls themselves** — mock `ClaudeService` (bind a fake returning fixed
  JSON) and assert the escalation branch fires only when `rowsDetected() === 0`.
- Live spreadsheet/PDF extraction accuracy is validated manually against real
  problem files (no CI coverage possible for the LLM itself).

## Out of scope (YAGNI)

- No preview/confirm UI (explicitly rejected).
- No change to Upload DPT / Upload Culaan parsing.
- No re-processing of files the fast path already read partially.
- No mapping-override UI.
