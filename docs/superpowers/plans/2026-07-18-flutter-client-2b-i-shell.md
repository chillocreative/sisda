# Flutter Client 2b-i — Navigation Shell & Read/Queue Screens Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the native app frame — a 4-tab bottom nav, the search-first home, voter search/scan/detail (read-only, masked), the Perlu Perhatian inbox, Rekod Saya, and the sync triggers 2a deferred — so the field worker has a working lookup-and-queue app, with the Culaan write form stubbed for Plan 2b-ii.

**Architecture:** Riverpod screens under `lib/features/`, each consuming the 2a foundation providers (`mobileApiProvider`, `appDatabaseProvider` as `DraftStore`, `syncEngineProvider`, `authControllerProvider`). Read screens hold voter data only in memory (never persisted — the privacy line). The offline queue strip and the Perlu Perhatian inbox are live views over the Drift draft store. Every screen is widget-tested with a fake `MobileApi` (mocktail) and an in-memory `AppDatabase.forTesting()` via `ProviderScope` overrides.

**Tech Stack:** Flutter 3.44.5 / Dart `^3.11.1`, `flutter_riverpod`, the 2a layers (Drift, `MobileApi`), `camera`/`image_picker`/`google_mlkit_text_recognition` (already present) for the IC scan, `webview_flutter` (present) for the WebView tail.

**This is Plan 2b-i of 2b** (2b-ii = the checklist-hub Culaan write form + 7 section editors + draft lifecycle + masked-create prefill). 2b-i produces a working, `flutter test`-green read/queue app. Draft *creation* is 2b-ii, so in 2b-i the queue strip and inbox are populated by tests (seeded drafts); they light up in real use once 2b-ii ships.

**Primary sources:**
- Design/UX spec: `docs/superpowers/specs/2026-07-17-aplikasi-mudah-alih-user-design.md` (§UI/UX — search-first home, Perlu Perhatian).
- API contract (authoritative): `docs/superpowers/specs/2026-07-17-kontrak-api-mudah-alih.md` (voters/search, voters/{ic}, culaan/mine; the role-based search behaviour and throttles).
- 2a foundation: `sisda_app/lib/providers.dart`, `lib/data/remote/mobile_api.dart`, `lib/data/local/database.dart`, `lib/models/`, `lib/screens/webview_screen.dart`, `lib/services/ocr_service.dart`.

## Global Constraints

- **All user-facing strings are Bahasa Melayu.** No i18n layer; hardcode inline, match existing copy. Server error text surfaces via `ApiException.firstError()` (already BM) — never display `rawMessage` (may be English).
- **The privacy line holds in the UI too:** voter search/detail results live in memory only. **Never write voter data to the draft store or any persistence.** `data/local/` stays drafts-only.
- **Masked fields are opaque.** The server returns `'****'` for `no_ic`/`no_tel`/`alamat` etc. to `user`-role viewers. Display them as-is; never assume or reconstruct a real value.
- **Search behaviour (contract §9):** `q` needs **min 3 chars**; for a `user`-role viewer, `no_ic` matches only on an **exact 12-digit** string (partial-IC search returns nothing) — so the search field is name-first; the *scan* flow provides the full 12-digit IC for exact lookup. The route is throttled **30/min** — **debounce** the search field (≥400ms) so type-ahead doesn't 429.
- **`sync/` and `models/` stay Flutter-free** (2b-i adds only UI + providers; it must not add a Flutter import to those dirs).
- **UI reaches data only through providers**, never importing `data/` directly in a widget beyond the provider wiring.
- **Flutter tests only** (`flutter test` from `sisda_app/`). `flutter analyze` must stay at **0 issues** (2a left it clean). No PHP.
- **Widget tests use `ProviderScope(overrides: [...])`** with a mocktail `MockMobileApi` and `AppDatabase.forTesting()` — the established 2a pattern (see `test/features/auth_controller_test.dart`).
- Flutter/Dart SDK floor unchanged (`^3.11.1`).

## File Structure

