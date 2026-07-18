# Flutter Client 2b-ii — Culaan Write Form (Checklist Hub) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the `CulaanFormScreen` stub with the real checklist-hub Culaan write form — 7 sections (5 always-present + Isi Rumah/Bantuan gated on `has_sumbangan`), data-driven section editors from the `culaan/options` taxonomy, draft-in-SQLite lifecycle, masked-create prefill, and the `Hantar` → queue → sync path — so the field worker can record a Culaan offline and have it sync when signal returns.

**Architecture:** A **data-driven** form. All 7 sections and their fields are declared once as pure-Dart `SectionSpec`/`FieldSpec` data (`culaan_form_spec.dart`); a single generic `SectionEditorScreen` renders any section from its spec, so there is **no per-section widget duplication**. The checklist hub (`CulaanFormScreen`) owns one `CulaanDraft`, loaded-or-created on open and persisted to the Drift `DraftStore` on every section save (draft lives in SQLite from the first save — the privacy line: a masked-prefill draft stores `'****'` for sensitive fields, never real values the agent can't see). Completeness and required-field gating are **pure functions** over `(fields, hasSumbangan)`, unit-tested without widgets. `Hantar` sets the draft to `queued`, clears any failure reason, and triggers `syncEngineProvider.syncNow` — the same enqueue path 2b-i's tests seed by hand.

**Tech Stack:** Flutter, Riverpod (`flutter_riverpod ^2.6.1`), Drift (`^2.20.0`, in-memory for tests via `AppDatabase.forTesting()`), `uuid ^4.5.1` (already a dependency, unused so far), `mocktail` + `flutter_test` for tests. No new packages.

## Global Constraints

- **All user-facing text is Bahasa Melayu**, matching existing copy (`docs/superpowers/specs/2026-07-17-aplikasi-mudah-alih-user-design.md`). No i18n layer.
- **`lib/models/` and `lib/sync/` stay Flutter-free.** 2b-ii adds only `lib/features/culaan/**` (UI + providers). Do **not** add a Flutter import to `models/` or `sync/`. The new pure spec file (`culaan_form_spec.dart`) lives under `features/` but must import **no** Flutter (`package:flutter/*`) — it is pure Dart so it unit-tests without a widget harness.
- **Never invent numbers or taxonomy.** Every dropdown/checkbox list comes from `GET /api/mobile/culaan/options` (contract §11). Do not hardcode a taxonomy list the endpoint owns.
- **`voter_color` is server-computed** — never send it. `CulaanDraft.toApiPayload()` already strips it (`lib/models/culaan_draft.dart:57`); do not re-add it.
- **Idempotency key = one UUID per draft, reused across every retry.** Generate it **once** when the draft is created (`CulaanDraft.newDraft`), never per submit attempt (contract §"Idempotency").
- **Masked sensitive fields are locked read-only.** When `lockedSourceId != null`, the fields in `SENSITIVE_FIELDS` (`no_ic, umur, bangsa, no_tel, alamat, poskod, negeri, bandar, pendapatan_isi_rumah`) render as `'****'` and are non-editable; the submit sends `'****'` + `locked_source_id` and the server substitutes real values (contract §"Masked-create").
- **Only the store-contract §13 field set exists on mobile.** No IC-photo upload, no `is_deceased`, no `update_status_pengundi`, no `jkm_program`/`status_sumbangan`/`tarikh_sumbangan`/`jumlah_dipohon`/`jumlah_dilulus`/`jumlah_dibayar`. **`perkeso_bantuan` and `zpp_jenis_bantuan` are scoped OUT of V1** (both server-optional; the mobile `options` endpoint provides no taxonomy for them) — see Task 1's field table.
- **Section-complete rule (locked in with the user):** a section shows `✓` when all its **server-required** fields are non-empty. `Maklumat Politik` and `Status Pengundi` have no required fields → they render a neutral `pilihan` tag, never `✓`/`Belum diisi`, and never block `Hantar`. The progress line counts required-bearing sections only.
- **Baseline:** `flutter test` from `sisda_app/` is green today (the 2a suite + 2b-i widget/controller tests). Every task ends with a green `flutter test`. Cancel Drift query-stream subscribers before a widget test ends (the `disposeAndDrainTimers` helper pattern from `test/features/perlu_perhatian_test.dart:29-32`) or a real `Timer` leaks and the test flags a pending timer.

---

## File Structure

| File | Responsibility |
|---|---|
| `sisda_app/lib/features/culaan/culaan_form_spec.dart` | **Create** — pure Dart. `FieldKind` enum, `FieldSpec`, `SectionSpec`, the 7 section specs, `SENSITIVE_FIELDS`, and the pure functions `sectionsFor(bool hasSumbangan)`, `isSectionComplete(SectionSpec, Map)`, `incompleteRequiredSections(Map, bool)`, `hasLainSelected(FieldSpec, List)`. NO Flutter import. |
| `sisda_app/lib/features/culaan/culaan_options.dart` | **Create** — typed `CulaanOptions` model + `CulaanOptions.fromJson` (pekerjaan list, `jenis_pekerjaan` cascading map, jenis_sumbangan, tujuan_sumbangan [may be empty], bantuan_lain, pemilik_rumah). Pure Dart. |
| `sisda_app/lib/features/culaan/culaan_options_provider.dart` | **Create** — `culaanOptionsProvider` (`FutureProvider.autoDispose<CulaanOptions>`) wrapping `mobileApiProvider.culaanOptions()`. |
| `sisda_app/lib/features/culaan/field_widgets.dart` | **Create** — the small field widgets a `FieldSpec` renders to: `CulaanTextField`, `CulaanNumberField`, `CulaanDropdownField`, `CulaanMultiSelectField`, `CulaanCascadingField` (pekerjaan→jenis_pekerjaan), and the locked `'****'` read-only variant. |
| `sisda_app/lib/features/culaan/section_editor_screen.dart` | **Create** — one generic screen that renders a `SectionSpec` into a scrollable form and returns the merged field map via `Navigator.pop(context, Map<String,dynamic>)`. |
| `sisda_app/lib/features/culaan/culaan_draft_loader.dart` | **Create** — `idGeneratorProvider` (`Provider<String Function()>`) + `loadOrCreateDraft(...)` (load existing by key, or new + masked-prefill seed). |
| `sisda_app/lib/features/culaan/culaan_form_screen.dart` | **Modify** — replace the stub body (keep the `({String? draftKey, Voter? prefillVoter})` constructor) with the checklist hub: progress line, section rows, `Ada Sumbangan` toggle, `Hantar`. |
| `sisda_app/lib/providers.dart` | **Modify** — export nothing new required here, but `idGeneratorProvider` may live here instead of the loader file if preferred (plan places it in the loader file). |
| `sisda_app/test/features/culaan_form_spec_test.dart` | **Create** — unit tests for the pure spec functions. |
| `sisda_app/test/features/culaan_options_test.dart` | **Create** — `CulaanOptions.fromJson` parse tests. |
| `sisda_app/test/features/culaan_field_widgets_test.dart` | **Create** — widget tests for the field widgets (dropdown, multiselect, cascading, locked). |
| `sisda_app/test/features/section_editor_screen_test.dart` | **Create** — the generic editor returns merged fields; locked fields read-only. |
| `sisda_app/test/features/culaan_draft_loader_test.dart` | **Create** — blank/prefill/reopen load-or-create. |
| `sisda_app/test/features/culaan_form_screen_test.dart` | **Create** — hub rendering, toggle add/removes 2 sections, completeness, Hantar gating + enqueue + syncNow, reopen clears failure. |

---

## Task 1: Form spec — pure section/field model + gating functions

**Files:**
- Create: `sisda_app/lib/features/culaan/culaan_form_spec.dart`
- Test: `sisda_app/test/features/culaan_form_spec_test.dart`

**Interfaces:**
- Produces:
  - `enum FieldKind { text, multilineText, number, dropdown, multiSelect, cascading }`
  - `class FieldSpec { final String key; final String label; final FieldKind kind; final bool required; final bool sensitive; final String? lainKey; final String? optionsKey; const FieldSpec(...); }`
  - `class SectionSpec { final String key; final String title; final List<FieldSpec> fields; final bool sumbanganGated; const SectionSpec(...); bool get isOptional; }`
  - `const Set<String> kSensitiveFields`
  - `List<SectionSpec> sectionsFor(bool hasSumbangan)`
  - `bool isSectionComplete(SectionSpec section, Map<String, dynamic> fields)`
  - `List<SectionSpec> incompleteRequiredSections(Map<String, dynamic> fields, bool hasSumbangan)`
  - `bool hasLainSelected(FieldSpec spec, dynamic value)`
- Consumes: nothing.

**Field → section table** (the authoritative mobile field set — store contract §13; `optionsKey` names the `culaan/options` list a dropdown/multiselect/cascading field reads):

| Section (`key` / `title`) | Field `key` | `label` | `kind` | `required` | `sensitive` | `lainKey` | `optionsKey` |
|---|---|---|---|---|---|---|---|
| `peribadi` / Maklumat Peribadi | `nama` | Nama | text | ✓ | | | |
| | `no_ic` | No. Kad Pengenalan | number | ✓ | ✓ | | |
| | `umur` | Umur | number | ✓ | ✓ | | |
| | `no_tel` | No. Telefon | text | ✓ | ✓ | | |
| | `bangsa` | Bangsa | text | ✓ | ✓ | | |
| `alamat` / Maklumat Alamat | `alamat` | Alamat | multilineText | ✓ | ✓ | | |
| | `poskod` | Poskod | text | ✓ | ✓ | | |
| | `negeri` | Negeri | text | ✓ | ✓ | | |
| | `bandar` | Bandar | text | ✓ | ✓ | | |
| `kawasan` / Kawasan Mengundi | `parlimen` | Parlimen | text | ✓ | | | |
| | `kadun` | DUN / Kadun | text | ✓ | | | |
| | `mpkk` | MPKK | text | | | | |
| | `daerah_mengundi` | Daerah Mengundi | text | | | | |
| | `lokaliti` | Lokaliti | text | | | | |
| `politik` / Maklumat Politik | `keahlian_parti` | Keahlian Parti | text | | | | |
| | `kecenderungan_politik` | Kecenderungan Politik | text | | | | |
| `status` / Status Pengundi | `status_pengundi` | Status Pengundi | text | | | | |
| | `nota` | Nota | multilineText | | | | |
| `isi_rumah` / Isi Rumah *(sumbanganGated)* | `bil_isi_rumah` | Bilangan Isi Rumah | number | ✓ | | | |
| | `pendapatan_isi_rumah` | Pendapatan Isi Rumah | number | | ✓ | | |
| | `pekerjaan` | Pekerjaan | dropdown | ✓ | | | `pekerjaan` |
| | `jenis_pekerjaan` | Jenis Pekerjaan | cascading | ✓ | | `jenis_pekerjaan_lain` | `jenis_pekerjaan` |
| | `pemilik_rumah` | Pemilik Rumah | dropdown | ✓ | | `pemilik_rumah_lain` | `pemilik_rumah` |
| `bantuan` / Bantuan *(sumbanganGated)* | `jenis_sumbangan` | Jenis Sumbangan | multiSelect | ✓ | | `jenis_sumbangan_lain` | `jenis_sumbangan` |
| | `tujuan_sumbangan` | Tujuan Sumbangan | multiSelect | ✓ | | `tujuan_sumbangan_lain` | `tujuan_sumbangan` |
| | `bantuan_lain` | Bantuan Lain | multiSelect | ✓ | | `bantuan_lain_lain` | `bantuan_lain` |
| | `isejahtera_program` | Program iSejahtera | text | | | | |
| | `bkb_program` | Program BKB | text | | | | |
| | `jumlah_bantuan_tunai` | Jumlah Bantuan Tunai (RM) | number | | | | |
| | `jumlah_wang_tunai` | Jumlah Wang Tunai (RM) | number | | | | |

> `jenis_pekerjaan`, `jenis_sumbangan`, `tujuan_sumbangan`, `bantuan_lain` store **`List<String>`** in `fields`. All others store `String`. The `*_lain` companion keys (`jenis_pekerjaan_lain`, etc.) are stored as `String` when the corresponding "Lain-lain" option is selected; the server folds them back in (`CulaanPayloadNormalizer`). Scoped OUT of V1: `perkeso_bantuan`, `zpp_jenis_bantuan`, `perkeso_bantuan_lain` (server-optional, no mobile taxonomy).

- [ ] **Step 1: Write the failing tests**

```dart
// sisda_app/test/features/culaan_form_spec_test.dart
import 'package:flutter_test/flutter_test.dart';
import 'package:sisda_app/features/culaan/culaan_form_spec.dart';

void main() {
  test('sectionsFor(false) yields 5 sections; (true) adds Isi Rumah + Bantuan', () {
    expect(sectionsFor(false).map((s) => s.key),
        ['peribadi', 'alamat', 'kawasan', 'politik', 'status']);
    expect(sectionsFor(true).map((s) => s.key),
        ['peribadi', 'alamat', 'kawasan', 'politik', 'status', 'isi_rumah', 'bantuan']);
  });

  test('kSensitiveFields matches the contract SENSITIVE_FIELDS exactly', () {
    expect(kSensitiveFields, {
      'no_ic', 'umur', 'bangsa', 'no_tel', 'alamat',
      'poskod', 'negeri', 'bandar', 'pendapatan_isi_rumah',
    });
  });

  test('peribadi complete only when all 5 required fields present', () {
    final peribadi = sectionsFor(false).firstWhere((s) => s.key == 'peribadi');
    expect(isSectionComplete(peribadi, {'nama': 'Ali'}), isFalse);
    expect(
        isSectionComplete(peribadi, {
          'nama': 'Ali', 'no_ic': '800101015555', 'umur': '44',
          'no_tel': '0121234567', 'bangsa': 'Melayu',
        }),
        isTrue);
  });

  test('kawasan complete on parlimen+kadun; optional fields ignored', () {
    final kawasan = sectionsFor(false).firstWhere((s) => s.key == 'kawasan');
    expect(isSectionComplete(kawasan, {'parlimen': 'P.044', 'kadun': 'N.11'}), isTrue);
    expect(isSectionComplete(kawasan, {'parlimen': 'P.044'}), isFalse);
  });

  test('optional-only sections are never required and count as complete', () {
    final politik = sectionsFor(false).firstWhere((s) => s.key == 'politik');
    expect(politik.isOptional, isTrue);
    expect(isSectionComplete(politik, {}), isTrue); // nothing required → complete
  });

  test('incompleteRequiredSections excludes optional sections', () {
    final missing = incompleteRequiredSections({}, false).map((s) => s.key);
    expect(missing, containsAll(['peribadi', 'alamat', 'kawasan']));
    expect(missing, isNot(contains('politik')));
    expect(missing, isNot(contains('status')));
  });

  test('has_sumbangan adds Isi Rumah/Bantuan required fields to the gate', () {
    final full = {
      'nama': 'Ali', 'no_ic': '800101015555', 'umur': '44',
      'no_tel': '0121234567', 'bangsa': 'Melayu', 'alamat': 'Jln 1',
      'poskod': '13200', 'negeri': 'Pulau Pinang', 'bandar': 'Kepala Batas',
      'parlimen': 'P.044', 'kadun': 'N.11',
    };
    expect(incompleteRequiredSections(full, false), isEmpty);
    // same fields but has_sumbangan on: Isi Rumah + Bantuan now incomplete
    final missing = incompleteRequiredSections(full, true).map((s) => s.key);
    expect(missing, ['isi_rumah', 'bantuan']);
  });

  test('array required field needs at least one entry', () {
    final bantuan = sectionsFor(true).firstWhere((s) => s.key == 'bantuan');
    expect(isSectionComplete(bantuan, {
      'jenis_sumbangan': <String>[], 'tujuan_sumbangan': ['Pendidikan'],
      'bantuan_lain': ['JKM'],
    }), isFalse);
    expect(isSectionComplete(bantuan, {
      'jenis_sumbangan': ['Barangan'], 'tujuan_sumbangan': ['Pendidikan'],
      'bantuan_lain': ['JKM'],
    }), isTrue);
  });

  test('hasLainSelected true when a selected value contains "lain"', () {
    final jenisSumbangan =
        sectionsFor(true).firstWhere((s) => s.key == 'bantuan').fields
            .firstWhere((f) => f.key == 'jenis_sumbangan');
    expect(hasLainSelected(jenisSumbangan, ['Barangan', 'Lain-lain']), isTrue);
    expect(hasLainSelected(jenisSumbangan, ['Barangan']), isFalse);
  });
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd sisda_app && flutter test test/features/culaan_form_spec_test.dart`
Expected: FAIL — `culaan_form_spec.dart` / its symbols not defined.

- [ ] **Step 3: Write the implementation**

```dart
// sisda_app/lib/features/culaan/culaan_form_spec.dart
// PURE DART — no Flutter import. Declares the 7-section Culaan form and the
// completeness/gating functions the checklist hub and Hantar button use.

enum FieldKind { text, multilineText, number, dropdown, multiSelect, cascading }

class FieldSpec {
  final String key;
  final String label;
  final FieldKind kind;
  final bool required;
  final bool sensitive;

  /// Companion "*_lain" field key revealed when a "Lain-lain" option is chosen.
  final String? lainKey;

  /// Key into the `culaan/options` map for dropdown/multiSelect/cascading fields.
  final String? optionsKey;

  const FieldSpec({
    required this.key,
    required this.label,
    required this.kind,
    this.required = false,
    this.sensitive = false,
    this.lainKey,
    this.optionsKey,
  });

  bool get isArray => kind == FieldKind.multiSelect || kind == FieldKind.cascading;
}

class SectionSpec {
  final String key;
  final String title;
  final List<FieldSpec> fields;
  final bool sumbanganGated;

  const SectionSpec({
    required this.key,
    required this.title,
    required this.fields,
    this.sumbanganGated = false,
  });

  /// A section with no server-required fields (Maklumat Politik, Status Pengundi).
  bool get isOptional => fields.every((f) => !f.required);
}

const Set<String> kSensitiveFields = {
  'no_ic', 'umur', 'bangsa', 'no_tel', 'alamat',
  'poskod', 'negeri', 'bandar', 'pendapatan_isi_rumah',
};

const List<SectionSpec> _allSections = [
  SectionSpec(key: 'peribadi', title: 'Maklumat Peribadi', fields: [
    FieldSpec(key: 'nama', label: 'Nama', kind: FieldKind.text, required: true),
    FieldSpec(key: 'no_ic', label: 'No. Kad Pengenalan', kind: FieldKind.number, required: true, sensitive: true),
    FieldSpec(key: 'umur', label: 'Umur', kind: FieldKind.number, required: true, sensitive: true),
    FieldSpec(key: 'no_tel', label: 'No. Telefon', kind: FieldKind.text, required: true, sensitive: true),
    FieldSpec(key: 'bangsa', label: 'Bangsa', kind: FieldKind.text, required: true, sensitive: true),
  ]),
  SectionSpec(key: 'alamat', title: 'Maklumat Alamat', fields: [
    FieldSpec(key: 'alamat', label: 'Alamat', kind: FieldKind.multilineText, required: true, sensitive: true),
    FieldSpec(key: 'poskod', label: 'Poskod', kind: FieldKind.text, required: true, sensitive: true),
    FieldSpec(key: 'negeri', label: 'Negeri', kind: FieldKind.text, required: true, sensitive: true),
    FieldSpec(key: 'bandar', label: 'Bandar', kind: FieldKind.text, required: true, sensitive: true),
  ]),
  SectionSpec(key: 'kawasan', title: 'Kawasan Mengundi', fields: [
    FieldSpec(key: 'parlimen', label: 'Parlimen', kind: FieldKind.text, required: true),
    FieldSpec(key: 'kadun', label: 'DUN / Kadun', kind: FieldKind.text, required: true),
    FieldSpec(key: 'mpkk', label: 'MPKK', kind: FieldKind.text),
    FieldSpec(key: 'daerah_mengundi', label: 'Daerah Mengundi', kind: FieldKind.text),
    FieldSpec(key: 'lokaliti', label: 'Lokaliti', kind: FieldKind.text),
  ]),
  SectionSpec(key: 'politik', title: 'Maklumat Politik', fields: [
    FieldSpec(key: 'keahlian_parti', label: 'Keahlian Parti', kind: FieldKind.text),
    FieldSpec(key: 'kecenderungan_politik', label: 'Kecenderungan Politik', kind: FieldKind.text),
  ]),
  SectionSpec(key: 'status', title: 'Status Pengundi', fields: [
    FieldSpec(key: 'status_pengundi', label: 'Status Pengundi', kind: FieldKind.text),
    FieldSpec(key: 'nota', label: 'Nota', kind: FieldKind.multilineText),
  ]),
  SectionSpec(key: 'isi_rumah', title: 'Isi Rumah', sumbanganGated: true, fields: [
    FieldSpec(key: 'bil_isi_rumah', label: 'Bilangan Isi Rumah', kind: FieldKind.number, required: true),
    FieldSpec(key: 'pendapatan_isi_rumah', label: 'Pendapatan Isi Rumah', kind: FieldKind.number, sensitive: true),
    FieldSpec(key: 'pekerjaan', label: 'Pekerjaan', kind: FieldKind.dropdown, required: true, optionsKey: 'pekerjaan'),
    FieldSpec(key: 'jenis_pekerjaan', label: 'Jenis Pekerjaan', kind: FieldKind.cascading, required: true, lainKey: 'jenis_pekerjaan_lain', optionsKey: 'jenis_pekerjaan'),
    FieldSpec(key: 'pemilik_rumah', label: 'Pemilik Rumah', kind: FieldKind.dropdown, required: true, lainKey: 'pemilik_rumah_lain', optionsKey: 'pemilik_rumah'),
  ]),
  SectionSpec(key: 'bantuan', title: 'Bantuan', sumbanganGated: true, fields: [
    FieldSpec(key: 'jenis_sumbangan', label: 'Jenis Sumbangan', kind: FieldKind.multiSelect, required: true, lainKey: 'jenis_sumbangan_lain', optionsKey: 'jenis_sumbangan'),
    FieldSpec(key: 'tujuan_sumbangan', label: 'Tujuan Sumbangan', kind: FieldKind.multiSelect, required: true, lainKey: 'tujuan_sumbangan_lain', optionsKey: 'tujuan_sumbangan'),
    FieldSpec(key: 'bantuan_lain', label: 'Bantuan Lain', kind: FieldKind.multiSelect, required: true, lainKey: 'bantuan_lain_lain', optionsKey: 'bantuan_lain'),
    FieldSpec(key: 'isejahtera_program', label: 'Program iSejahtera', kind: FieldKind.text),
    FieldSpec(key: 'bkb_program', label: 'Program BKB', kind: FieldKind.text),
    FieldSpec(key: 'jumlah_bantuan_tunai', label: 'Jumlah Bantuan Tunai (RM)', kind: FieldKind.number),
    FieldSpec(key: 'jumlah_wang_tunai', label: 'Jumlah Wang Tunai (RM)', kind: FieldKind.number),
  ]),
];

List<SectionSpec> sectionsFor(bool hasSumbangan) => _allSections
    .where((s) => !s.sumbanganGated || hasSumbangan)
    .toList(growable: false);

bool _isFilled(dynamic v) {
  if (v == null) return false;
  if (v is String) return v.trim().isNotEmpty;
  if (v is List) return v.isNotEmpty;
  return true;
}

bool isSectionComplete(SectionSpec section, Map<String, dynamic> fields) =>
    section.fields.where((f) => f.required).every((f) => _isFilled(fields[f.key]));

List<SectionSpec> incompleteRequiredSections(
        Map<String, dynamic> fields, bool hasSumbangan) =>
    sectionsFor(hasSumbangan)
        .where((s) => !s.isOptional && !isSectionComplete(s, fields))
        .toList(growable: false);

bool hasLainSelected(FieldSpec spec, dynamic value) {
  bool matches(String s) => s.toLowerCase().contains('lain');
  if (value is List) return value.any((e) => e is String && matches(e));
  if (value is String) return matches(value);
  return false;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd sisda_app && flutter test test/features/culaan_form_spec_test.dart`
Expected: PASS (9 tests).

- [ ] **Step 5: Commit**

```bash
git add sisda_app/lib/features/culaan/culaan_form_spec.dart sisda_app/test/features/culaan_form_spec_test.dart
git commit -m "Klien mudah alih 2b-ii: spec borang Culaan (7 bahagian, gating wajib) — pure Dart"
```

---

## Task 2: Typed `CulaanOptions` + options provider

**Files:**
- Create: `sisda_app/lib/features/culaan/culaan_options.dart`
- Create: `sisda_app/lib/features/culaan/culaan_options_provider.dart`
- Test: `sisda_app/test/features/culaan_options_test.dart`

**Interfaces:**
- Consumes: `mobileApiProvider` (`lib/providers.dart:19`) → `MobileApi.culaanOptions()` returns `Future<Map<String, dynamic>>` (the unwrapped `options` object, `lib/data/remote/mobile_api.dart:115`).
- Produces:
  - `class PekerjaanCategory { final String category; final List<String> items; }`
  - `class CulaanOptions { final List<String> pekerjaan; final Map<String, List<PekerjaanCategory>> jenisPekerjaan; final List<String> jenisSumbangan; final List<String> tujuanSumbangan; final List<String> bantuanLain; final List<String> pemilikRumah; ... List<String> optionsForKey(String optionsKey); List<PekerjaanCategory> jenisPekerjaanFor(String pekerjaan); factory CulaanOptions.fromJson(Map<String,dynamic>); }`
  - `final culaanOptionsProvider = FutureProvider.autoDispose<CulaanOptions>(...)`

- [ ] **Step 1: Write the failing tests**

```dart
// sisda_app/test/features/culaan_options_test.dart
import 'package:flutter_test/flutter_test.dart';
import 'package:sisda_app/features/culaan/culaan_options.dart';

void main() {
  final json = {
    'pekerjaan': ['Kerajaan', 'Swasta', 'Bekerja Sendiri', 'Tidak Bekerja'],
    'jenis_pekerjaan': {
      'Kerajaan': [
        {'category': 'Jenis Perkhidmatan', 'items': ['Perkhidmatan Awam Persekutuan', 'Perkhidmatan Awam Negeri']},
        {'category': 'Lain-lain', 'items': ['Lain-lain']},
      ],
      'Swasta': [
        {'category': 'Sektor', 'items': ['Kilang']},
      ],
    },
    'jenis_sumbangan': ['Barangan Keperluan Dapur', 'Lain-lain'],
    'tujuan_sumbangan': ['Pendidikan', 'Kesihatan'],
    'bantuan_lain': ['Jabatan Kebajikan Masyarakat (JKM)', 'Tiada', 'Lain-lain'],
    'pemilik_rumah': ['Sendiri', 'Sewa', 'Keluarga', 'Lain-lain'],
  };

  test('fromJson parses flat lists', () {
    final o = CulaanOptions.fromJson(json);
    expect(o.pekerjaan, ['Kerajaan', 'Swasta', 'Bekerja Sendiri', 'Tidak Bekerja']);
    expect(o.pemilikRumah.last, 'Lain-lain');
    expect(o.optionsForKey('jenis_sumbangan'), ['Barangan Keperluan Dapur', 'Lain-lain']);
  });

  test('jenisPekerjaanFor returns the category groups for the selected pekerjaan', () {
    final o = CulaanOptions.fromJson(json);
    final groups = o.jenisPekerjaanFor('Kerajaan');
    expect(groups.map((g) => g.category), ['Jenis Perkhidmatan', 'Lain-lain']);
    expect(groups.first.items, ['Perkhidmatan Awam Persekutuan', 'Perkhidmatan Awam Negeri']);
    expect(o.jenisPekerjaanFor('Tidak Bekerja'), isEmpty); // key absent → empty, not crash
  });

  test('empty tujuan_sumbangan is tolerated (Master Data may be empty)', () {
    final o = CulaanOptions.fromJson({...json, 'tujuan_sumbangan': <dynamic>[]});
    expect(o.tujuanSumbangan, isEmpty);
    expect(o.optionsForKey('tujuan_sumbangan'), isEmpty);
  });

  test('missing keys default to empty lists (defensive)', () {
    final o = CulaanOptions.fromJson({});
    expect(o.pekerjaan, isEmpty);
    expect(o.jenisPekerjaanFor('Kerajaan'), isEmpty);
  });
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd sisda_app && flutter test test/features/culaan_options_test.dart`
Expected: FAIL — `culaan_options.dart` not defined.

- [ ] **Step 3: Write the implementation**

```dart
// sisda_app/lib/features/culaan/culaan_options.dart
// PURE DART. Typed view over GET /api/mobile/culaan/options (contract §11).

class PekerjaanCategory {
  final String category;
  final List<String> items;
  const PekerjaanCategory(this.category, this.items);
}

class CulaanOptions {
  final List<String> pekerjaan;
  final Map<String, List<PekerjaanCategory>> jenisPekerjaan;
  final List<String> jenisSumbangan;
  final List<String> tujuanSumbangan;
  final List<String> bantuanLain;
  final List<String> pemilikRumah;

  const CulaanOptions({
    required this.pekerjaan,
    required this.jenisPekerjaan,
    required this.jenisSumbangan,
    required this.tujuanSumbangan,
    required this.bantuanLain,
    required this.pemilikRumah,
  });

  static List<String> _stringList(dynamic v) =>
      v is List ? v.map((e) => e.toString()).toList() : const [];

  factory CulaanOptions.fromJson(Map<String, dynamic> json) {
    final rawJp = json['jenis_pekerjaan'];
    final jp = <String, List<PekerjaanCategory>>{};
    if (rawJp is Map) {
      rawJp.forEach((pekerjaan, groups) {
        if (groups is List) {
          jp[pekerjaan.toString()] = groups
              .whereType<Map>()
              .map((g) => PekerjaanCategory(
                    (g['category'] ?? '').toString(),
                    _stringList(g['items']),
                  ))
              .toList();
        }
      });
    }
    return CulaanOptions(
      pekerjaan: _stringList(json['pekerjaan']),
      jenisPekerjaan: jp,
      jenisSumbangan: _stringList(json['jenis_sumbangan']),
      tujuanSumbangan: _stringList(json['tujuan_sumbangan']),
      bantuanLain: _stringList(json['bantuan_lain']),
      pemilikRumah: _stringList(json['pemilik_rumah']),
    );
  }

  List<String> optionsForKey(String optionsKey) {
    switch (optionsKey) {
      case 'pekerjaan':
        return pekerjaan;
      case 'jenis_sumbangan':
        return jenisSumbangan;
      case 'tujuan_sumbangan':
        return tujuanSumbangan;
      case 'bantuan_lain':
        return bantuanLain;
      case 'pemilik_rumah':
        return pemilikRumah;
      default:
        return const [];
    }
  }

  List<PekerjaanCategory> jenisPekerjaanFor(String pekerjaan) =>
      jenisPekerjaan[pekerjaan] ?? const [];
}
```

```dart
// sisda_app/lib/features/culaan/culaan_options_provider.dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers.dart';
import 'culaan_options.dart';

/// Fetches the Culaan form taxonomy. autoDispose so it re-fetches when the
/// form is re-opened (Master Data can change server-side between sessions).
final culaanOptionsProvider = FutureProvider.autoDispose<CulaanOptions>((ref) async {
  final api = ref.watch(mobileApiProvider);
  return CulaanOptions.fromJson(await api.culaanOptions());
});
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd sisda_app && flutter test test/features/culaan_options_test.dart`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add sisda_app/lib/features/culaan/culaan_options.dart sisda_app/lib/features/culaan/culaan_options_provider.dart sisda_app/test/features/culaan_options_test.dart
git commit -m "Klien mudah alih 2b-ii: model + provider taksonomi culaan/options (jenis_pekerjaan berkumpulan, tujuan boleh kosong)"
```

---

## Task 3: Field widgets (text/number/dropdown/multiselect/cascading/locked)

**Files:**
- Create: `sisda_app/lib/features/culaan/field_widgets.dart`
- Test: `sisda_app/test/features/culaan_field_widgets_test.dart`

**Interfaces:**
- Consumes: `FieldKind`, `FieldSpec`, `hasLainSelected` (Task 1); `CulaanOptions`, `PekerjaanCategory` (Task 2).
- Produces stateless widgets, each with an `onChanged` callback and a `value`:
  - `CulaanTextField({required FieldSpec spec, required String value, required bool locked, required ValueChanged<String> onChanged})` — renders `'****'` read-only when `locked`.
  - `CulaanNumberField({...})` — like text but `keyboardType: number`.
  - `CulaanDropdownField({required FieldSpec spec, required String value, required List<String> options, required ValueChanged<String> onChanged})`.
  - `CulaanMultiSelectField({required FieldSpec spec, required List<String> value, required List<String> options, required ValueChanged<List<String>> onChanged})` — `FilterChip` per option.
  - `CulaanCascadingField({required FieldSpec spec, required List<String> value, required List<PekerjaanCategory> groups, required ValueChanged<List<String>> onChanged})` — category headers + `FilterChip` items; shows a "Pilih Pekerjaan dahulu" hint when `groups` is empty.

- [ ] **Step 1: Write the failing tests**

```dart
// sisda_app/test/features/culaan_field_widgets_test.dart
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sisda_app/features/culaan/culaan_form_spec.dart';
import 'package:sisda_app/features/culaan/culaan_options.dart';
import 'package:sisda_app/features/culaan/field_widgets.dart';

Widget _host(Widget child) => MaterialApp(home: Scaffold(body: SingleChildScrollView(child: child)));

void main() {
  testWidgets('locked text field shows **** and is read-only', (tester) async {
    const spec = FieldSpec(key: 'no_ic', label: 'No. KP', kind: FieldKind.number, required: true, sensitive: true);
    var changed = false;
    await tester.pumpWidget(_host(CulaanTextField(
        spec: spec, value: '****', locked: true, onChanged: (_) => changed = true)));
    expect(find.text('****'), findsOneWidget);
    await tester.enterText(find.byType(TextField), '9999'); // ignored
    expect(changed, isFalse);
  });

  testWidgets('dropdown emits selected value', (tester) async {
    const spec = FieldSpec(key: 'pemilik_rumah', label: 'Pemilik Rumah', kind: FieldKind.dropdown, required: true, optionsKey: 'pemilik_rumah');
    String? picked;
    await tester.pumpWidget(_host(CulaanDropdownField(
        spec: spec, value: '', options: const ['Sendiri', 'Sewa'], onChanged: (v) => picked = v)));
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Sewa').last);
    await tester.pumpAndSettle();
    expect(picked, 'Sewa');
  });

  testWidgets('multiselect toggles a value into/out of the list', (tester) async {
    const spec = FieldSpec(key: 'jenis_sumbangan', label: 'Jenis Sumbangan', kind: FieldKind.multiSelect, required: true, optionsKey: 'jenis_sumbangan');
    List<String>? out;
    await tester.pumpWidget(_host(CulaanMultiSelectField(
        spec: spec, value: const ['Barangan'], options: const ['Barangan', 'Tunai'], onChanged: (v) => out = v)));
    await tester.tap(find.text('Tunai'));
    await tester.pump();
    expect(out, ['Barangan', 'Tunai']);
  });

  testWidgets('cascading shows category headers and a hint when no pekerjaan', (tester) async {
    const spec = FieldSpec(key: 'jenis_pekerjaan', label: 'Jenis Pekerjaan', kind: FieldKind.cascading, required: true, optionsKey: 'jenis_pekerjaan');
    await tester.pumpWidget(_host(CulaanCascadingField(
        spec: spec, value: const [], groups: const [], onChanged: (_) {})));
    expect(find.textContaining('Pilih Pekerjaan'), findsOneWidget);

    await tester.pumpWidget(_host(CulaanCascadingField(
        spec: spec, value: const [], onChanged: (_) {},
        groups: const [PekerjaanCategory('Jenis Perkhidmatan', ['Awam'])])));
    expect(find.text('Jenis Perkhidmatan'), findsOneWidget);
    expect(find.text('Awam'), findsOneWidget);
  });
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd sisda_app && flutter test test/features/culaan_field_widgets_test.dart`
Expected: FAIL — `field_widgets.dart` not defined.

- [ ] **Step 3: Write the implementation**

```dart
// sisda_app/lib/features/culaan/field_widgets.dart
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'culaan_form_spec.dart';
import 'culaan_options.dart';

/// Stateful so the controller is created ONCE — the section editor rebuilds on
/// every keystroke (setState in `_set`), and a controller rebuilt each frame
/// would jump the cursor to the end mid-typing. The controller is the source of
/// truth for text; `onChanged` flows edits up. Parent never pushes text back
/// down (the only source of `value` change is this field's own `onChanged`),
/// so no `didUpdateWidget` resync is needed.
class CulaanTextField extends StatefulWidget {
  final FieldSpec spec;
  final String value;
  final bool locked;
  final ValueChanged<String> onChanged;
  const CulaanTextField({
    super.key,
    required this.spec,
    required this.value,
    required this.onChanged,
    this.locked = false,
  });

  @override
  State<CulaanTextField> createState() => _CulaanTextFieldState();
}

class _CulaanTextFieldState extends State<CulaanTextField> {
  late final TextEditingController _controller =
      TextEditingController(text: widget.locked ? '****' : widget.value);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isNumber = widget.spec.kind == FieldKind.number;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: TextField(
        controller: _controller,
        readOnly: widget.locked,
        enabled: !widget.locked,
        maxLines: widget.spec.kind == FieldKind.multilineText ? 3 : 1,
        keyboardType: isNumber ? TextInputType.number : TextInputType.text,
        inputFormatters: isNumber ? [FilteringTextInputFormatter.digitsOnly] : null,
        decoration: InputDecoration(
          labelText: widget.spec.required ? '${widget.spec.label} *' : widget.spec.label,
          border: const OutlineInputBorder(),
          suffixIcon: widget.locked ? const Icon(Icons.lock_outline, size: 18) : null,
        ),
        onChanged: widget.locked ? null : widget.onChanged,
      ),
    );
  }
}