| File | Responsibility |
|---|---|
| `sisda_app/lib/features/shell/main_shell.dart` | **Create** — the 4-tab `IndexedStack` scaffold + bottom nav + Perlu Perhatian badge; the root `WidgetsBindingObserver` for foreground-sync. |
| `sisda_app/lib/features/culaan/culaan_form_screen.dart` | **Create** — a minimal STUB (accepts `draftKey`/`prefillVoter` args, shows a "akan datang" placeholder). **Plan 2b-ii replaces its body with the real form.** |
| `sisda_app/lib/features/sync/draft_counts_provider.dart` | **Create** — `StreamProvider` over `DraftStore.watchAll()` exposing `{queued, failed}` counts; used by the shell badge and the home strip. |
| `sisda_app/lib/features/home/home_screen.dart` | **Rewrite** the existing `lib/screens/home_screen.dart` into the native search-first Utama (search bar + camera icon, amber queue strip, entry points). Moves to `features/home/`. |
| `sisda_app/lib/features/voters/voter_search_screen.dart` | **Create** — debounced name/IC search → results list. |
| `sisda_app/lib/features/voters/voter_search_controller.dart` | **Create** — the search state logic (debounce, min-3, loading/empty/error), testable without the widget. |
| `sisda_app/lib/features/voters/voter_detail_screen.dart` | **Create** — read-only masked detail + "Culaan baru untuk pengundi ini" → form stub with prefill. |
| `sisda_app/lib/features/voters/ic_scan.dart` | **Create** — the scan→OCR→`showVoter`→detail flow; the IC-resolution logic factored testable, camera capture emulator-only. |
| `sisda_app/lib/features/perlu_perhatian/perlu_perhatian_screen.dart` | **Create** — live view of `failed` drafts, BM reason, discard; "Betulkan" navigates to the form stub (re-open is 2b-ii). |
| `sisda_app/lib/features/culaan/rekod_saya_screen.dart` | **Create** — `culaan/mine` list (masked) + "Borang Culaan Baru" entry. |
| `sisda_app/lib/features/webview/web_screens.dart` | **Create** — thin helpers opening `WebViewScreen` for Dashboard/Laporan/Data Pengundi from within the tabs. |
| `sisda_app/lib/screens/splash_screen.dart` | **Modify** — route to `MainShell` (not the old home) when logged in. |
| `sisda_app/lib/screens/home_screen.dart`, `webview_screen.dart` | `home_screen.dart` **deleted** (replaced by `features/home/`); `webview_screen.dart` **kept** (reused by the WebView tail). |
| `sisda_app/test/features/**` | **Create** — widget/controller tests per task. |

---

### Task 1: Navigation shell + form stub + foreground-sync

The frame everything else slots into. A `MainShell` with a 4-tab bottom nav (Utama · Culaan · Perlu Perhatian · Profil), preserving each tab's state via `IndexedStack`. The Perlu Perhatian tab shows a badge of the `failed`-draft count. The shell is also the root `WidgetsBindingObserver` that drains the sync queue when the app returns to the foreground. Includes the `CulaanFormScreen` stub so navigation to the (2b-ii) form compiles and is reachable.

**Files:**
- Create: `sisda_app/lib/features/shell/main_shell.dart`
- Create: `sisda_app/lib/features/culaan/culaan_form_screen.dart`
- Create: `sisda_app/lib/features/sync/draft_counts_provider.dart`
- Modify: `sisda_app/lib/screens/splash_screen.dart`
- Test: `sisda_app/test/features/main_shell_test.dart`

**Interfaces:**
- Consumes: `appDatabaseProvider` (→ `DraftStore.watchAll()`), `syncEngineProvider` (`syncNow({required now})`), all from 2a.
- Produces:
  - `draftCountsProvider` — `StreamProvider<DraftCounts>` where `class DraftCounts { int queued; int failed; }`, derived from `watchAll()` (`queued` = status `queued` or `syncing`; `failed` = status `failed`).
  - `class MainShell extends ConsumerStatefulWidget` — the app's logged-in root.
  - `class CulaanFormScreen extends ConsumerWidget` with `CulaanFormScreen({String? draftKey, Voter? prefillVoter})` — a STUB (placeholder body). Later tasks and 2b-ii navigate to it.

- [ ] **Step 1: Write the failing test**

Create `sisda_app/test/features/main_shell_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sisda_app/data/local/database.dart';
import 'package:sisda_app/models/culaan_draft.dart';
import 'package:sisda_app/models/sync_status.dart';
import 'package:sisda_app/providers.dart';
import 'package:sisda_app/features/shell/main_shell.dart';

void main() {
  final now = DateTime.utc(2026, 7, 18);

  Future<void> pumpShell(WidgetTester tester, AppDatabase db) async {
    await tester.pumpWidget(ProviderScope(
      overrides: [appDatabaseProvider.overrideWithValue(db)],
      child: const MaterialApp(home: MainShell()),
    ));
    await tester.pump(); // let the stream emit
  }

  testWidgets('renders four tabs', (tester) async {
    final db = AppDatabase.forTesting();
    addTearDown(db.close);
    await pumpShell(tester, db);
    expect(find.text('Utama'), findsOneWidget);
    expect(find.text('Culaan'), findsOneWidget);
    expect(find.text('Perlu Perhatian'), findsOneWidget);
    expect(find.text('Profil'), findsOneWidget);
  });

  testWidgets('Perlu Perhatian badge shows the failed-draft count', (tester) async {
    final db = AppDatabase.forTesting();
    addTearDown(db.close);
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'f1', now: now)
        .copyWith(status: SyncStatus.failed, failureReason: 'Rekod ini di luar Parlimen anda.'));
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'q1', now: now)
        .copyWith(status: SyncStatus.queued));
    await pumpShell(tester, db);
    await tester.pump();
    expect(find.text('1'), findsOneWidget); // one failed draft → badge "1"
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run (from `sisda_app/`): `flutter test test/features/main_shell_test.dart`
Expected: FAIL — `main_shell.dart` missing.

- [ ] **Step 3: Write the draft-counts provider**

Create `sisda_app/lib/features/sync/draft_counts_provider.dart`:

```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../models/sync_status.dart';
import '../../providers.dart';