class CulaanDropdownField extends StatelessWidget {
  final FieldSpec spec;
  final String value;
  final List<String> options;
  final ValueChanged<String> onChanged;
  const CulaanDropdownField({
    super.key,
    required this.spec,
    required this.value,
    required this.options,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: DropdownButtonFormField<String>(
        initialValue: value.isEmpty ? null : value,
        isExpanded: true,
        decoration: InputDecoration(
          labelText: spec.required ? '${spec.label} *' : spec.label,
          border: const OutlineInputBorder(),
        ),
        items: [
          for (final o in options) DropdownMenuItem(value: o, child: Text(o)),
        ],
        onChanged: (v) => onChanged(v ?? ''),
      ),
    );
  }
}

class CulaanMultiSelectField extends StatelessWidget {
  final FieldSpec spec;
  final List<String> value;
  final List<String> options;
  final ValueChanged<List<String>> onChanged;
  const CulaanMultiSelectField({
    super.key,
    required this.spec,
    required this.value,
    required this.options,
    required this.onChanged,
  });

  void _toggle(String option) {
    final next = List<String>.from(value);
    next.contains(option) ? next.remove(option) : next.add(option);
    onChanged(next);
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(spec.required ? '${spec.label} *' : spec.label,
              style: Theme.of(context).textTheme.titleSmall),
          if (options.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: Text('Tiada pilihan tersedia.', style: TextStyle(color: Colors.grey)),
            ),
          Wrap(
            spacing: 8,
            children: [
              for (final o in options)
                FilterChip(
                  label: Text(o),
                  selected: value.contains(o),
                  onSelected: (_) => _toggle(o),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class CulaanCascadingField extends StatelessWidget {
  final FieldSpec spec;
  final List<String> value;
  final List<PekerjaanCategory> groups;
  final ValueChanged<List<String>> onChanged;
  const CulaanCascadingField({
    super.key,
    required this.spec,
    required this.value,
    required this.groups,
    required this.onChanged,
  });

  void _toggle(String item) {
    final next = List<String>.from(value);
    next.contains(item) ? next.remove(item) : next.add(item);
    onChanged(next);
  }

  @override
  Widget build(BuildContext context) {
    if (groups.isEmpty) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 8),
        child: Text('Pilih Pekerjaan dahulu untuk memaparkan jenis pekerjaan.',
            style: TextStyle(color: Colors.grey)),
      );
    }
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(spec.required ? '${spec.label} *' : spec.label,
              style: Theme.of(context).textTheme.titleSmall),
          for (final g in groups) ...[
            Padding(
              padding: const EdgeInsets.only(top: 8, bottom: 4),
              child: Text(g.category,
                  style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
            ),
            Wrap(
              spacing: 8,
              children: [
                for (final item in g.items)
                  FilterChip(
                    label: Text(item),
                    selected: value.contains(item),
                    onSelected: (_) => _toggle(item),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
```

> Note: `CulaanNumberField` is not a separate class — `CulaanTextField` renders numeric keyboards when `spec.kind == FieldKind.number`. The interface block's `CulaanNumberField` is satisfied by that behavior; do not create a second widget.

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd sisda_app && flutter test test/features/culaan_field_widgets_test.dart`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add sisda_app/lib/features/culaan/field_widgets.dart sisda_app/test/features/culaan_field_widgets_test.dart
git commit -m "Klien mudah alih 2b-ii: widget medan (teks/nombor/dropdown/multiselect/berperingkat + kunci ****)"
```

---

## Task 4: Generic section editor screen

**Files:**
- Create: `sisda_app/lib/features/culaan/section_editor_screen.dart`
- Test: `sisda_app/test/features/section_editor_screen_test.dart`

**Interfaces:**
- Consumes: `SectionSpec`, `FieldSpec`, `FieldKind`, `kSensitiveFields`, `hasLainSelected` (Task 1); `CulaanOptions` + `culaanOptionsProvider` (Task 2); the field widgets (Task 3).
- Produces: `class SectionEditorScreen extends ConsumerStatefulWidget` with `SectionEditorScreen({required SectionSpec section, required Map<String,dynamic> initialFields, required bool locked})`. On "Simpan" it `Navigator.pop(context, Map<String,dynamic>)` carrying **only this section's keys** (the hub merges). Locked sensitive fields are skipped from the returned map (they stay `'****'`/unchanged in the draft).
- Behavior:
  - Holds a working copy of this section's field values in `State`.
  - dropdown/multiselect/cascading fields read `culaanOptionsProvider`; while it loads show a spinner, on error show a BM message + Cuba Semula (options only gate the sumbangan sections; the 5 core sections have no option-backed fields so they render immediately).
  - When a `FieldSpec.lainKey != null` and `hasLainSelected(spec, value)`, render an extra `CulaanTextField` for the `_lain` key.
  - Cascading `jenis_pekerjaan` reads groups from `options.jenisPekerjaanFor(workingFields['pekerjaan'])`; changing `pekerjaan` clears `jenis_pekerjaan` selections that are no longer valid.

- [ ] **Step 1: Write the failing tests**

```dart
// sisda_app/test/features/section_editor_screen_test.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:sisda_app/data/remote/mobile_api.dart';
import 'package:sisda_app/features/culaan/culaan_form_spec.dart';
import 'package:sisda_app/features/culaan/section_editor_screen.dart';
import 'package:sisda_app/providers.dart';

class MockMobileApi extends Mock implements MobileApi {}

void main() {
  late MockMobileApi api;
  setUp(() {
    api = MockMobileApi();
    when(() => api.culaanOptions()).thenAnswer((_) async => {
          'pekerjaan': ['Kerajaan'],
          'jenis_pekerjaan': {
            'Kerajaan': [
              {'category': 'Perkhidmatan', 'items': ['Awam']}
            ]
          },
          'pemilik_rumah': ['Sendiri', 'Lain-lain'],
          'jenis_sumbangan': ['Barangan'],
          'tujuan_sumbangan': ['Pendidikan'],
          'bantuan_lain': ['JKM'],
        });
  });

  Future<Map<String, dynamic>?> editSection(
      WidgetTester tester, SectionSpec section, Map<String, dynamic> initial,
      {bool locked = false}) async {
    Map<String, dynamic>? result;
    await tester.pumpWidget(ProviderScope(
      overrides: [mobileApiProvider.overrideWithValue(api)],
      child: MaterialApp(
        home: Builder(
          builder: (context) => Scaffold(
            body: Center(
              child: ElevatedButton(
                onPressed: () async {
                  result = await Navigator.push<Map<String, dynamic>>(
                    context,
                    MaterialPageRoute(
                      builder: (_) => SectionEditorScreen(
                          section: section, initialFields: initial, locked: locked),
                    ),
                  );
                },
                child: const Text('open'),
              ),
            ),
          ),
        ),
      ),
    ));
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    return result;
  }

  testWidgets('core section returns only its edited keys', (tester) async {
    final peribadi = sectionsFor(false).firstWhere((s) => s.key == 'peribadi');
    await editSection(tester, peribadi, {'nama': ''});
    await tester.enterText(find.widgetWithText(TextField, 'Nama *'), 'Ahmad');
    await tester.tap(find.text('Simpan'));
    await tester.pumpAndSettle();
    // The pushed screen returns {nama: 'Ahmad', ...other peribadi keys}
    // (assert via a captured result — see helper wiring in real impl test)
  });

  testWidgets('locked sensitive field renders **** and read-only', (tester) async {
    final peribadi = sectionsFor(false).firstWhere((s) => s.key == 'peribadi');
    await editSection(tester, peribadi, {'no_ic': '****'}, locked: true);
    expect(find.text('****'), findsWidgets);
    expect(find.byIcon(Icons.lock_outline), findsWidgets);
  });

  testWidgets('cascading Jenis Pekerjaan appears after picking Pekerjaan', (tester) async {
    final isiRumah = sectionsFor(true).firstWhere((s) => s.key == 'isi_rumah');
    await editSection(tester, isiRumah, {});
    expect(find.textContaining('Pilih Pekerjaan'), findsOneWidget);
    await tester.tap(find.byType(DropdownButtonFormField<String>).first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Kerajaan').last);
    await tester.pumpAndSettle();
    expect(find.text('Perkhidmatan'), findsOneWidget); // category header
    expect(find.text('Awam'), findsOneWidget);
  });

  testWidgets('selecting a Lain-lain option reveals the *_lain text field', (tester) async {
    final isiRumah = sectionsFor(true).firstWhere((s) => s.key == 'isi_rumah');
    await editSection(tester, isiRumah, {});
    await tester.tap(find.byType(DropdownButtonFormField<String>).last); // pemilik_rumah
    await tester.pumpAndSettle();
    await tester.tap(find.text('Lain-lain').last);
    await tester.pumpAndSettle();
    expect(find.widgetWithText(TextField, 'Pemilik Rumah (Lain-lain)'), findsOneWidget);
  });
}
```

> The first test's exact result-capture wiring: in the real test file, hoist `result` to the closure and assert `expect(result!['nama'], 'Ahmad')` after Simpan — shown fully in the implementation-phase test. The gist above is the interaction path.

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd sisda_app && flutter test test/features/section_editor_screen_test.dart`
Expected: FAIL — `section_editor_screen.dart` not defined.

- [ ] **Step 3: Write the implementation**

```dart
// sisda_app/lib/features/culaan/section_editor_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'culaan_form_spec.dart';
import 'culaan_options.dart';
import 'culaan_options_provider.dart';
import 'field_widgets.dart';

class SectionEditorScreen extends ConsumerStatefulWidget {
  final SectionSpec section;
  final Map<String, dynamic> initialFields;
  final bool locked; // masked-create: sensitive fields read-only
  const SectionEditorScreen({
    super.key,
    required this.section,
    required this.initialFields,
    this.locked = false,
  });

  @override
  ConsumerState<SectionEditorScreen> createState() => _SectionEditorScreenState();
}

class _SectionEditorScreenState extends ConsumerState<SectionEditorScreen> {
  late final Map<String, dynamic> _work;

  bool get _needsOptions =>
      widget.section.fields.any((f) => f.optionsKey != null);

  @override
  void initState() {
    super.initState();
    // Copy only this section's keys (plus any *_lain companions) into the work map.
    _work = {};
    for (final f in widget.section.fields) {
      _work[f.key] = widget.initialFields[f.key] ??
          (f.isArray ? <String>[] : '');
      if (f.lainKey != null) {
        _work[f.lainKey!] = widget.initialFields[f.lainKey!] ?? '';
      }
    }
  }

  bool _isLocked(FieldSpec f) => widget.locked && kSensitiveFields.contains(f.key);

  void _set(String key, dynamic value) => setState(() => _work[key] = value);

  void _save() {
    // Drop locked sensitive keys so the draft keeps its '****'/existing value.
    final out = <String, dynamic>{};
    _work.forEach((k, v) {
      final spec = widget.section.fields
          .cast<FieldSpec?>()
          .firstWhere((f) => f?.key == k, orElse: () => null);
      if (spec != null && _isLocked(spec)) return;
      out[k] = v;
    });
    Navigator.pop(context, out);
  }

  @override
  Widget build(BuildContext context) {
    final body = _needsOptions
        ? ref.watch(culaanOptionsProvider).when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (_, __) => _OptionsError(onRetry: () => ref.invalidate(culaanOptionsProvider)),
              data: (options) => _form(options),
            )
        : _form(null);

    return Scaffold(
      appBar: AppBar(title: Text(widget.section.title)),
      body: body,
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: FilledButton(onPressed: _save, child: const Text('Simpan')),
        ),
      ),
    );
  }

  Widget _form(CulaanOptions? options) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        for (final f in widget.section.fields) ..._fieldWidgets(f, options),
      ],
    );
  }

  List<Widget> _fieldWidgets(FieldSpec f, CulaanOptions? options) {
    final widgets = <Widget>[];
    switch (f.kind) {
      case FieldKind.text:
      case FieldKind.multilineText:
      case FieldKind.number:
        widgets.add(CulaanTextField(
          key: ValueKey(f.key), // stable identity so the controller stays bound
          spec: f,
          value: (_work[f.key] ?? '').toString(),
          locked: _isLocked(f),
          onChanged: (v) => _set(f.key, v),
        ));
        break;
      case FieldKind.dropdown:
        widgets.add(CulaanDropdownField(
          spec: f,
          value: (_work[f.key] ?? '').toString(),
          options: options?.optionsForKey(f.optionsKey!) ?? const [],
          onChanged: (v) {
            _set(f.key, v);
            // Changing pekerjaan invalidates jenis_pekerjaan selections.
            if (f.key == 'pekerjaan') _set('jenis_pekerjaan', <String>[]);
          },
        ));
        break;
      case FieldKind.multiSelect:
        widgets.add(CulaanMultiSelectField(
          spec: f,
          value: List<String>.from(_work[f.key] as List? ?? const []),
          options: options?.optionsForKey(f.optionsKey!) ?? const [],
          onChanged: (v) => _set(f.key, v),
        ));
        break;
      case FieldKind.cascading:
        final pekerjaan = (_work['pekerjaan'] ?? '').toString();
        widgets.add(CulaanCascadingField(
          spec: f,
          value: List<String>.from(_work[f.key] as List? ?? const []),
          groups: options?.jenisPekerjaanFor(pekerjaan) ?? const [],
          onChanged: (v) => _set(f.key, v),
        ));
        break;
    }
    // Reveal the *_lain companion when a "Lain-lain" option is selected.
    if (f.lainKey != null && hasLainSelected(f, _work[f.key])) {
      widgets.add(CulaanTextField(
        key: ValueKey(f.lainKey!),
        spec: FieldSpec(
            key: f.lainKey!, label: '${f.label} (Lain-lain)', kind: FieldKind.text),
        value: (_work[f.lainKey!] ?? '').toString(),
        onChanged: (v) => _set(f.lainKey!, v),
      ));
    }
    return widgets;
  }
}

class _OptionsError extends StatelessWidget {
  final VoidCallback onRetry;
  const _OptionsError({required this.onRetry});
  @override
  Widget build(BuildContext context) => Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text('Gagal memuat pilihan borang. Sila cuba semula bila ada talian.',
                  textAlign: TextAlign.center),
            ),
            OutlinedButton(onPressed: onRetry, child: const Text('Cuba Semula')),
          ],
        ),
      );
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd sisda_app && flutter test test/features/section_editor_screen_test.dart`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add sisda_app/lib/features/culaan/section_editor_screen.dart sisda_app/test/features/section_editor_screen_test.dart
git commit -m "Klien mudah alih 2b-ii: skrin editor bahagian generik (dipacu-spec, *_lain, kunci sensitif)"
```

---

## Task 5: Draft load-or-create + masked-create prefill

**Files:**
- Create: `sisda_app/lib/features/culaan/culaan_draft_loader.dart`
- Test: `sisda_app/test/features/culaan_draft_loader_test.dart`

**Interfaces:**
- Consumes: `DraftStore` (`AppDatabase` implements it; `upsertDraft`, `getDraft` — `lib/data/local/database.dart:42-49`); `CulaanDraft.newDraft` / `copyWith` (`lib/models/culaan_draft.dart:30,66`); `Voter` (`lib/models/voter.dart` — `int id`, `String field(String)`); `kSensitiveFields` (Task 1); `uuid`.
- Produces:
  - `final idGeneratorProvider = Provider<String Function()>((ref) => () => const Uuid().v4());`
  - `Future<CulaanDraft> loadOrCreateDraft({required DraftStore store, required String Function() idGenerator, required DateTime now, String? draftKey, Voter? prefillVoter})`
- Behavior:
  - `draftKey != null` → return `store.getDraft(draftKey)` (must exist; if `null`, throw `StateError` — a Betulkan tap on a live row).
  - else → new draft with `idGenerator()`; if `prefillVoter != null`, seed the mappable fields from the voter, set `lockedSourceId = voter.id`, mark `hasSumbangan` false. Sensitive fields carry whatever the voter already holds (`'****'` for a masked role) — **never** real values the agent couldn't see. Persist (`upsertDraft`, `status: draft`) and return.

**Voter→draft field keys** to seed (the intersection of Voter's json keys and the mobile field set): `nama, no_ic, umur, no_tel, bangsa, alamat, poskod, negeri, bandar, parlimen, kadun, mpkk, daerah_mengundi, lokaliti, keahlian_parti, kecenderungan_politik, status_pengundi`. Empty (`''`) values are skipped so the draft only carries what the voter record actually had.

- [ ] **Step 1: Write the failing tests**

```dart
// sisda_app/test/features/culaan_draft_loader_test.dart
import 'package:flutter_test/flutter_test.dart';
import 'package:sisda_app/data/local/database.dart';
import 'package:sisda_app/features/culaan/culaan_draft_loader.dart';
import 'package:sisda_app/models/culaan_draft.dart';
import 'package:sisda_app/models/sync_status.dart';
import 'package:sisda_app/models/voter.dart';

void main() {
  late AppDatabase db;
  final now = DateTime(2026, 7, 18, 12);
  setUp(() => db = AppDatabase.forTesting());
  tearDown(() => db.close());

  test('blank: creates + persists a draft with a fresh key, status draft', () async {
    final draft = await loadOrCreateDraft(
        store: db, idGenerator: () => 'KEY-1', now: now);
    expect(draft.idempotencyKey, 'KEY-1');
    expect(draft.status, SyncStatus.draft);
    expect(draft.lockedSourceId, isNull);
    expect(await db.getDraft('KEY-1'), isNotNull); // persisted
  });

  test('prefill: seeds mappable fields, sets lockedSourceId, keeps mask', () async {
    final voter = Voter.fromJson({
      'id': 77, 'nama': 'Ahmad bin Ali', 'no_ic': '****', 'alamat': '****',
      'parlimen': 'P.044', 'kadun': 'N.11', 'poskod': '',
    });
    final draft = await loadOrCreateDraft(
        store: db, idGenerator: () => 'KEY-2', now: now, prefillVoter: voter);
    expect(draft.lockedSourceId, 77);
    expect(draft.fields['nama'], 'Ahmad bin Ali');
    expect(draft.fields['no_ic'], '****');    // mask preserved, not a real IC
    expect(draft.fields['parlimen'], 'P.044');
    expect(draft.fields.containsKey('poskod'), isFalse); // empty skipped
  });

  test('reopen: returns the existing draft by key', () async {
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'KEY-3', now: now)
        .copyWith(status: SyncStatus.failed, failureReason: 'Di luar Parlimen anda.',
            fields: {'nama': 'Siti'}));
    final draft = await loadOrCreateDraft(store: db, idGenerator: () => 'NOPE', now: now, draftKey: 'KEY-3');
    expect(draft.idempotencyKey, 'KEY-3');
    expect(draft.fields['nama'], 'Siti');
    expect(draft.status, SyncStatus.failed);
  });

  test('reopen a missing key throws', () async {
    expect(
      () => loadOrCreateDraft(store: db, idGenerator: () => 'x', now: now, draftKey: 'GHOST'),
      throwsStateError,
    );
  });
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd sisda_app && flutter test test/features/culaan_draft_loader_test.dart`
Expected: FAIL — `culaan_draft_loader.dart` not defined.

- [ ] **Step 3: Write the implementation**

```dart
// sisda_app/lib/features/culaan/culaan_draft_loader.dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';
import '../../data/local/database.dart';
import '../../models/culaan_draft.dart';
import '../../models/voter.dart';

/// Injectable so widget tests get deterministic idempotency keys.
final idGeneratorProvider =
    Provider<String Function()>((ref) => () => const Uuid().v4());

const _prefillKeys = [
  'nama', 'no_ic', 'umur', 'no_tel', 'bangsa', 'alamat', 'poskod', 'negeri',
  'bandar', 'parlimen', 'kadun', 'mpkk', 'daerah_mengundi', 'lokaliti',
  'keahlian_parti', 'kecenderungan_politik', 'status_pengundi',
];

Future<CulaanDraft> loadOrCreateDraft({
  required DraftStore store,
  required String Function() idGenerator,
  required DateTime now,
  String? draftKey,
  Voter? prefillVoter,
}) async {
  if (draftKey != null) {
    final existing = await store.getDraft(draftKey);
    if (existing == null) {
      throw StateError('Draf $draftKey tidak lagi wujud.');
    }
    return existing;
  }

  var draft = CulaanDraft.newDraft(idempotencyKey: idGenerator(), now: now);

  if (prefillVoter != null) {
    final seeded = <String, dynamic>{};
    for (final k in _prefillKeys) {
      final v = prefillVoter.field(k); // '' when absent; '****' when masked
      if (v.isNotEmpty) seeded[k] = v;
    }
    draft = draft.copyWith(
      fields: seeded,
      lockedSourceId: prefillVoter.id,
      updatedAt: now,
    );
  }

  await store.upsertDraft(draft);
  return draft;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd sisda_app && flutter test test/features/culaan_draft_loader_test.dart`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add sisda_app/lib/features/culaan/culaan_draft_loader.dart sisda_app/test/features/culaan_draft_loader_test.dart
git commit -m "Klien mudah alih 2b-ii: muat/cipta draf + prasi masked-create (kekal topeng, locked_source_id)"
```

---

## Task 6: The checklist hub (`CulaanFormScreen`) — sections, toggle, progress

**Files:**
- Modify: `sisda_app/lib/features/culaan/culaan_form_screen.dart` (replace the stub body; keep the constructor signature `({String? draftKey, Voter? prefillVoter})`)
- Test: `sisda_app/test/features/culaan_form_screen_test.dart`

**Interfaces:**
- Consumes: everything above — `loadOrCreateDraft`, `idGeneratorProvider` (Task 5); `sectionsFor`, `isSectionComplete`, `incompleteRequiredSections`, `SectionSpec`, `kSensitiveFields` (Task 1); `SectionEditorScreen` (Task 4); `appDatabaseProvider` (`lib/providers.dart:25`), `syncEngineProvider` (`lib/providers.dart:31`); `CulaanDraft.copyWith/toApiPayload` and `SyncStatus`.
- Produces: the live hub. State: `CulaanDraft? _draft` (loaded on init). Renders:
  - Title: `_draft.fields['nama']` or `'Culaan Baru'`.
  - Progress: `"$done daripada $total bahagian wajib siap"` where `total`/`done` count **required-bearing** sections.
  - Each section row: title + trailing `✓` (complete), `Belum diisi ›` (required, incomplete), or `pilihan ›` (optional section). Tapping pushes `SectionEditorScreen`; on return, merge and persist.
  - The `Ada Sumbangan` `SwitchListTile` between `status` and the gated sections.
  - `Hantar` button (Task 7 wires its behavior; in Task 6 it is present but calls a `_submit()` stub that only validates + shows a snackbar of missing sections — replaced/extended in Task 7).

- [ ] **Step 1: Write the failing tests**

```dart
// sisda_app/test/features/culaan_form_screen_test.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:sisda_app/data/local/database.dart';
import 'package:sisda_app/data/remote/mobile_api.dart';
import 'package:sisda_app/features/culaan/culaan_draft_loader.dart';
import 'package:sisda_app/features/culaan/culaan_form_screen.dart';
import 'package:sisda_app/models/culaan_draft.dart';
import 'package:sisda_app/models/sync_status.dart';
import 'package:sisda_app/models/voter.dart';
import 'package:sisda_app/providers.dart';
import 'package:sisda_app/sync/sync_engine.dart';

class MockMobileApi extends Mock implements MobileApi {}
class MockSyncEngine extends Mock implements SyncEngine {}

Future<void> _drain(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await tester.pump(const Duration(milliseconds: 50));
}

void main() {
  late AppDatabase db;
  late MockMobileApi api;
  late MockSyncEngine sync;
  final now = DateTime(2026, 7, 18);

  setUp(() {
    db = AppDatabase.forTesting();
    api = MockMobileApi();
    sync = MockSyncEngine();
    when(() => api.culaanOptions()).thenAnswer((_) async => {'pekerjaan': [], 'jenis_pekerjaan': {}, 'jenis_sumbangan': [], 'tujuan_sumbangan': [], 'bantuan_lain': [], 'pemilik_rumah': []});
    when(() => sync.syncNow(now: any(named: 'now'))).thenAnswer((_) async => SyncOutcome());
  });
  tearDown(() => db.close());

  Future<void> pump(WidgetTester tester, {String? draftKey, Voter? prefill}) async {
    await tester.pumpWidget(ProviderScope(
      overrides: [
        appDatabaseProvider.overrideWithValue(db),
        mobileApiProvider.overrideWithValue(api),
        syncEngineProvider.overrideWithValue(sync),
        idGeneratorProvider.overrideWithValue(() => 'TEST-KEY'),
      ],
      child: MaterialApp(home: CulaanFormScreen(draftKey: draftKey, prefillVoter: prefill)),
    ));
    await tester.pump();               // build
    await tester.pump();               // let loadOrCreateDraft future resolve
  }

  testWidgets('blank form shows 5 sections and "0 daripada 3 bahagian wajib siap"', (tester) async {
    await pump(tester);
    expect(find.text('Maklumat Peribadi'), findsOneWidget);
    expect(find.text('Isi Rumah'), findsNothing); // gated off
    expect(find.textContaining('0 daripada 3'), findsOneWidget);
    await _drain(tester);
  });

  testWidgets('Ada Sumbangan toggle adds Isi Rumah + Bantuan', (tester) async {
    await pump(tester);
    await tester.tap(find.byType(SwitchListTile));
    await tester.pump();
    expect(find.text('Isi Rumah'), findsOneWidget);
    expect(find.text('Bantuan'), findsOneWidget);
    expect(find.textContaining('daripada 5'), findsOneWidget); // now 5 required sections
    await _drain(tester);
  });

  testWidgets('optional sections render a "pilihan" tag, not Belum diisi', (tester) async {
    await pump(tester);
    final politikRow = find.ancestor(of: find.text('Maklumat Politik'), matching: find.byType(ListTile));
    expect(find.descendant(of: politikRow, matching: find.text('pilihan')), findsOneWidget);
    await _drain(tester);
  });

  testWidgets('reopen a seeded draft shows its saved section completeness', (tester) async {
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'D1', now: now).copyWith(
      status: SyncStatus.failed,
      fields: {
        'nama': 'Ali', 'no_ic': '800101015555', 'umur': '44',
        'no_tel': '0121234567', 'bangsa': 'Melayu',
      },
    ));
    await pump(tester, draftKey: 'D1');
    // Peribadi complete → ✓; Alamat incomplete → Belum diisi
    final peribadiRow = find.ancestor(of: find.text('Maklumat Peribadi'), matching: find.byType(ListTile));
    expect(find.descendant(of: peribadiRow, matching: find.byIcon(Icons.check_circle)), findsOneWidget);
    expect(find.textContaining('1 daripada 3'), findsOneWidget);
    await _drain(tester);
  });

  testWidgets('masked prefill locks name shown but marks locked_source_id', (tester) async {
    final voter = Voter.fromJson({'id': 9, 'nama': 'Ahmad', 'no_ic': '****'});
    await pump(tester, prefill: voter);
    expect(find.text('Ahmad'), findsOneWidget); // title from prefilled nama
    // draft persisted with lockedSourceId
    final saved = await db.getDraft('TEST-KEY');
    expect(saved!.lockedSourceId, 9);
    await _drain(tester);
  });
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd sisda_app && flutter test test/features/culaan_form_screen_test.dart`
Expected: FAIL — the stub renders "akan tersedia", not the hub.

- [ ] **Step 3: Write the implementation**

```dart
// sisda_app/lib/features/culaan/culaan_form_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../models/culaan_draft.dart';
import '../../models/sync_status.dart';
import '../../models/voter.dart';
import '../../providers.dart';
import 'culaan_draft_loader.dart';
import 'culaan_form_spec.dart';
import 'section_editor_screen.dart';

class CulaanFormScreen extends ConsumerStatefulWidget {
  final String? draftKey;
  final Voter? prefillVoter;
  const CulaanFormScreen({super.key, this.draftKey, this.prefillVoter});

  @override
  ConsumerState<CulaanFormScreen> createState() => _CulaanFormScreenState();
}

class _CulaanFormScreenState extends ConsumerState<CulaanFormScreen> {
  CulaanDraft? _draft;
  Object? _loadError;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final draft = await loadOrCreateDraft(
        store: ref.read(appDatabaseProvider),
        idGenerator: ref.read(idGeneratorProvider),
        now: DateTime.now(),
        draftKey: widget.draftKey,
        prefillVoter: widget.prefillVoter,
      );
      if (mounted) setState(() => _draft = draft);
    } catch (e) {
      if (mounted) setState(() => _loadError = e);
    }
  }

  bool get _locked => (_draft?.lockedSourceId) != null;
  Map<String, dynamic> get _fields => _draft?.fields ?? const {};

  Future<void> _persist(CulaanDraft next) async {
    await ref.read(appDatabaseProvider).upsertDraft(next);
    if (mounted) setState(() => _draft = next);
  }

  Future<void> _openSection(SectionSpec section) async {
    final result = await Navigator.push<Map<String, dynamic>>(
      context,
      MaterialPageRoute(
        builder: (_) => SectionEditorScreen(
          section: section,
          initialFields: _fields,
          locked: _locked,
        ),
      ),
    );
    if (result == null || _draft == null) return;
    final merged = {..._fields, ...result};
    await _persist(_draft!.copyWith(fields: merged, updatedAt: DateTime.now()));
  }

  Future<void> _toggleSumbangan(bool value) async {
    if (_draft == null) return;
    var fields = _fields;
    if (!value) {
      // Strip the two gated sections' keys so the payload stays clean.
      fields = {..._fields};
      for (final s in [_section('isi_rumah'), _section('bantuan')]) {
        for (final f in s.fields) {
          fields.remove(f.key);
          if (f.lainKey != null) fields.remove(f.lainKey);
        }
      }
    }
    await _persist(_draft!.copyWith(
        hasSumbangan: value, fields: fields, updatedAt: DateTime.now()));
  }

  SectionSpec _section(String key) =>
      sectionsFor(true).firstWhere((s) => s.key == key);

  // --- Task 7 replaces this body with the real enqueue+sync path. ---
  Future<void> _submit() async {
    final missing = incompleteRequiredSections(_fields, _draft!.hasSumbangan);
    if (missing.isNotEmpty) {
      final names = missing.map((s) => s.title).join(', ');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Sila lengkapkan: $names')),
      );
      return;
    }
    // Real submit wired in Task 7.
  }

  @override
  Widget build(BuildContext context) {
    if (_loadError != null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Borang Culaan')),
        body: const Center(
          child: Padding(
            padding: EdgeInsets.all(24),
            child: Text('Draf ini tidak lagi wujud. Sila cari semula pengundi.',
                textAlign: TextAlign.center),
          ),
        ),
      );
    }
    if (_draft == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final draft = _draft!;
    final sections = sectionsFor(draft.hasSumbangan);
    final requiredSections = sections.where((s) => !s.isOptional).toList();
    final done = requiredSections.where((s) => isSectionComplete(s, _fields)).length;
    final nama = (_fields['nama'] ?? '').toString();

    return Scaffold(
      appBar: AppBar(title: Text(nama.isEmpty ? 'Culaan Baru' : nama)),
      body: ListView(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Text('$done daripada ${requiredSections.length} bahagian wajib siap',
                style: Theme.of(context).textTheme.titleMedium),
          ),
          for (final s in sectionsFor(false)) _sectionTile(s),
          SwitchListTile(
            title: const Text('Ada Sumbangan'),
            value: draft.hasSumbangan,
            onChanged: _toggleSumbangan,
          ),
          if (draft.hasSumbangan) ...[
            _sectionTile(_section('isi_rumah')),
            _sectionTile(_section('bantuan')),
          ],
          const SizedBox(height: 12),
          Padding(
            padding: const EdgeInsets.all(16),
            child: FilledButton(onPressed: _submit, child: const Text('Hantar')),
          ),
        ],
      ),
    );
  }

  Widget _sectionTile(SectionSpec s) {
    final Widget trailing;
    if (s.isOptional) {
      trailing = const Text('pilihan');
    } else if (isSectionComplete(s, _fields)) {
      trailing = const Icon(Icons.check_circle, color: Colors.green);
    } else {
      trailing = const Text('Belum diisi ›');
    }
    return ListTile(
      title: Text(s.title),
      trailing: trailing,
      onTap: () => _openSection(s),
    );
  }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd sisda_app && flutter test test/features/culaan_form_screen_test.dart`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add sisda_app/lib/features/culaan/culaan_form_screen.dart sisda_app/test/features/culaan_form_screen_test.dart
git commit -m "Klien mudah alih 2b-ii: hub checklist (kemajuan, togol sumbangan, prasi, buka bahagian)"
```

---

## Task 7: `Hantar` — enqueue + sync trigger + Betulkan re-submit

**Files:**
- Modify: `sisda_app/lib/features/culaan/culaan_form_screen.dart` (replace the `_submit()` stub body with the real enqueue+sync path)
- Test: `sisda_app/test/features/culaan_form_screen_test.dart` (add submit tests to the existing file)

**Interfaces:**
- Consumes: `appDatabaseProvider`, `syncEngineProvider` (already imported); `CulaanDraft.copyWith`, `SyncStatus.queued`; `incompleteRequiredSections` (Task 1).
- Behavior of `_submit()`:
  1. Compute `incompleteRequiredSections(_fields, hasSumbangan)`. If non-empty → BM snackbar naming the sections, return (no enqueue).
  2. Else → build `queued = _draft.copyWith(status: SyncStatus.queued, failureReason: null, updatedAt: now)`; `upsertDraft(queued)` (this is the `draft`/`failed` → `queued` transition the SyncEngine relies on — SyncEngine never sets `queued` itself, per `lib/sync/sync_engine.dart`).
  3. `await ref.read(syncEngineProvider).syncNow(now: DateTime.now())` (drains the queue if online; if offline it stays `queued` and the Utama amber strip shows it).
  4. `Navigator.pop(context)` and show a BM confirmation snackbar on the previous screen (`'Culaan disimpan. Ia akan dihantar bila ada talian.'`) — pop first, then the caller shows it; simplest: show the snackbar via the root `ScaffoldMessenger` before pop.
- Betulkan re-submit is covered by the same path: a re-opened `failed` draft (loaded by `draftKey`) has its `failureReason` cleared and `status` reset to `queued` on submit, so it leaves the Perlu Perhatian inbox and re-enters the queue.

- [ ] **Step 1: Write the failing tests** (append to `culaan_form_screen_test.dart`)

```dart
  testWidgets('Hantar with missing required fields shows BM message, does NOT enqueue', (tester) async {
    await pump(tester);
    await tester.tap(find.text('Hantar'));
    await tester.pump();
    expect(find.textContaining('Sila lengkapkan'), findsOneWidget);
    verifyNever(() => sync.syncNow(now: any(named: 'now')));
    final saved = await db.getDraft('TEST-KEY');
    expect(saved!.status, SyncStatus.draft); // still a draft, not queued
    await _drain(tester);
  });

  testWidgets('Hantar with a complete form enqueues (queued) and triggers syncNow', (tester) async {
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'C1', now: now).copyWith(
      status: SyncStatus.failed,
      failureReason: 'Di luar Parlimen anda.',
      fields: {
        'nama': 'Ali', 'no_ic': '800101015555', 'umur': '44', 'no_tel': '0121234567',
        'bangsa': 'Melayu', 'alamat': 'Jln 1', 'poskod': '13200', 'negeri': 'P. Pinang',
        'bandar': 'Kepala Batas', 'parlimen': 'P.044', 'kadun': 'N.11',
      },
    ));
    await pump(tester, draftKey: 'C1');
    await tester.tap(find.text('Hantar'));
    await tester.pump();
    final saved = await db.getDraft('C1');
    expect(saved!.status, SyncStatus.queued);
    expect(saved.failureReason, isNull); // cleared on re-submit (leaves Perlu Perhatian)
    verify(() => sync.syncNow(now: any(named: 'now'))).called(1);
    await _drain(tester);
  });
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd sisda_app && flutter test test/features/culaan_form_screen_test.dart`
Expected: FAIL — the second test: `status` stays `draft` (stub doesn't enqueue); `syncNow` never called.

- [ ] **Step 3: Replace the `_submit()` body**

```dart
  Future<void> _submit() async {
    final draft = _draft;
    if (draft == null) return;
    final missing = incompleteRequiredSections(_fields, draft.hasSumbangan);
    if (missing.isNotEmpty) {
      final names = missing.map((s) => s.title).join(', ');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Sila lengkapkan: $names')),
      );
      return;
    }
    final now = DateTime.now();
    final queued = draft.copyWith(
      status: SyncStatus.queued,
      failureReason: null, // clear any prior failure → leaves Perlu Perhatian
      updatedAt: now,
    );
    await ref.read(appDatabaseProvider).upsertDraft(queued);
    await ref.read(syncEngineProvider).syncNow(now: DateTime.now());
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Culaan disimpan. Ia akan dihantar bila ada talian.')),
    );
    Navigator.of(context).pop();
  }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd sisda_app && flutter test test/features/culaan_form_screen_test.dart`
Expected: PASS (7 tests total in the file).

- [ ] **Step 5: Run the whole suite + analyzer**

Run: `cd sisda_app && flutter analyze && flutter test`
Expected: analyzer clean; all tests green (2a + 2b-i + the six new 2b-ii test files).

- [ ] **Step 6: Commit**

```bash
git add sisda_app/lib/features/culaan/culaan_form_screen.dart sisda_app/test/features/culaan_form_screen_test.dart
git commit -m "Klien mudah alih 2b-ii: Hantar -> baris gilir + cetus sync; Betulkan kosongkan sebab gagal"
```

---

## Definition of Done

- [ ] `flutter analyze` clean; `flutter test` (from `sisda_app/`) all green — the 2a suite, the 2b-i suite, and the six new 2b-ii test files.
- [ ] `lib/models/` and `lib/sync/` still have **zero** Flutter imports (`grep -rL "package:flutter" lib/models lib/sync` unchanged); `culaan_form_spec.dart` and `culaan_options.dart` import no Flutter.
- [ ] The checklist hub renders from a blank form (5 sections), a masked-prefill (locked sensitive fields, `locked_source_id` persisted), and a re-opened `failed` draft (Betulkan).
- [ ] `Ada Sumbangan` adds/removes exactly Isi Rumah + Bantuan; toggling off strips their keys from the draft payload.
- [ ] Required-field gating: `Hantar` blocks with a BM message until all required-bearing sections are complete; the two optional sections never block.
- [ ] `Hantar` moves the draft `draft`/`failed` → `queued`, clears `failureReason`, and calls `syncEngineProvider.syncNow` — proven against the in-memory DB. The Utama amber strip and Perlu Perhatian inbox (2b-i) now light up in real use.
- [ ] Every dropdown/checkbox list originates from `GET /culaan/options`; empty `tujuan_sumbangan` renders gracefully.

## Scope boundary — deferred beyond 2b-ii

- **`perkeso_bantuan` / `zpp_jenis_bantuan`** — server-optional; the mobile `options` endpoint provides no taxonomy for them, so they are out of V1. If field feedback needs them, add a taxonomy source to `culaan/options` first, then a `multiSelect` `FieldSpec` in the `bantuan` section.
- **IC-photo upload (`kad_pengenalan`), `is_deceased`, cash-tracking fields** (`status_sumbangan`, `tarikh_sumbangan`, `jumlah_dipohon/dilulus/dibayar`, `jkm_program`) — not in the mobile store contract (§13); web-only.
- **Real-device/emulator E2E against the deployed API** — needs the `feature/mobile-app-user` API branch merged and deployed. That is the final milestone after this suite is green (design spec §Ujian "Emulator").
- **Per-keystroke autosave** — 2b-ii persists on each section Simpan (draft exists from the first save). If field agents report losing in-progress section edits on a crash, add debounced per-field `upsertDraft` inside `SectionEditorScreen`.

## Branch

Continue on `feature/mobile-client-2a` (where 2a + 2b-i live) or a `feature/mobile-client-2b` branch. Touches only `sisda_app/` — no PHP, no server; all tests use fakes / in-memory Drift.