class DraftCounts {
  final int queued;
  final int failed;
  const DraftCounts({required this.queued, required this.failed});
}

/// Live counts over the local draft store, for the home queue strip
/// ("N culaan menunggu talian") and the Perlu Perhatian badge.
final draftCountsProvider = StreamProvider<DraftCounts>((ref) {
  final db = ref.watch(appDatabaseProvider);
  return db.watchAll().map((drafts) => DraftCounts(
        queued: drafts
            .where((d) => d.status == SyncStatus.queued || d.status == SyncStatus.syncing)
            .length,
        failed: drafts.where((d) => d.status == SyncStatus.failed).length,
      ));
});
```

- [ ] **Step 4: Write the form stub**

Create `sisda_app/lib/features/culaan/culaan_form_screen.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../models/voter.dart';

/// STUB — Plan 2b-ii replaces this body with the real checklist-hub form.
/// It accepts the navigation arguments 2b-i's entry points pass, so the
/// navigation graph is complete and testable now.
///  - draftKey: re-open an existing draft (from Perlu Perhatian "Betulkan")
///  - prefillVoter: masked-create prefill (from voter detail)
class CulaanFormScreen extends ConsumerWidget {
  final String? draftKey;
  final Voter? prefillVoter;
  const CulaanFormScreen({super.key, this.draftKey, this.prefillVoter});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(title: const Text('Borang Culaan')),
      body: const Center(
        child: Padding(
          padding: EdgeInsets.all(24),
          child: Text('Borang Culaan akan tersedia tidak lama lagi.',
              textAlign: TextAlign.center),
        ),
      ),
    );
  }
}
```

- [ ] **Step 5: Write the shell**

Create `sisda_app/lib/features/shell/main_shell.dart`. Requirements:
- `ConsumerStatefulWidget` with `WidgetsBindingObserver` (register in `initState`, remove in `dispose`).
- `didChangeAppLifecycleState(AppLifecycleState.resumed)` → `ref.read(syncEngineProvider).syncNow(now: DateTime.now())`. **This `DateTime.now()` is allowed (wiring layer); the engine stays pure.**
- `IndexedStack` over four tab bodies (import the real screens as they land in later tasks; for THIS task the Culaan/Perlu/Profil bodies may be simple `Placeholder`/text — but the Utama body must at least be a scaffold so the shell renders; later tasks swap in the real screens). Use named consts for the tab labels: `'Utama'`, `'Culaan'`, `'Perlu Perhatian'`, `'Profil'`.
- Bottom nav: `NavigationBar` with 4 destinations. The Perlu Perhatian destination wraps its icon in a `Badge` showing `ref.watch(draftCountsProvider).valueOrNull?.failed`, hidden when 0.
- Profil destination opens the WebView Profil (`WebViewScreen(path: '/profile')`) — you may wire this fully here or leave a placeholder that Task 8's WebView helper fills; if placeholder, note it.

Write the concrete `NavigationBar` + `IndexedStack` + `Badge` code (the test asserts the four labels and the badge count).

- [ ] **Step 6: Route splash to the shell**

In `sisda_app/lib/screens/splash_screen.dart`, change the logged-in route target from the old `HomeScreen` to `const MainShell()`. Keep the splash animation and the `authControllerProvider`-based session restore exactly as 2a left them.

- [ ] **Step 7: Run test + analyze**

Run: `flutter test test/features/main_shell_test.dart` → PASS (2 tests).
Run: `flutter analyze` → `No issues found!`

- [ ] **Step 8: Commit**

```bash
cd sisda_app
git add lib/features/shell/ lib/features/culaan/culaan_form_screen.dart lib/features/sync/draft_counts_provider.dart lib/screens/splash_screen.dart test/features/main_shell_test.dart
git commit -m "Klien mudah alih 2b: rangka nav 4-tab + lencana Perlu Perhatian + stub borang"
```

---

### Task 2: Voter detail screen (read-only, masked)

Built before its callers (search and scan both navigate here). Displays a `Voter`'s fields exactly as returned — masked fields show `'****'`. A "Culaan baru untuk pengundi ini" button navigates to the `CulaanFormScreen` stub with `prefillVoter` set (the masked-create entry point; 2b-ii turns the masked values + the voter id into a `locked_source_id` draft).

**Files:**
- Create: `sisda_app/lib/features/voters/voter_detail_screen.dart`
- Test: `sisda_app/test/features/voter_detail_test.dart`

**Interfaces:**
- Consumes: `Voter` (2a model), `CulaanFormScreen({prefillVoter})` (Task 1).
- Produces: `class VoterDetailScreen extends ConsumerWidget` with `VoterDetailScreen({required Voter voter})`.

- [ ] **Step 1: Write the failing test**

Create `sisda_app/test/features/voter_detail_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sisda_app/models/voter.dart';
import 'package:sisda_app/features/voters/voter_detail_screen.dart';

void main() {
  testWidgets('shows nama and displays masked fields verbatim', (tester) async {
    final voter = Voter.fromJson({
      'id': 5, 'nama': 'Ahmad bin Ali', 'no_ic': '****', 'no_tel': '****',
      'kadun': 'BULOH KASAP',
    });
    await tester.pumpWidget(ProviderScope(
      child: MaterialApp(home: VoterDetailScreen(voter: voter)),
    ));
    expect(find.text('Ahmad bin Ali'), findsOneWidget);
    expect(find.text('****'), findsWidgets); // masked fields shown as-is
    expect(find.text('BULOH KASAP'), findsOneWidget);
    expect(find.text('Culaan baru untuk pengundi ini'), findsOneWidget);
  });
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `flutter test test/features/voter_detail_test.dart` → FAIL (missing file).

- [ ] **Step 3: Write the screen**

Create `voter_detail_screen.dart`: a `Scaffold` (AppBar title "Butiran Pengundi") showing `voter.nama` prominently and a list of labelled rows for the fields (Nama, No. IC, Umur, No. Tel, Alamat, Poskod, Kadun, Parlimen, Kecenderungan Politik, etc.) using `voter.field(key)` — display `'—'` for empty strings (unknown), the raw value otherwise (so `'****'` shows as `'****'`). A bottom button "Culaan baru untuk pengundi ini" → `Navigator.push` to `CulaanFormScreen(prefillVoter: voter)`. **Read-only — no edit affordances.** All labels BM.

- [ ] **Step 4: Run to verify it passes** → `flutter test test/features/voter_detail_test.dart` PASS.

- [ ] **Step 5: Commit**

```bash
cd sisda_app
git add lib/features/voters/voter_detail_screen.dart test/features/voter_detail_test.dart
git commit -m "Klien mudah alih 2b: skrin butiran pengundi (baca sahaja, medan bertopeng)"
```

---

### Task 3: Voter search — controller + screen

A name-first search (the contract makes partial-IC search return nothing for `user` role, so the field is primarily for names; a full 12-digit IC still works via exact match). The controller holds the state and the **debounce** (≥400ms, so the 30/min throttle isn't tripped by type-ahead) and the min-3-char gate; the screen renders loading/results/empty/error. Errors surface `ApiException.firstError()` (BM).

**Files:**
- Create: `sisda_app/lib/features/voters/voter_search_controller.dart`
- Create: `sisda_app/lib/features/voters/voter_search_screen.dart`
- Test: `sisda_app/test/features/voter_search_test.dart`

**Interfaces:**
- Consumes: `mobileApiProvider` (`searchVoters(q) -> Future<List<Voter>>`), `VoterDetailScreen` (Task 2).
- Produces:
  - `class VoterSearchState { bool loading; List<Voter> results; String? error; bool tooShort; }` — default `const VoterSearchState()` = all-empty/false.
  - `voterSearchControllerProvider` — `NotifierProvider<VoterSearchController, VoterSearchState>`. The controller exposes **`Future<void> searchNow(String q)`** (NO debounce — the *widget* wraps calls in a debounce `Timer`; the controller stays synchronous-to-call and directly testable). `searchNow` enforces min-3 (sets `tooShort`, no API call), else calls `searchVoters`, populating `results`; on `ApiException` sets `error = firstError()` and clears `results`.
  - `class VoterSearchScreen extends ConsumerWidget`.

- [ ] **Step 1: Write the failing test**

Create `sisda_app/test/features/voter_search_test.dart` (tests the controller via `searchNow`, deterministic — no timers):

```dart
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mocktail/mocktail.dart';
import 'package:sisda_app/data/remote/mobile_api.dart';
import 'package:sisda_app/models/api_result.dart';
import 'package:sisda_app/models/voter.dart';
import 'package:sisda_app/providers.dart';
import 'package:sisda_app/features/voters/voter_search_controller.dart';

class MockMobileApi extends Mock implements MobileApi {}

void main() {
  late MockMobileApi api;
  setUp(() => api = MockMobileApi());

  ProviderContainer container() =>
      ProviderContainer(overrides: [mobileApiProvider.overrideWithValue(api)]);

  test('query under 3 chars sets tooShort and never calls the API', () async {
    final c = container();
    await c.read(voterSearchControllerProvider.notifier).searchNow('Ah');
    final s = c.read(voterSearchControllerProvider);
    expect(s.tooShort, isTrue);
    expect(s.results, isEmpty);
    verifyNever(() => api.searchVoters(any()));
  });

  test('valid query populates results', () async {
    when(() => api.searchVoters('Ahmad')).thenAnswer((_) async =>
        [Voter.fromJson({'id': 1, 'nama': 'Ahmad bin Ali', 'no_ic': '****'})]);
    final c = container();
    await c.read(voterSearchControllerProvider.notifier).searchNow('Ahmad');
    final s = c.read(voterSearchControllerProvider);
    expect(s.results.single.nama, 'Ahmad bin Ali');
    expect(s.error, isNull);
    expect(s.tooShort, isFalse);
  });

  test('ApiException surfaces BM firstError and clears results, no English', () async {
    when(() => api.searchVoters(any())).thenThrow(const ApiException(
        status: 500, errors: {}, rawMessage: 'Server Error'));
    final c = container();
    await c.read(voterSearchControllerProvider.notifier).searchNow('Ahmad');
    final s = c.read(voterSearchControllerProvider);
    expect(s.error, isNotEmpty);
    expect(s.error, isNot(contains('Server Error'))); // never leak rawMessage
    expect(s.results, isEmpty);
  });
}
```

- [ ] **Step 2: Run to verify it fails** → FAIL (missing files).

- [ ] **Step 3: Write the controller**

Create `voter_search_controller.dart`. The controller:
- Debounces input (a `Timer`; expose a test seam — e.g. a `Duration debounce` field defaulting to 400ms that tests can set to `Duration.zero`, OR a `searchNow(q)` the widget's debounce calls and tests call directly).
- On `q.trim().length < 3` → `state = VoterSearchState(tooShort: true, results: [])`, no API call.
- Else → `loading`, call `ref.read(mobileApiProvider).searchVoters(q)`, set `results`; on `ApiException` set `error = e.firstError()` and clear `results`.

- [ ] **Step 4: Write the screen**

Create `voter_search_screen.dart`: a `TextField` (hint "Cari nama atau No. IC…") driving the controller; body shows a spinner while `loading`, the results as tappable `ListTile`s (`voter.nama` + Kadun; tap → `VoterDetailScreen(voter:)`), a BM empty state ("Tiada pengundi dijumpai."), the `tooShort` hint ("Sila masukkan sekurang-kurangnya 3 aksara."), or `state.error` in a BM error banner.

- [ ] **Step 5: Run to verify it passes** → `flutter test test/features/voter_search_test.dart` PASS.

- [ ] **Step 6: Commit**

```bash
cd sisda_app
git add lib/features/voters/voter_search_controller.dart lib/features/voters/voter_search_screen.dart test/features/voter_search_test.dart
git commit -m "Klien mudah alih 2b: carian pengundi (debounce, min-3, ralat BM)"
```

---

### Task 4: IC scan → voter lookup

The scan flow: camera → OCR (`OcrService.extractFromImage`) → extract the 12-digit IC → `MobileApi.showVoter(ic)` → `VoterDetailScreen`, or a BM "tidak dijumpai" when the lookup 404s. The **IC-resolution logic** (given an IC string, look it up and decide navigate-vs-notfound) is factored into a testable function; the **camera capture itself is emulator-only** (a plugin dependency), so it is exercised on-device, not in widget tests — this is noted, not faked.

**Files:**
- Create: `sisda_app/lib/features/voters/ic_scan.dart`
- Test: `sisda_app/test/features/ic_scan_test.dart`

**Interfaces:**
- Consumes: `OcrService.extractFromImage(File) -> Future<KpData>` (existing), `mobileApiProvider` (`showVoter(ic)`), `VoterDetailScreen`.
- Produces: `Future<IcLookupResult> resolveIc(String ic, MobileApi api)` returning one of a sealed `IcLookupResult` — `IcFound(Voter voter)` / `IcNotFound()` / `IcError(String bmMessage)` — unit-testable with a fake api. Plus `Future<void> startScanAndLookup(BuildContext, WidgetRef)` that does camera→OCR→`resolveIc`→navigate (device path, not unit-tested).

- [ ] **Step 1: Write the failing test**

Create `sisda_app/test/features/ic_scan_test.dart` (tests `resolveIc` with a `MockMobileApi`):

```dart
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:sisda_app/data/remote/mobile_api.dart';
import 'package:sisda_app/models/api_result.dart';
import 'package:sisda_app/models/voter.dart';
import 'package:sisda_app/features/voters/ic_scan.dart';

class MockMobileApi extends Mock implements MobileApi {}

void main() {
  late MockMobileApi api;
  setUp(() => api = MockMobileApi());

  test('a found voter → IcFound', () async {
    when(() => api.showVoter('800101015555')).thenAnswer(
        (_) async => Voter.fromJson({'id': 1, 'nama': 'Ahmad', 'no_ic': '****'}));
    final r = await resolveIc('800101015555', api);
    expect(r, isA<IcFound>());
    expect((r as IcFound).voter.nama, 'Ahmad');
  });

  test('404 → IcNotFound', () async {
    when(() => api.showVoter(any())).thenThrow(const ApiException(
        status: 404, errors: {'no_ic': ['Pengundi tidak dijumpai.']}));
    expect(await resolveIc('999999999999', api), isA<IcNotFound>());
  });

  test('network error → IcError with a BM message (no English)', () async {
    when(() => api.showVoter(any())).thenThrow(
        const ApiException(status: null, errors: {}, rawMessage: 'SocketException'));
    final r = await resolveIc('800101015555', api);
    expect(r, isA<IcError>());
    expect((r as IcError).bmMessage, isNot(contains('SocketException')));
  });
}
```

- [ ] **Step 2: Run to verify it fails** → FAIL.

- [ ] **Step 3: Write `resolveIc` + the scan wiring**

Create `ic_scan.dart`. `resolveIc` calls `api.showVoter(ic)`, maps a returned `Voter` → found, a 404 → notFound, any other `ApiException` → error (`e.firstError()`). `startScanAndLookup` uses `image_picker`/`camera` to capture, `OcrService.extractFromImage` to get `KpData`, guards `kpData.icNumber` (BM snackbar "Tidak dapat membaca No. IC…" if null — reuse the existing home-screen copy), then `resolveIc` and navigates to `VoterDetailScreen` on found / shows a BM snackbar on notFound/error. Mark the capture path with a comment that it is emulator-tested.

- [ ] **Step 4: Run to verify it passes** → `flutter test test/features/ic_scan_test.dart` PASS.

- [ ] **Step 5: Commit**

```bash
cd sisda_app
git add lib/features/voters/ic_scan.dart test/features/ic_scan_test.dart
git commit -m "Klien mudah alih 2b: imbas KP -> carian pengundi (resolveIc diuji, kamera emulator)"
```

---

### Task 5: Utama — search-first home

The `Utama` tab. A search bar (with a camera icon inside it — "search by photo") at the top; the amber **"N culaan menunggu talian"** strip when the queued count > 0 (watches `draftCountsProvider`); and quick entry points ("Borang Culaan Baru" → form stub; recent records). Tapping the search bar opens `VoterSearchScreen`; the camera icon runs `startScanAndLookup`. Replaces the old WebView-shell `home_screen.dart`.

**Files:**
- Create: `sisda_app/lib/features/home/home_screen.dart`
- Delete: `sisda_app/lib/screens/home_screen.dart` (old WebView-shell version)
- Modify: `sisda_app/lib/features/shell/main_shell.dart` (wire the Utama tab body to the new home)
- Test: `sisda_app/test/features/home_screen_test.dart`

**Interfaces:**
- Consumes: `draftCountsProvider` (Task 1), `VoterSearchScreen` (Task 3), `startScanAndLookup` (Task 4), `CulaanFormScreen` (Task 1).
- Produces: `class HomeScreen extends ConsumerWidget` (the Utama body).

- [ ] **Step 1: Write the failing test**

Create `home_screen_test.dart`. With `appDatabaseProvider` overridden to an in-memory DB seeded with 3 `queued` drafts, assert the amber strip shows **"3 culaan menunggu talian"**; with 0 queued, the strip is absent. Assert the search bar (hint text) and the "Borang Culaan Baru" entry are present. (Seed drafts via the DB as in Task 1's test.)

- [ ] **Step 2: Run to verify it fails** → FAIL.

- [ ] **Step 3: Write the home**

Create `features/home/home_screen.dart` per the spec's search-first layout (§UI/UX): a tappable search field with a trailing camera `IconButton`; below it the amber strip (`Container`, amber bg, `↻` icon, text `'$queued culaan menunggu talian'`) shown only when `queued > 0`; then the entry buttons. Tapping the field → `VoterSearchScreen`; camera icon → `startScanAndLookup(context, ref)`; "Borang Culaan Baru" → `CulaanFormScreen()`. All BM.

- [ ] **Step 4: Wire it into the shell**

In `main_shell.dart`, replace the Utama placeholder body with `const HomeScreen()`.

- [ ] **Step 5: Delete the old home + verify no refs**

`git rm sisda_app/lib/screens/home_screen.dart`. Run `flutter analyze` → confirm no dangling reference to the old `HomeScreen` (splash already routes to `MainShell` from Task 1).

- [ ] **Step 6: Run tests** → `flutter test` (full suite) green.

- [ ] **Step 7: Commit**

```bash
cd sisda_app
git add lib/features/home/ lib/features/shell/main_shell.dart test/features/home_screen_test.dart
git rm lib/screens/home_screen.dart
git commit -m "Klien mudah alih 2b: Utama search-first + jalur amber baris gilir sync"
```

---

### Task 6: Perlu Perhatian inbox

A live view of `failed` drafts (`watchAll()` filtered), each showing its BM `failureReason` and a **discard** action (`deleteDraft` after a BM confirm dialog). The **"Betulkan" (fix-and-resubmit)** action navigates to `CulaanFormScreen(draftKey: ...)` — in 2b-i that opens the stub; **2b-ii makes it re-open the draft in the editor**. This screen is the `Perlu Perhatian` tab body.

**Files:**
- Create: `sisda_app/lib/features/perlu_perhatian/perlu_perhatian_screen.dart`
- Modify: `sisda_app/lib/features/shell/main_shell.dart` (wire the tab body)
- Test: `sisda_app/test/features/perlu_perhatian_test.dart`

**Interfaces:**
- Consumes: `appDatabaseProvider` (`watchAll()`, `deleteDraft(key)`), `CulaanFormScreen({draftKey})` (Task 1).
- Produces: `class PerluPerhatianScreen extends ConsumerWidget`.

- [ ] **Step 1: Write the failing test**

Create `perlu_perhatian_test.dart`: seed the in-memory DB with two `failed` drafts (distinct `failureReason`s, e.g. "Rekod ini di luar Parlimen anda." and "Ruangan Bil. Isi Rumah diperlukan.") and one `queued` draft. Assert the screen lists exactly the two failed reasons and NOT the queued one. Then tap a discard action → confirm → assert that draft is gone from the DB (`getDraft` returns null) and the list shrinks to one.

- [ ] **Step 2: Run to verify it fails** → FAIL.

- [ ] **Step 3: Write the screen**

Create `perlu_perhatian_screen.dart`: watch `appDatabaseProvider.watchAll()`, filter `status == failed`, render each as a card showing `nama` (from `draft.fields['nama'] ?? 'Pengundi'`) + the BM `failureReason`, a "Betulkan" button (→ `CulaanFormScreen(draftKey: draft.idempotencyKey)`) and a "Buang" button (BM `AlertDialog` confirm → `deleteDraft`). Empty state: "Tiada culaan yang perlu perhatian." All BM.

- [ ] **Step 4: Wire into the shell** — replace the Perlu Perhatian placeholder body with `const PerluPerhatianScreen()`.

- [ ] **Step 5: Run tests** → `flutter test test/features/perlu_perhatian_test.dart` PASS; full suite green.

- [ ] **Step 6: Commit**

```bash
cd sisda_app
git add lib/features/perlu_perhatian/ lib/features/shell/main_shell.dart test/features/perlu_perhatian_test.dart
git commit -m "Klien mudah alih 2b: peti masuk Perlu Perhatian (sebab BM, buang; betulkan->2b-ii)"
```

---

### Task 7: Rekod Saya (Culaan tab) + pull-to-refresh sync trigger

The `Culaan` tab: the user's server-side submissions from `MobileApi.culaanMine()` (masked), with a "Borang Culaan Baru" entry → form stub. Adds **pull-to-refresh** on this list and on Utama, which calls `syncEngine.syncNow(now: DateTime.now())` then reloads — completing the sync-trigger set (connectivity from 2a, foreground from Task 1, pull-to-refresh here).

**Files:**
- Create: `sisda_app/lib/features/culaan/rekod_saya_screen.dart`
- Modify: `sisda_app/lib/features/shell/main_shell.dart` (wire the Culaan tab), `sisda_app/lib/features/home/home_screen.dart` (add pull-to-refresh)
- Test: `sisda_app/test/features/rekod_saya_test.dart`

**Interfaces:**
- Consumes: `mobileApiProvider` (`culaanMine() -> Future<List<Map<String,dynamic>>>`), `syncEngineProvider` (`syncNow`), `CulaanFormScreen` (Task 1).
- Produces: `class RekodSayaScreen extends ConsumerWidget`.

- [ ] **Step 1: Write the failing test**

Create `rekod_saya_test.dart` with a `MockMobileApi` whose `culaanMine()` returns two records (each a `Map` with `nama` + masked `no_ic`). Assert both `nama`s render and the masked `no_ic` shows as `'****'`. Assert a BM empty-state when `culaanMine()` returns `[]`.

- [ ] **Step 2: Run to verify it fails** → FAIL.

- [ ] **Step 3: Write the screen + pull-to-refresh**

Create `rekod_saya_screen.dart`: a `FutureProvider`/`FutureBuilder` over `culaanMine()`, rendering each record's `nama` + masked fields in a `ListTile`, wrapped in a `RefreshIndicator` whose `onRefresh` calls `ref.read(syncEngineProvider).syncNow(now: DateTime.now())` then invalidates the future. BM empty state "Tiada rekod culaan lagi." A "Borang Culaan Baru" FAB → `CulaanFormScreen()`. Add the same `RefreshIndicator`+`syncNow` wrapper to Utama's scroll body.

- [ ] **Step 4: Wire into the shell** — Culaan tab body → `const RekodSayaScreen()`.

- [ ] **Step 5: Run tests** → `flutter test test/features/rekod_saya_test.dart` PASS; full suite green.

- [ ] **Step 6: Commit**

```bash
cd sisda_app
git add lib/features/culaan/rekod_saya_screen.dart lib/features/shell/main_shell.dart lib/features/home/home_screen.dart test/features/rekod_saya_test.dart
git commit -m "Klien mudah alih 2b: Rekod Saya (culaan/mine) + pull-to-refresh cetus sync"
```

---

### Task 8: WebView tail + Profil tab

Wire the heavy web screens (Dashboard, Laporan, Data Pengundi) as `WebViewScreen` destinations reachable from within the native tabs, and make the Profil tab open the WebView profile. Reuses the existing `WebViewScreen` (already Riverpod-wired to `webAuthToken`/`webAuthUrl` from 2a). This closes the "native core, WebView tail" boundary the spec defines.

**Files:**
- Create: `sisda_app/lib/features/webview/web_screens.dart`
- Modify: `sisda_app/lib/features/shell/main_shell.dart` (Profil tab → WebView; add web-screen entry points where the spec places them, e.g. a menu on Utama or the Profil screen)
- Test: `sisda_app/test/features/web_screens_test.dart`

**Interfaces:**
- Consumes: `WebViewScreen({required path, embedded})` (existing 2a-refactored screen).
- Produces: helpers like `void openDashboard(BuildContext)`, `openLaporan`, `openDataPengundi`, `openProfil` — each `Navigator.push`ing a `WebViewScreen` with the right path (`/dashboard`, `/reports/hasil-culaan`, `/reports/data-pengundi`, `/profile`).

- [ ] **Step 1: Write the failing test**

Create `web_screens_test.dart`: a widget test that pumps a button calling `openDashboard(context)` and asserts a `WebViewScreen` is pushed (find it by type) with `path == '/dashboard'`. (You may need to expose the pushed widget via a test key or assert `find.byType(WebViewScreen)` after tapping.) Keep it light — the WebView content itself isn't loaded in a widget test.

- [ ] **Step 2: Run to verify it fails** → FAIL.

- [ ] **Step 3: Write the helpers + wire Profil**

Create `web_screens.dart` with the four `open*` helpers. In `main_shell.dart`, make the Profil tab body (or a button within it) call `openProfil`, and place Dashboard/Laporan/Data Pengundi entry points per the spec (a simple menu list is fine — this is the WebView tail, not a redesign). All labels BM.

- [ ] **Step 4: Run tests** → `flutter test test/features/web_screens_test.dart` PASS; full suite green; `flutter analyze` 0 issues.

- [ ] **Step 5: Commit**

```bash
cd sisda_app
git add lib/features/webview/ lib/features/shell/main_shell.dart test/features/web_screens_test.dart
git commit -m "Klien mudah alih 2b: ekor WebView (Dashboard/Laporan/Data Pengundi/Profil)"
```

---

## Definition of Done

- [ ] `flutter test` (from `sisda_app/`) — all green (the 2a suite plus every 2b-i widget/controller test).
- [ ] `flutter analyze` — `No issues found!` (stays at 0).
- [ ] The 4-tab shell renders; the Perlu Perhatian badge and the amber Utama strip reflect the live draft store; both were proven against a seeded in-memory DB.
- [ ] Voter search debounces (no type-ahead 429), enforces min-3, shows masked fields verbatim, surfaces BM errors; scan resolves an IC to detail or a BM not-found.
- [ ] The Perlu Perhatian inbox lists `failed` drafts with their BM reason and discards them; "Betulkan" routes to the form stub (real re-open is 2b-ii).
- [ ] Rekod Saya lists `culaan/mine`; pull-to-refresh and app-foreground both call `syncNow` (connectivity trigger already from 2a).
- [ ] No voter PII is persisted anywhere — `data/local/` is still drafts-only; search/detail data lives only in memory.
- [ ] `sync/` and `models/` still contain no `package:flutter/` import; the old WebView-shell `home_screen.dart` is deleted and unreferenced.

## Scope boundary — deferred to Plan 2b-ii

The **checklist-hub Culaan write form** (the 7 sections, the cascading `pekerjaan → jenis_pekerjaan` category-grouped taxonomy from `culaan/options`, the `has_sumbangan` toggle, draft creation/editing, and the masked-create `locked_source_id` submission) is Plan 2b-ii. In 2b-i, every path that would open the form navigates to the `CulaanFormScreen` **stub**, and draft *creation* does not exist yet — so the queue strip and Perlu Perhatian inbox are exercised via seeded drafts in tests and go live once 2b-ii ships. Real-device/emulator E2E against the **deployed** mobile API is also 2b-ii's milestone (it needs the `feature/mobile-app-user` API branch merged and deployed).

## Branch note

2b-i continues on `feature/mobile-client-2a` (where 2a is complete) or a `feature/mobile-client-2b` branch off it — decide at execution time. It touches only `sisda_app/`; no PHP, no server needed (all tests use fakes/in-memory). The user's stashed `MasterDataController.php` WIP belongs on `feature/mobile-app-user` and is not involved here.
