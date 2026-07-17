# Flutter Client 2a — Foundation & Offline Sync Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the data, sync, and state-management foundation of the SISDA native Flutter client — a fully unit-tested offline write-queue for Hasil Culaan — with no new screens, so the riskiest part (sync) is proven before any UI is built on it (Plan 2b).

**Architecture:** Layered under `sisda_app/lib/`: `models/` (plain Dart, no Flutter), `data/local/` (Drift SQLite — **drafts only**), `data/remote/` (typed `MobileApi` client), `sync/` (the queue engine — pure logic, no Flutter, testable with a fake API + in-memory Drift), and Riverpod providers wiring them together. The existing auth screens are refactored to consume a Riverpod auth controller instead of the static `ApiService`.

**Tech Stack:** Flutter 3.44.5 / Dart `^3.11.1`. Add: `flutter_riverpod`, `drift` + `sqlite3_flutter_libs` + `path_provider`, `uuid`; dev: `drift_dev`, `build_runner`, `mocktail`.

**This is Plan 2a of 2** (Plan 2b = screens: search-first home, voter search/scan/detail, checklist-hub Culaan form, Perlu Perhatian inbox). 2a produces working, independently testable software: a `flutter test`-green offline sync engine.

**Primary sources (read before starting):**
- **API contract (THE source of truth):** `docs/superpowers/specs/2026-07-17-kontrak-api-mudah-alih.md`. Where it disagrees with the design doc, the contract wins — the API changed across six rounds of security fixes after the design was written.
- Design/UX spec: `docs/superpowers/specs/2026-07-17-aplikasi-mudah-alih-user-design.md` (data-flow, failure classification, privacy line).
- Existing app: `sisda_app/lib/` — `main.dart`, `services/api_service.dart` (static, bare), `services/ocr_service.dart`, `screens/{splash,login,register,forgot_password,home,webview}_screen.dart`, `theme/app_theme.dart`.

## Global Constraints

- **All user-facing strings are Bahasa Melayu.** No i18n layer; hardcode inline. Failure reasons shown to the user come from the server's `errors.<field>` (already BM for the mobile-specific endpoints).
- **The contract doc is authoritative** for every endpoint shape. The design doc's API table is stale (it lists a `POST /token/refresh` that does NOT exist, and says "13 endpoints" with wrong detail). Build against the contract.
- **Privacy line — non-negotiable:** `data/local/` stores **drafts only**. No voter PII is ever cached on device. Reads (voter search/detail) require signal and are never persisted. This is what makes the masking model safe on a lost/stolen phone.
- **`sync/` and `models/` MUST NOT import `package:flutter/*`** — they are pure Dart, unit-testable without a device. `data/local/` may import Drift; `data/remote/` may import `http`. Only `features/` and provider-wiring files import Flutter.
- **UI never imports `data/` directly** — always through a Riverpod provider/controller.
- **Idempotency key is a client-generated UUID created once at draft creation and reused across every retry.** Never regenerate it on retry — a fresh key per attempt creates duplicate voter records, which is the exact hazard it defends against.
- **429 is transient, not permanent.** It is the one 4xx that must be retried. Misclassifying it strands a valid submission in Perlu Perhatian forever.
- **Never send `voter_color`** — the server computes it. It is not in the request rules; anything sent is ignored.
- **Masked-create drafts store `'****'` placeholders + `locked_source_id`, never the real sensitive values.** The server swaps in the truth at sync time.
- **Response envelope is NOT uniform** (contract §"Konvensyen respons"): mobile-specific endpoints return `{"success": bool, ...}`; geography endpoints return **bare JSON arrays**; `web-auth-token` returns bare `{"token":...}`; and **401/429 from middleware plus `register`/`login`-missing-field errors return Laravel's default `{"message":...,"errors":...}` in ENGLISH**. The client reads `errors.<field>` when present and **never displays `message`** (may be English). Status code is checked first.
- **Flutter tests only** (`flutter test` from `sisda_app/`). The Laravel `php artisan test` baseline is irrelevant to this plan. Do not touch any PHP.
- **No running server needed** — every test in this plan uses a fake API. Real-API E2E is deferred to Plan 2b (after the mobile API branch is merged and deployed).

## File Structure

| File | Responsibility |
|---|---|
| `sisda_app/pubspec.yaml` | **Modify** — add deps + dev deps. |
| `sisda_app/lib/main.dart` | **Modify** — wrap in `ProviderScope`. |
| `sisda_app/lib/models/sync_status.dart` | **Create** — `SyncStatus` + `FailureBucket` enums. |
| `sisda_app/lib/models/culaan_draft.dart` | **Create** — the offline write unit (plain Dart). |
| `sisda_app/lib/models/voter.dart` | **Create** — search/detail result (masked fields are strings). |
| `sisda_app/lib/models/api_result.dart` | **Create** — `ApiException` typed error carrying status + parsed `errors`. |
| `sisda_app/lib/sync/failure_classifier.dart` | **Create** — pure `classify(status, exception) → FailureBucket`. The heart of the retry logic. |
| `sisda_app/lib/sync/backoff.dart` | **Create** — pure exponential-backoff next-retry calculator. |
| `sisda_app/lib/sync/sync_engine.dart` | **Create** — drains the queue; depends on abstract `DraftStore` + `CulaanApi` interfaces (fakes in tests). No Flutter. |
| `sisda_app/lib/data/local/database.dart` | **Create** — Drift DB, `drafts` table only + DAO; in-memory ctor for tests. Implements `DraftStore`. |
| `sisda_app/lib/data/remote/mobile_api.dart` | **Create** — typed client; one method per endpoint; envelope-variance handling. Implements `CulaanApi`. Injectable `http.Client`. |
| `sisda_app/lib/data/remote/api_config.dart` | **Create** — base URL + token holder (moved off the static `ApiService`). |
| `sisda_app/lib/providers.dart` | **Create** — Riverpod providers (api, db, sync engine, connectivity trigger, auth controller). |
| `sisda_app/lib/features/auth/auth_controller.dart` | **Create** — Riverpod `AsyncNotifier` for login/register/forgot/logout/session-restore. |
| `sisda_app/lib/screens/*.dart` | **Modify** — swap static `ApiService` calls for the auth controller; keep the widgets. |
| `sisda_app/lib/services/api_service.dart` | **Delete at the end** — fully replaced by `mobile_api.dart` + `auth_controller.dart`. |
| `sisda_app/test/**` | **Create** — unit tests per task; `mocktail` fakes. |

---

### Task 1: Packages, project skeleton, ProviderScope

Establish dependencies and the `ProviderScope` root so every later task compiles. Deliverable: `flutter pub get` resolves, `flutter analyze` is clean, `flutter test` runs (after removing the stale default widget test).

**Files:**
- Modify: `sisda_app/pubspec.yaml`
- Modify: `sisda_app/lib/main.dart`
- Delete: `sisda_app/test/widget_test.dart` (the default counter test — references a `MyApp` that doesn't exist)

**Interfaces:**
- Consumes: nothing.
- Produces: a `ProviderScope`-wrapped app; the dependency set every later task imports.

- [ ] **Step 1: Add dependencies**

In `sisda_app/pubspec.yaml`, under `dependencies:` (keep the existing entries — `webview_flutter`, `http`, `shared_preferences`, `local_auth`, `google_mlkit_text_recognition`, `camera`, `image_picker`, `connectivity_plus`), add:

```yaml
  flutter_riverpod: ^2.6.1
  drift: ^2.20.0
  sqlite3_flutter_libs: ^0.5.24
  path_provider: ^2.1.4
  uuid: ^4.5.1
```

Under `dev_dependencies:` add:

```yaml
  drift_dev: ^2.20.0
  build_runner: ^2.4.13
  mocktail: ^1.0.4
```

- [ ] **Step 2: Resolve and verify**

Run (from `sisda_app/`): `flutter pub get`
Expected: resolves with no version-solve error. If any package requires a Dart SDK above `^3.11.1`, pin to the highest compatible version and note it — do not raise the SDK floor.

- [ ] **Step 3: Delete the stale default test**

Delete `sisda_app/test/widget_test.dart`. Run `flutter test`.
Expected: `No tests ran` (or passes) — not a compile error about `MyApp`.

- [ ] **Step 4: Wrap the app in ProviderScope**

Edit `sisda_app/lib/main.dart` — wrap `SisdaApp` in `ProviderScope`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'theme/app_theme.dart';
import 'screens/splash_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  runApp(const ProviderScope(child: SisdaApp()));
}

class SisdaApp extends StatelessWidget {
  const SisdaApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SISDA',
      theme: AppTheme.theme,
      debugShowCheckedModeBanner: false,
      home: const SplashScreen(),
    );
  }
}
```

- [ ] **Step 5: Verify analyze + build**

Run: `flutter analyze`
Expected: `No issues found!`

- [ ] **Step 6: Commit**

```bash
cd sisda_app
git add pubspec.yaml pubspec.lock lib/main.dart
git rm test/widget_test.dart
git commit -m "Klien mudah alih: tambah Riverpod/Drift/uuid, bungkus ProviderScope"
```

---

### Task 2: Enums + `CulaanDraft` model

The draft is the unit of work — it lives in SQLite from the first keystroke. This task defines the plain-Dart shape. No Flutter, no Drift imports here (the Drift table in Task 4 maps to/from this).

**Files:**
- Create: `sisda_app/lib/models/sync_status.dart`
- Create: `sisda_app/lib/models/culaan_draft.dart`
- Create: `sisda_app/test/models/culaan_draft_test.dart`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `enum SyncStatus { draft, queued, syncing, synced, failed }`
  - `enum FailureBucket { transient, auth, permanent }`
  - `class CulaanDraft` with: `String idempotencyKey`, `Map<String, dynamic> fields` (the culaan payload), `bool hasSumbangan`, `int? lockedSourceId`, `SyncStatus status`, `int attempts`, `DateTime? nextRetryAt`, `String? failureReason`, `DateTime createdAt`, `DateTime updatedAt`. Methods: `CulaanDraft.newDraft({required String idempotencyKey, required DateTime now})`, `Map<String, dynamic> toApiPayload()`, `CulaanDraft copyWith({...})`.

- [ ] **Step 1: Write the failing test**

Create `sisda_app/test/models/culaan_draft_test.dart`:

```dart
import 'package:flutter_test/flutter_test.dart';
import 'package:sisda_app/models/culaan_draft.dart';
import 'package:sisda_app/models/sync_status.dart';

void main() {
  final now = DateTime.utc(2026, 7, 18, 10, 0);

  test('newDraft starts in draft status with zero attempts', () {
    final d = CulaanDraft.newDraft(idempotencyKey: 'abc-123', now: now);
    expect(d.status, SyncStatus.draft);
    expect(d.attempts, 0);
    expect(d.idempotencyKey, 'abc-123');
    expect(d.fields, isEmpty);
    expect(d.nextRetryAt, isNull);
  });

  test('toApiPayload includes idempotency_key and has_sumbangan, excludes voter_color', () {
    final d = CulaanDraft.newDraft(idempotencyKey: 'abc-123', now: now).copyWith(
      fields: {'nama': 'Ahmad', 'no_ic': '800101015555', 'voter_color': 'hitam'},
      hasSumbangan: true,
      lockedSourceId: 42,
    );
    final p = d.toApiPayload();
    expect(p['idempotency_key'], 'abc-123');
    expect(p['nama'], 'Ahmad');
    expect(p['has_sumbangan'], true);
    expect(p['locked_source_id'], 42);
    // The server computes voter_color; the client must never send it.
    expect(p.containsKey('voter_color'), isFalse);
  });

  test('copyWith preserves unspecified fields', () {
    final d = CulaanDraft.newDraft(idempotencyKey: 'k', now: now)
        .copyWith(status: SyncStatus.queued, attempts: 2);
    expect(d.status, SyncStatus.queued);
    expect(d.attempts, 2);
    expect(d.idempotencyKey, 'k');
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `flutter test test/models/culaan_draft_test.dart`
Expected: FAIL — target files do not exist.

- [ ] **Step 3: Write the enums**

Create `sisda_app/lib/models/sync_status.dart`:

```dart
/// Lifecycle of a Culaan draft. `draft` (editing) → `queued` (user tapped
/// Hantar) → `syncing` → `synced` (deleted locally) or `failed` (→ Perlu
/// Perhatian). Pure Dart — no Flutter import.
enum SyncStatus { draft, queued, syncing, synced, failed }

/// How a sync attempt failed, which decides what happens next.
/// See the status→bucket table in the API contract.
enum FailureBucket { transient, auth, permanent }
```

- [ ] **Step 4: Write the model**

Create `sisda_app/lib/models/culaan_draft.dart`:

```dart
import 'sync_status.dart';

/// The offline write unit. Lives in local SQLite from the first keystroke.
/// Pure Dart — NO Flutter, NO Drift import (the Drift row maps to/from this).
class CulaanDraft {
  final String idempotencyKey;
  final Map<String, dynamic> fields;
  final bool hasSumbangan;
  final int? lockedSourceId;
  final SyncStatus status;
  final int attempts;
  final DateTime? nextRetryAt;
  final String? failureReason;
  final DateTime createdAt;
  final DateTime updatedAt;

  const CulaanDraft({
    required this.idempotencyKey,
    required this.fields,
    required this.hasSumbangan,
    required this.lockedSourceId,
    required this.status,
    required this.attempts,
    required this.nextRetryAt,
    required this.failureReason,
    required this.createdAt,
    required this.updatedAt,
  });

  factory CulaanDraft.newDraft({
    required String idempotencyKey,
    required DateTime now,
  }) =>
      CulaanDraft(
        idempotencyKey: idempotencyKey,
        fields: const {},
        hasSumbangan: false,
        lockedSourceId: null,
        status: SyncStatus.draft,
        attempts: 0,
        nextRetryAt: null,
        failureReason: null,
        createdAt: now,
        updatedAt: now,
      );

  /// The body POSTed to `/api/mobile/culaan`. Sends every field the user
  /// entered plus the control flags. NEVER includes voter_color — the
  /// server computes it (contract §13).
  Map<String, dynamic> toApiPayload() {
    final payload = <String, dynamic>{
      ...fields,
      'idempotency_key': idempotencyKey,
      'has_sumbangan': hasSumbangan,
    };
    if (lockedSourceId != null) payload['locked_source_id'] = lockedSourceId;
    payload.remove('voter_color');
    return payload;
  }

  CulaanDraft copyWith({
    Map<String, dynamic>? fields,
    bool? hasSumbangan,
    int? lockedSourceId,
    SyncStatus? status,
    int? attempts,
    DateTime? nextRetryAt,
    String? failureReason,
    DateTime? updatedAt,
  }) =>
      CulaanDraft(
        idempotencyKey: idempotencyKey,
        fields: fields ?? this.fields,
        hasSumbangan: hasSumbangan ?? this.hasSumbangan,
        lockedSourceId: lockedSourceId ?? this.lockedSourceId,
        status: status ?? this.status,
        attempts: attempts ?? this.attempts,
        nextRetryAt: nextRetryAt ?? this.nextRetryAt,
        failureReason: failureReason ?? this.failureReason,
        createdAt: createdAt,
        updatedAt: updatedAt ?? this.updatedAt,
      );
}
```

> **Note on `copyWith` and nullable fields:** the simple `??` pattern above cannot set `lockedSourceId`/`nextRetryAt`/`failureReason` back to `null` once set. Task 6 needs to clear `nextRetryAt` on success/failure transitions. If that limitation bites, switch those three params to a sentinel wrapper — but do NOT do it pre-emptively (YAGNI); the tests in Task 6 will reveal whether it's needed, and clearing is achievable by constructing a fresh `CulaanDraft` in the DAO layer instead.

- [ ] **Step 5: Run test to verify it passes**

Run: `flutter test test/models/culaan_draft_test.dart`
Expected: PASS — 3 tests.

- [ ] **Step 6: Commit**

```bash
cd sisda_app
git add lib/models/sync_status.dart lib/models/culaan_draft.dart test/models/culaan_draft_test.dart
git commit -m "Klien mudah alih: model CulaanDraft + enum status/baldi"
```

---

### Task 3: `Voter` model + `ApiException`

The read model (never persisted) and the typed error the API client throws. `Voter`'s sensitive fields are plain strings because the server returns `'****'` for masked fields — the client must treat them as opaque display strings, never assume a real value.

**Files:**
- Create: `sisda_app/lib/models/voter.dart`
- Create: `sisda_app/lib/models/api_result.dart`
- Create: `sisda_app/test/models/voter_test.dart`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `class Voter` with `int id`, `String nama`, and `Map<String, dynamic> fields` (the raw masked map), plus a `String field(String key)` accessor returning `''` for absent keys. `Voter.fromJson(Map<String, dynamic>)`.
  - `class ApiException implements Exception` with `int? status`, `Map<String, List<String>> errors`, `String? rawMessage`, and `String firstError()` (first BM field message, or a generic BM fallback). `bool get isNetworkError` (status == null).

- [ ] **Step 1: Write the failing test**

Create `sisda_app/test/models/voter_test.dart`:

```dart
import 'package:flutter_test/flutter_test.dart';
import 'package:sisda_app/models/voter.dart';
import 'package:sisda_app/models/api_result.dart';

void main() {
  test('Voter.fromJson keeps masked fields as opaque strings', () {
    final v = Voter.fromJson({
      'id': 101, 'nama': 'Ahmad bin Ali', 'no_ic': '****', 'no_tel': '****',
    });
    expect(v.id, 101);
    expect(v.nama, 'Ahmad bin Ali');
    expect(v.field('no_ic'), '****');
    expect(v.field('missing'), '');
  });

  test('ApiException.firstError returns the first field message', () {
    final e = ApiException(status: 422, errors: {
      'bil_isi_rumah': ['Ruangan Bil. Isi Rumah diperlukan.'],
    });
    expect(e.firstError(), 'Ruangan Bil. Isi Rumah diperlukan.');
  });

  test('ApiException with no field errors falls back to a BM message', () {
    final e = ApiException(status: 500, errors: {});
    expect(e.firstError(), isNotEmpty);
    expect(e.firstError(), isNot(contains('field'))); // never leak English default
  });

  test('network error has null status', () {
    final e = ApiException(status: null, errors: {});
    expect(e.isNetworkError, isTrue);
  });
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `flutter test test/models/voter_test.dart`
Expected: FAIL — files missing.

- [ ] **Step 3: Write the models**

Create `sisda_app/lib/models/voter.dart`:

```dart
/// A voter search/detail result. NEVER persisted to disk (privacy line).
/// Sensitive fields arrive as '****' for viewers who cannot unmask; treat
/// every field as an opaque display string, never a real value.
class Voter {
  final int id;
  final String nama;
  final Map<String, dynamic> fields;

  const Voter({required this.id, required this.nama, required this.fields});

  factory Voter.fromJson(Map<String, dynamic> json) => Voter(
        id: json['id'] as int,
        nama: (json['nama'] ?? '') as String,
        fields: json,
      );

  String field(String key) => (fields[key] ?? '').toString();
}
```

Create `sisda_app/lib/models/api_result.dart`:

```dart
/// Typed API failure. `status == null` means a transport error (no response).
/// `errors` is the server's `errors.<field>` map (BM for mobile endpoints).
/// `rawMessage` is the server's top-level `message` — NOT shown to users
/// (may be English); kept only for logging.
class ApiException implements Exception {
  final int? status;
  final Map<String, List<String>> errors;
  final String? rawMessage;

  const ApiException({required this.status, required this.errors, this.rawMessage});

  bool get isNetworkError => status == null;

  /// First user-facing BM message. Falls back to a generic BM line rather
  /// than ever surfacing Laravel's English default.
  String firstError() {
    for (final list in errors.values) {
      if (list.isNotEmpty) return list.first;
    }
    return 'Ralat tidak dijangka. Sila cuba lagi.';
  }

  @override
  String toString() => 'ApiException(status: $status, errors: $errors)';
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `flutter test test/models/voter_test.dart`
Expected: PASS — 4 tests.

- [ ] **Step 5: Commit**

```bash
cd sisda_app
git add lib/models/voter.dart lib/models/api_result.dart test/models/voter_test.dart
git commit -m "Klien mudah alih: model Voter (medan bertopeng opaque) + ApiException BM"
```

---

### Task 4: Failure classifier + backoff

The heart of the retry logic — pure functions, exhaustively tested. This is where the status→bucket table (contract §"Pemetaan status → baldi kegagalan") becomes code. Getting 429 wrong (classifying it permanent) strands real submissions forever, so it gets its own test.

**Files:**
- Create: `sisda_app/lib/sync/failure_classifier.dart`
- Create: `sisda_app/lib/sync/backoff.dart`
- Create: `sisda_app/test/sync/failure_classifier_test.dart`
- Create: `sisda_app/test/sync/backoff_test.dart`

**Interfaces:**
- Consumes: `FailureBucket` (Task 2).
- Produces:
  - `FailureBucket? classifyStatus(int status)` — returns `null` for 2xx (success, no bucket). No Flutter.
  - `FailureBucket classifyException(Object error)` — any transport error → `FailureBucket.transient`.
  - `DateTime nextRetryAt({required int attempts, required DateTime now, Duration cap})` — exponential backoff, capped (~5 min).

- [ ] **Step 1: Write the failing tests**

Create `sisda_app/test/sync/failure_classifier_test.dart`:

```dart
import 'package:flutter_test/flutter_test.dart';
import 'package:sisda_app/models/sync_status.dart';
import 'package:sisda_app/sync/failure_classifier.dart';

void main() {
  test('2xx is success — no bucket', () {
    expect(classifyStatus(200), isNull);
    expect(classifyStatus(201), isNull);
  });

  test('401 is auth', () {
    expect(classifyStatus(401), FailureBucket.auth);
  });

  test('403/409/422 are permanent', () {
    expect(classifyStatus(403), FailureBucket.permanent);
    expect(classifyStatus(409), FailureBucket.permanent);
    expect(classifyStatus(422), FailureBucket.permanent);
  });

  test('429 is TRANSIENT, not permanent — the one 4xx that must retry', () {
    expect(classifyStatus(429), FailureBucket.transient);
  });

  test('5xx is transient', () {
    expect(classifyStatus(500), FailureBucket.transient);
    expect(classifyStatus(503), FailureBucket.transient);
  });

  test('any transport exception is transient', () {
    expect(classifyException(Exception('SocketException: no signal')),
        FailureBucket.transient);
  });
}
```

Create `sisda_app/test/sync/backoff_test.dart`:

```dart
import 'package:flutter_test/flutter_test.dart';
import 'package:sisda_app/sync/backoff.dart';

void main() {
  final now = DateTime.utc(2026, 7, 18, 10, 0);

  test('backoff grows with attempts', () {
    final a1 = nextRetryAt(attempts: 1, now: now);
    final a2 = nextRetryAt(attempts: 2, now: now);
    final a3 = nextRetryAt(attempts: 3, now: now);
    expect(a2.isAfter(a1), isTrue);
    expect(a3.isAfter(a2), isTrue);
  });

  test('backoff is capped at ~5 minutes', () {
    final far = nextRetryAt(attempts: 20, now: now);
    expect(far.difference(now).inSeconds, lessThanOrEqualTo(300));
  });
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `flutter test test/sync/`
Expected: FAIL — files missing.

- [ ] **Step 3: Write the implementations**

Create `sisda_app/lib/sync/failure_classifier.dart`:

```dart
import '../models/sync_status.dart';

/// Maps an HTTP status to a retry bucket, per the API contract's
/// status→bucket table. Returns null for 2xx (success — delete the draft).
///
/// The load-bearing case: 429 is TRANSIENT. It is the only 4xx that must be
/// retried; classifying it permanent would strand a valid submission in
/// Perlu Perhatian forever.
FailureBucket? classifyStatus(int status) {
  if (status >= 200 && status < 300) return null;
  if (status == 401) return FailureBucket.auth;
  if (status == 429) return FailureBucket.transient;
  if (status >= 500) return FailureBucket.transient;
  // 403 Parlimen, 409 duplicate/source, 422 validation — and any other 4xx —
  // are permanent: retrying will never help.
  return FailureBucket.permanent;
}

/// Any transport-level error (no HTTP response: timeout, socket, DNS) is
/// transient — normal life in the field.
FailureBucket classifyException(Object error) => FailureBucket.transient;
```

Create `sisda_app/lib/sync/backoff.dart`:

```dart
/// Exponential backoff for a transient retry: 2^attempts seconds, capped.
/// Pure — pass `now` in (never call DateTime.now() here, for testability).
DateTime nextRetryAt({
  required int attempts,
  required DateTime now,
  Duration cap = const Duration(minutes: 5),
}) {
  final seconds = (1 << attempts.clamp(0, 30)); // 2^attempts, guarded
  final delay = Duration(seconds: seconds);
  return now.add(delay > cap ? cap : delay);
}
```

- [ ] **Step 4: Run to verify they pass**

Run: `flutter test test/sync/`
Expected: PASS — 8 tests.

- [ ] **Step 5: Commit**

```bash
cd sisda_app
git add lib/sync/failure_classifier.dart lib/sync/backoff.dart test/sync/failure_classifier_test.dart test/sync/backoff_test.dart
git commit -m "Klien mudah alih: pengelas kegagalan (429=sementara) + backoff eksponen"
```

---

### Task 5: Drift local DB — drafts only

The `data/local/` layer. **Drafts only — this is the privacy line.** No voter table exists, by design. Drift generates code via `build_runner`.

**Files:**
- Create: `sisda_app/lib/data/local/database.dart`
- Create: `sisda_app/test/data/database_test.dart`
- Generated: `sisda_app/lib/data/local/database.g.dart` (via build_runner — do NOT hand-edit)

**Interfaces:**
- Consumes: `CulaanDraft`, `SyncStatus` (Task 2).
- Produces: `abstract class DraftStore` with:
  - `Future<void> upsertDraft(CulaanDraft draft)`
  - `Future<CulaanDraft?> getDraft(String idempotencyKey)`
  - `Future<List<CulaanDraft>> queuedReadyToSync(DateTime now)` — status `queued` AND (`nextRetryAt` null OR ≤ now)
  - `Future<void> deleteDraft(String idempotencyKey)`
  - `Stream<List<CulaanDraft>> watchAll()`
  - `class AppDatabase extends ... implements DraftStore` with a real constructor and `AppDatabase.forTesting(QueryExecutor)` using an in-memory executor.

- [ ] **Step 1: Write the Drift table + DAO (no test yet — needs codegen)**

Create `sisda_app/lib/data/local/database.dart`:

```dart
import 'dart:convert';
import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import '../../models/culaan_draft.dart';
import '../../models/sync_status.dart';

part 'database.g.dart';

/// The ONLY table on device. Stores Culaan drafts — never voter PII.
/// `fieldsJson` holds the culaan payload as JSON; `status` is the SyncStatus
/// index. `idempotencyKey` is the primary key (client-generated UUID).
class Drafts extends Table {
  TextColumn get idempotencyKey => text()();
  TextColumn get fieldsJson => text().withDefault(const Constant('{}'))();
  BoolColumn get hasSumbangan => boolean().withDefault(const Constant(false))();
  IntColumn get lockedSourceId => integer().nullable()();
  IntColumn get status => integer().withDefault(const Constant(0))(); // SyncStatus.index
  IntColumn get attempts => integer().withDefault(const Constant(0))();
  DateTimeColumn get nextRetryAt => dateTime().nullable()();
  TextColumn get failureReason => text().nullable()();
  DateTimeColumn get createdAt => dateTime()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {idempotencyKey};
}

/// Abstract seam so the sync engine depends on an interface, not Drift.
abstract class DraftStore {
  Future<void> upsertDraft(CulaanDraft draft);
  Future<CulaanDraft?> getDraft(String idempotencyKey);
  Future<List<CulaanDraft>> queuedReadyToSync(DateTime now);
  Future<void> deleteDraft(String idempotencyKey);
  Stream<List<CulaanDraft>> watchAll();
}

@DriftDatabase(tables: [Drafts])
class AppDatabase extends _$AppDatabase implements DraftStore {
  AppDatabase(super.e);
  AppDatabase.forTesting() : super(NativeDatabase.memory());

  @override
  int get schemaVersion => 1;

  CulaanDraft _toModel(Draft row) => CulaanDraft(
        idempotencyKey: row.idempotencyKey,
        fields: (jsonDecode(row.fieldsJson) as Map).cast<String, dynamic>(),
        hasSumbangan: row.hasSumbangan,
        lockedSourceId: row.lockedSourceId,
        status: SyncStatus.values[row.status],
        attempts: row.attempts,
        nextRetryAt: row.nextRetryAt,
        failureReason: row.failureReason,
        createdAt: row.createdAt,
        updatedAt: row.updatedAt,
      );

  DraftsCompanion _toRow(CulaanDraft d) => DraftsCompanion(
        idempotencyKey: Value(d.idempotencyKey),
        fieldsJson: Value(jsonEncode(d.fields)),
        hasSumbangan: Value(d.hasSumbangan),
        lockedSourceId: Value(d.lockedSourceId),
        status: Value(d.status.index),
        attempts: Value(d.attempts),
        nextRetryAt: Value(d.nextRetryAt),
        failureReason: Value(d.failureReason),
        createdAt: Value(d.createdAt),
        updatedAt: Value(d.updatedAt),
      );

  @override
  Future<void> upsertDraft(CulaanDraft draft) =>
      into(drafts).insertOnConflictUpdate(_toRow(draft));

  @override
  Future<CulaanDraft?> getDraft(String key) async {
    final row = await (select(drafts)..where((t) => t.idempotencyKey.equals(key)))
        .getSingleOrNull();
    return row == null ? null : _toModel(row);
  }

  @override
  Future<List<CulaanDraft>> queuedReadyToSync(DateTime now) async {
    final rows = await (select(drafts)
          ..where((t) =>
              t.status.equals(SyncStatus.queued.index) &
              (t.nextRetryAt.isNull() | t.nextRetryAt.isSmallerOrEqualValue(now))))
        .get();
    return rows.map(_toModel).toList();
  }

  @override
  Future<void> deleteDraft(String key) =>
      (delete(drafts)..where((t) => t.idempotencyKey.equals(key))).go();

  @override
  Stream<List<CulaanDraft>> watchAll() =>
      select(drafts).watch().map((rows) => rows.map(_toModel).toList());
}
```

- [ ] **Step 2: Generate the Drift code**

Run (from `sisda_app/`): `dart run build_runner build --delete-conflicting-outputs`
Expected: creates `lib/data/local/database.g.dart`, no errors. If codegen fails, read the error — usually a column-type mismatch. Do not hand-write `.g.dart`.

- [ ] **Step 3: Write the DAO test**

Create `sisda_app/test/data/database_test.dart`:

```dart
import 'package:flutter_test/flutter_test.dart';
import 'package:sisda_app/data/local/database.dart';
import 'package:sisda_app/models/culaan_draft.dart';
import 'package:sisda_app/models/sync_status.dart';

void main() {
  late AppDatabase db;
  final now = DateTime.utc(2026, 7, 18, 10, 0);

  setUp(() => db = AppDatabase.forTesting());
  tearDown(() => db.close());

  test('upsert then get round-trips fields and status', () async {
    final d = CulaanDraft.newDraft(idempotencyKey: 'k1', now: now)
        .copyWith(fields: {'nama': 'Ahmad'}, status: SyncStatus.queued);
    await db.upsertDraft(d);
    final got = await db.getDraft('k1');
    expect(got!.fields['nama'], 'Ahmad');
    expect(got.status, SyncStatus.queued);
  });

  test('queuedReadyToSync excludes future nextRetryAt and non-queued', () async {
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'ready', now: now)
        .copyWith(status: SyncStatus.queued));
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'later', now: now)
        .copyWith(status: SyncStatus.queued, nextRetryAt: now.add(const Duration(minutes: 10))));
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'editing', now: now)
        .copyWith(status: SyncStatus.draft));

    final ready = await db.queuedReadyToSync(now);
    expect(ready.map((d) => d.idempotencyKey), ['ready']);
  });

  test('deleteDraft removes it', () async {
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'x', now: now));
    await db.deleteDraft('x');
    expect(await db.getDraft('x'), isNull);
  });
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `flutter test test/data/database_test.dart`
Expected: PASS — 3 tests.

- [ ] **Step 5: Commit**

```bash
cd sisda_app
git add lib/data/local/database.dart lib/data/local/database.g.dart test/data/database_test.dart
git commit -m "Klien mudah alih: Drift DB — jadual drafts SAHAJA (garis privasi)"
```

> **Note:** confirm `database.g.dart` is committed (not gitignored). Drift generated code is committed in this repo so CI/other machines build without codegen. If `sisda_app/.gitignore` excludes `*.g.dart`, add an exception for this file and note it.

---

### Task 6: The sync engine

The star. Pure logic over the `DraftStore` (Task 5) and a `CulaanApi` interface (implemented for real in Task 7, faked here). Drains queued drafts, classifies each result, applies the bucket's action. No Flutter import. Tested exhaustively with fakes — including the lost-response idempotency case that is the whole reason the UUID exists.

**Files:**
- Create: `sisda_app/lib/sync/sync_engine.dart`
- Create: `sisda_app/test/sync/sync_engine_test.dart`

**Interfaces:**
- Consumes: `DraftStore` (Task 5), `CulaanDraft`/`SyncStatus`/`FailureBucket` (Task 2), `classifyStatus`/`classifyException` (Task 4), `nextRetryAt` (Task 4), `ApiException` (Task 3).
- Produces:
  - `abstract class CulaanApi { Future<void> submitCulaan(Map<String, dynamic> payload); }` — throws `ApiException` on non-2xx (Task 7's `MobileApi` implements it). A 2xx (including the idempotent replay that returns the original 201) returns normally.
  - `class SyncEngine { SyncEngine(this._store, this._api); Future<SyncOutcome> syncNow({required DateTime now}); }`
  - `class SyncOutcome { int synced; int stillQueued; int failed; bool needsReauth; }`

- [ ] **Step 1: Write the failing test**

Create `sisda_app/test/sync/sync_engine_test.dart`:

```dart
import 'package:flutter_test/flutter_test.dart';
import 'package:sisda_app/data/local/database.dart';
import 'package:sisda_app/models/api_result.dart';
import 'package:sisda_app/models/culaan_draft.dart';
import 'package:sisda_app/models/sync_status.dart';
import 'package:sisda_app/sync/sync_engine.dart';

/// Fake API whose behaviour per idempotency_key is scripted by the test.
class FakeCulaanApi implements CulaanApi {
  final List<Map<String, dynamic>> received = [];
  Object? Function(Map<String, dynamic> payload)? onSubmit;

  @override
  Future<void> submitCulaan(Map<String, dynamic> payload) async {
    received.add(payload);
    final result = onSubmit?.call(payload);
    if (result is ApiException) throw result;
    if (result is Exception) throw result;
    // null → success (2xx)
  }
}

void main() {
  late AppDatabase db;
  late FakeCulaanApi api;
  late SyncEngine engine;
  final now = DateTime.utc(2026, 7, 18, 10, 0);

  setUp(() {
    db = AppDatabase.forTesting();
    api = FakeCulaanApi();
    engine = SyncEngine(db, api);
  });
  tearDown(() => db.close());

  Future<void> queue(String key) => db.upsertDraft(
        CulaanDraft.newDraft(idempotencyKey: key, now: now)
            .copyWith(fields: {'no_ic': '800101015555'}, status: SyncStatus.queued),
      );

  test('success deletes the draft locally', () async {
    await queue('k1');
    api.onSubmit = (_) => null; // 2xx
    final out = await engine.syncNow(now: now);
    expect(out.synced, 1);
    expect(await db.getDraft('k1'), isNull);
  });

  test('transient (429) keeps the draft queued, increments attempts, schedules retry', () async {
    await queue('k1');
    api.onSubmit = (_) => const ApiException(status: 429, errors: {});
    final out = await engine.syncNow(now: now);
    expect(out.stillQueued, 1);
    final d = await db.getDraft('k1');
    expect(d!.status, SyncStatus.queued);
    expect(d.attempts, 1);
    expect(d.nextRetryAt, isNotNull);
  });

  test('transient network error is also retried, not failed', () async {
    await queue('k1');
    api.onSubmit = (_) => Exception('SocketException');
    final out = await engine.syncNow(now: now);
    expect(out.stillQueued, 1);
    expect((await db.getDraft('k1'))!.status, SyncStatus.queued);
  });

  test('permanent (403) moves the draft to failed with the BM reason', () async {
    await queue('k1');
    api.onSubmit = (_) => const ApiException(
        status: 403, errors: {'parlimen': ['Rekod ini di luar Parlimen anda.']});
    final out = await engine.syncNow(now: now);
    expect(out.failed, 1);
    final d = await db.getDraft('k1');
    expect(d!.status, SyncStatus.failed);
    expect(d.failureReason, 'Rekod ini di luar Parlimen anda.');
  });

  test('auth (401) keeps the draft queued and flags needsReauth', () async {
    await queue('k1');
    api.onSubmit = (_) => const ApiException(status: 401, errors: {});
    final out = await engine.syncNow(now: now);
    expect(out.needsReauth, isTrue);
    expect((await db.getDraft('k1'))!.status, SyncStatus.queued);
  });

  test('LOST-RESPONSE RETRY: the same key is re-sent unchanged; server replay returns 2xx; draft deleted', () async {
    // First attempt "succeeded" server-side but the response was lost, so the
    // draft is still queued and retried. The key must be identical, and the
    // server's idempotent replay returns 2xx → we delete. No duplicate.
    await queue('k1');
    api.onSubmit = (_) => null;
    await engine.syncNow(now: now);
    expect(api.received.single['idempotency_key'], 'k1');
    expect(await db.getDraft('k1'), isNull);
  });

  test('a draft whose nextRetryAt is in the future is skipped', () async {
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'later', now: now)
        .copyWith(status: SyncStatus.queued, nextRetryAt: now.add(const Duration(minutes: 5))));
    final out = await engine.syncNow(now: now);
    expect(out.synced, 0);
    expect(api.received, isEmpty);
  });
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `flutter test test/sync/sync_engine_test.dart`
Expected: FAIL — `sync_engine.dart` missing.

- [ ] **Step 3: Write the engine**

Create `sisda_app/lib/sync/sync_engine.dart`:

```dart
import '../data/local/database.dart';
import '../models/api_result.dart';
import '../models/culaan_draft.dart';
import '../models/sync_status.dart';
import 'backoff.dart';
import 'failure_classifier.dart';

/// The write side of the API, abstracted so the engine is Flutter-free and
/// unit-testable. Task 7's MobileApi implements it. A 2xx (including the
/// server's idempotent replay of an already-seen key) returns normally;
/// anything else throws ApiException.
abstract class CulaanApi {
  Future<void> submitCulaan(Map<String, dynamic> payload);
}

class SyncOutcome {
  int synced = 0;
  int stillQueued = 0;
  int failed = 0;
  bool needsReauth = false;
}

/// Drains queued Culaan drafts. Pure logic over DraftStore + CulaanApi.
/// NO Flutter import — the Riverpod layer (Task 8) wires the triggers.
class SyncEngine {
  final DraftStore _store;
  final CulaanApi _api;

  SyncEngine(this._store, this._api);

  Future<SyncOutcome> syncNow({required DateTime now}) async {
    final outcome = SyncOutcome();
    final ready = await _store.queuedReadyToSync(now);

    for (final draft in ready) {
      try {
        // The payload carries the SAME idempotency_key every attempt — this
        // is what makes a lost-response retry safe (server returns the
        // original record instead of writing a duplicate).
        await _api.submitCulaan(draft.toApiPayload());
        await _store.deleteDraft(draft.idempotencyKey); // 2xx → done
        outcome.synced++;
      } on ApiException catch (e) {
        final bucket = e.status == null
            ? classifyException(e)
            : (classifyStatus(e.status!) ?? FailureBucket.transient);
        _applyBucket(draft, bucket, e.firstError(), now, outcome);
        await _persist(draft, bucket, e.firstError(), now);
      } catch (e) {
        // Transport error with no HTTP status → transient.
        _applyBucket(draft, FailureBucket.transient, null, now, outcome);
        await _persist(draft, FailureBucket.transient, null, now);
      }
    }
    return outcome;
  }

  void _applyBucket(CulaanDraft d, FailureBucket bucket, String? reason,
      DateTime now, SyncOutcome out) {
    switch (bucket) {
      case FailureBucket.transient:
        out.stillQueued++;
      case FailureBucket.auth:
        out.needsReauth = true;
        out.stillQueued++;
      case FailureBucket.permanent:
        out.failed++;
    }
  }

  Future<void> _persist(CulaanDraft d, FailureBucket bucket, String? reason,
      DateTime now) async {
    switch (bucket) {
      case FailureBucket.transient:
        final attempts = d.attempts + 1;
        await _store.upsertDraft(d.copyWith(
          status: SyncStatus.queued,
          attempts: attempts,
          nextRetryAt: nextRetryAt(attempts: attempts, now: now),
          updatedAt: now,
        ));
      case FailureBucket.auth:
        // Stay queued, do NOT count as an attempt or back off — the retry is
        // gated on re-login, not on time. Draft survives logout.
        await _store.upsertDraft(d.copyWith(status: SyncStatus.queued, updatedAt: now));
      case FailureBucket.permanent:
        await _store.upsertDraft(d.copyWith(
          status: SyncStatus.failed,
          failureReason: reason,
          updatedAt: now,
        ));
    }
  }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `flutter test test/sync/sync_engine_test.dart`
Expected: PASS — 7 tests. If the permanent-failure test can't clear/keep `nextRetryAt` or set `failureReason` via `copyWith`, this is where the `copyWith` nullable limitation (Task 2 note) surfaces — fix `copyWith` to accept those nulls (sentinel wrapper) and re-run.

- [ ] **Step 5: Commit**

```bash
cd sisda_app
git add lib/sync/sync_engine.dart test/sync/sync_engine_test.dart lib/models/culaan_draft.dart
git commit -m "Klien mudah alih: enjin sync — baldi kegagalan + idempotency retry"
```

---

### Task 7: Typed `MobileApi` client

The `data/remote/` layer. One method per endpoint the native V1 flow uses, each returning a typed model or throwing `ApiException`. The hard part is the **envelope variance** (contract §"Konvensyen respons"): `{success}`-wrapped vs bare arrays vs bare `{message}` (English 401/429/register). Injectable `http.Client` for a fake-transport test.

**Files:**
- Create: `sisda_app/lib/data/remote/api_config.dart`
- Create: `sisda_app/lib/data/remote/mobile_api.dart`
- Create: `sisda_app/test/data/mobile_api_test.dart`

**Interfaces:**
- Consumes: `Voter`, `ApiException` (Task 3), `CulaanApi` (Task 6).
- Produces: `class MobileApi implements CulaanApi` with `MobileApi({required http.Client client, required ApiConfig config})` and methods:
  - `Future<LoginResult> login(String telephone, String password)`
  - `Future<void> register(Map<String, dynamic> body)`
  - `Future<String> forgotPassword(String telephone)` (returns the BM success message)
  - `Future<List<Map<String, dynamic>>> negeriList()` / `bandarByNegeri(int)` / `kadunByBandar(int)` (bare arrays)
  - `Future<List<Voter>> searchVoters(String q)`
  - `Future<Voter> showVoter(String ic)`
  - `Future<Map<String, dynamic>> culaanOptions()`
  - `Future<List<Map<String, dynamic>>> culaanMine()`
  - `Future<void> submitCulaan(Map<String, dynamic> payload)` (the `CulaanApi` method)
  - `Future<void> logout()`
  - `class LoginResult { String token; Map<String, dynamic> user; }`
  - `class ApiConfig { String baseUrl; String? token; }`

- [ ] **Step 1: Write the failing test**

Create `sisda_app/test/data/mobile_api_test.dart`:

```dart
import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:sisda_app/data/remote/api_config.dart';
import 'package:sisda_app/data/remote/mobile_api.dart';
import 'package:sisda_app/models/api_result.dart';

MobileApi apiReturning(int status, String body, {ApiConfig? cfg}) {
  final client = MockClient((req) async => http.Response(body, status,
      headers: {'content-type': 'application/json'}));
  return MobileApi(client: client, config: cfg ?? ApiConfig(baseUrl: 'http://x'));
}

void main() {
  test('login parses the {success, token, user} envelope', () async {
    final api = apiReturning(200,
        jsonEncode({'success': true, 'token': '1|abc', 'user': {'id': 1, 'role': 'user'}}));
    final r = await api.login('0123', 'pw');
    expect(r.token, '1|abc');
    expect(r.user['role'], 'user');
  });

  test('login 422 throws ApiException with the BM field error', () async {
    final api = apiReturning(422, jsonEncode({
      'success': false,
      'errors': {'telephone': ['Nombor telefon atau kata laluan tidak sah.']}
    }));
    expect(() => api.login('0123', 'pw'),
        throwsA(isA<ApiException>().having((e) => e.firstError(), 'msg',
            'Nombor telefon atau kata laluan tidak sah.')));
  });

  test('401 middleware ({message} only, English) throws ApiException status 401, no leaked message', () async {
    final api = apiReturning(401, jsonEncode({'message': 'Unauthenticated.'}));
    try {
      await api.culaanMine();
      fail('expected throw');
    } on ApiException catch (e) {
      expect(e.status, 401);
      // firstError falls back to BM, never surfaces "Unauthenticated."
      expect(e.firstError(), isNot(contains('Unauthenticated')));
    }
  });

  test('negeriList parses a BARE JSON array', () async {
    final api = apiReturning(200, jsonEncode([
      {'id': 1, 'nama': 'JOHOR'}, {'id': 2, 'nama': 'KEDAH'}
    ]));
    final list = await api.negeriList();
    expect(list.length, 2);
    expect(list.first['nama'], 'JOHOR');
  });

  test('searchVoters parses {success, voters:[...]} into Voter list', () async {
    final api = apiReturning(200, jsonEncode({
      'success': true,
      'voters': [{'id': 5, 'nama': 'Ahmad', 'no_ic': '****'}]
    }));
    final voters = await api.searchVoters('Ahmad');
    expect(voters.single.nama, 'Ahmad');
    expect(voters.single.field('no_ic'), '****');
  });

  test('submitCulaan 201 returns normally; 403 throws permanent-classifiable ApiException', () async {
    await apiReturning(201, jsonEncode({'success': true, 'culaan': {'id': 9, 'no_ic': '****'}}))
        .submitCulaan({'idempotency_key': 'k'});
    final api403 = apiReturning(403,
        jsonEncode({'success': false, 'errors': {'parlimen': ['Rekod ini di luar Parlimen anda.']}}));
    expect(() => api403.submitCulaan({'idempotency_key': 'k'}),
        throwsA(isA<ApiException>().having((e) => e.status, 'status', 403)));
  });

  test('sends Bearer token when configured', () async {
    String? seenAuth;
    final client = MockClient((req) async {
      seenAuth = req.headers['Authorization'];
      return http.Response(jsonEncode({'success': true, 'voters': []}), 200,
          headers: {'content-type': 'application/json'});
    });
    final api = MobileApi(client: client, config: ApiConfig(baseUrl: 'http://x', token: 'TKN'));
    await api.searchVoters('abc');
    expect(seenAuth, 'Bearer TKN');
  });
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `flutter test test/data/mobile_api_test.dart`
Expected: FAIL — files missing.

- [ ] **Step 3: Write `ApiConfig` and the client**

Create `sisda_app/lib/data/remote/api_config.dart`:

```dart
/// Holds the base URL and the current Sanctum token. Mutable token so login
/// can set it and logout clear it. Replaces the static fields on the old
/// ApiService.
class ApiConfig {
  final String baseUrl;
  String? token;
  ApiConfig({required this.baseUrl, this.token});
}
```

Create `sisda_app/lib/data/remote/mobile_api.dart`:

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import '../../models/api_result.dart';
import '../../models/voter.dart';
import '../../sync/sync_engine.dart' show CulaanApi;
import 'api_config.dart';

class LoginResult {
  final String token;
  final Map<String, dynamic> user;
  const LoginResult(this.token, this.user);
}

/// Typed client for /api/mobile/*. Every method returns a model or throws
/// ApiException. Handles the contract's envelope variance:
///  - most endpoints: {"success": bool, ...}
///  - geography: bare JSON arrays
///  - 401/429 middleware, register/login-missing-field: bare {"message":...} (English)
/// The client reads errors.<field> and NEVER surfaces `message` to users.
class MobileApi implements CulaanApi {
  final http.Client _client;
  final ApiConfig _config;

  MobileApi({required http.Client client, required ApiConfig config})
      : _client = client,
        _config = config;

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (_config.token != null) 'Authorization': 'Bearer ${_config.token}',
      };

  Uri _uri(String path, [Map<String, dynamic>? query]) =>
      Uri.parse('${_config.baseUrl}$path').replace(
          queryParameters: query?.map((k, v) => MapEntry(k, '$v')));

  /// Decodes a response body, throwing ApiException for any non-2xx.
  /// Parses errors.<field> when present; otherwise an empty errors map (so
  /// ApiException.firstError falls back to BM).
  dynamic _decode(http.Response res) {
    final body = res.body.isEmpty ? null : jsonDecode(res.body);
    if (res.statusCode >= 200 && res.statusCode < 300) return body;

    final errors = <String, List<String>>{};
    if (body is Map && body['errors'] is Map) {
      (body['errors'] as Map).forEach((k, v) {
        errors['$k'] = (v as List).map((e) => '$e').toList();
      });
    }
    throw ApiException(
      status: res.statusCode,
      errors: errors,
      rawMessage: body is Map ? body['message'] as String? : null,
    );
  }

  Future<dynamic> _get(String path, [Map<String, dynamic>? q]) async {
    try {
      return _decode(await _client.get(_uri(path, q), headers: _headers));
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException(status: null, errors: {}, rawMessage: '$e');
    }
  }

  Future<dynamic> _post(String path, Map<String, dynamic> body) async {
    try {
      return _decode(await _client.post(_uri(path), headers: _headers, body: jsonEncode(body)));
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException(status: null, errors: {}, rawMessage: '$e');
    }
  }

  Future<LoginResult> login(String telephone, String password) async {
    final body = await _post('/api/mobile/login', {'telephone': telephone, 'password': password});
    return LoginResult(body['token'] as String, (body['user'] as Map).cast<String, dynamic>());
  }

  Future<void> register(Map<String, dynamic> body) => _post('/api/mobile/register', body);

  Future<String> forgotPassword(String telephone) async {
    final body = await _post('/api/mobile/forgot-password', {'telephone': telephone});
    // Contract §3: WhatsApp-send failure is a 200 with success:false — surface it.
    if (body is Map && body['success'] == false) {
      throw ApiException(status: 200, errors: {'telephone': ['${body['message']}']});
    }
    return (body['message'] ?? 'Kata laluan baharu telah dihantar.') as String;
  }

  Future<List<Map<String, dynamic>>> negeriList() async =>
      ((await _get('/api/mobile/negeri-list')) as List).map((e) => (e as Map).cast<String, dynamic>()).toList();

  Future<List<Map<String, dynamic>>> bandarByNegeri(int negeriId) async =>
      ((await _get('/api/mobile/bandar-by-negeri', {'negeri_id': negeriId})) as List)
          .map((e) => (e as Map).cast<String, dynamic>()).toList();

  Future<List<Map<String, dynamic>>> kadunByBandar(int bandarId) async =>
      ((await _get('/api/mobile/kadun-by-bandar', {'bandar_id': bandarId})) as List)
          .map((e) => (e as Map).cast<String, dynamic>()).toList();

  Future<List<Voter>> searchVoters(String q) async {
    final body = await _get('/api/mobile/voters/search', {'q': q});
    return ((body['voters'] as List)).map((e) => Voter.fromJson((e as Map).cast<String, dynamic>())).toList();
  }

  Future<Voter> showVoter(String ic) async {
    final body = await _get('/api/mobile/voters/$ic');
    return Voter.fromJson((body['voter'] as Map).cast<String, dynamic>());
  }

  Future<Map<String, dynamic>> culaanOptions() async =>
      ((await _get('/api/mobile/culaan/options'))['options'] as Map).cast<String, dynamic>();

  Future<List<Map<String, dynamic>>> culaanMine() async =>
      (((await _get('/api/mobile/culaan/mine'))['culaan']) as List)
          .map((e) => (e as Map).cast<String, dynamic>()).toList();

  @override
  Future<void> submitCulaan(Map<String, dynamic> payload) => _post('/api/mobile/culaan', payload);

  Future<void> logout() => _post('/api/mobile/logout', {});
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `flutter test test/data/mobile_api_test.dart`
Expected: PASS — 7 tests.

- [ ] **Step 5: Commit**

```bash
cd sisda_app
git add lib/data/remote/api_config.dart lib/data/remote/mobile_api.dart test/data/mobile_api_test.dart
git commit -m "Klien mudah alih: MobileApi bertaip + pengendalian variasi envelope"
```

---

### Task 8: Riverpod wiring + auth refactor + delete the static ApiService

Wire everything into providers, refactor the existing auth screens to use a Riverpod `AuthController` instead of the static `ApiService`, add the connectivity-triggered sync, and delete `services/api_service.dart`. Deliverable: the app compiles and runs with the new foundation; the existing login/register/forgot/logout flows work through the new client; `flutter analyze` clean.

**Files:**
- Create: `sisda_app/lib/providers.dart`
- Create: `sisda_app/lib/features/auth/auth_controller.dart`
- Modify: `sisda_app/lib/screens/login_screen.dart`, `register_screen.dart`, `forgot_password_screen.dart`, `splash_screen.dart`, `home_screen.dart`, **`webview_screen.dart`** (swap `ApiService.*` for provider/controller calls; **keep the widget trees and the OCR flow**). All six screens reference `ApiService` today (verified) — every one must be migrated before the delete in Step 6, or `flutter analyze` fails on a dangling reference.
- Delete: `sisda_app/lib/services/api_service.dart`
- Create: `sisda_app/test/features/auth_controller_test.dart`

**Interfaces:**
- Consumes: everything from Tasks 5–7.
- Produces:
  - Providers: `apiConfigProvider`, `mobileApiProvider`, `appDatabaseProvider`, `syncEngineProvider`, `authControllerProvider`.
  - `class AuthController extends AsyncNotifier<AuthState>` with `login`, `register`, `forgotPassword`, `logout`, `restoreSession`; `AuthState { bool loggedIn; Map<String,dynamic>? user; }`. Persists the token via `shared_preferences` (as the old code did) and mirrors it into `ApiConfig.token`.

- [ ] **Step 1: Write the auth controller test (with a fake MobileApi via mocktail)**

Create `sisda_app/test/features/auth_controller_test.dart`:

```dart
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mocktail/mocktail.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sisda_app/data/remote/api_config.dart';
import 'package:sisda_app/data/remote/mobile_api.dart';
import 'package:sisda_app/models/api_result.dart';
import 'package:sisda_app/providers.dart';
import 'package:sisda_app/features/auth/auth_controller.dart';

class MockMobileApi extends Mock implements MobileApi {}

void main() {
  late MockMobileApi api;
  late ApiConfig cfg;

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    api = MockMobileApi();
    cfg = ApiConfig(baseUrl: 'http://x');
  });

  ProviderContainer container() => ProviderContainer(overrides: [
        mobileApiProvider.overrideWithValue(api),
        apiConfigProvider.overrideWithValue(cfg),
      ]);

  test('login success sets loggedIn and stores the token into ApiConfig', () async {
    when(() => api.login(any(), any()))
        .thenAnswer((_) async => const LoginResult('1|abc', {'id': 1, 'role': 'user'}));
    final c = container();
    await c.read(authControllerProvider.notifier).login('0123456789', 'pw');
    final state = c.read(authControllerProvider).value!;
    expect(state.loggedIn, isTrue);
    expect(cfg.token, '1|abc');
  });

  test('login 422 surfaces the BM error and stays logged out', () async {
    when(() => api.login(any(), any())).thenThrow(const ApiException(
        status: 422, errors: {'telephone': ['Nombor telefon atau kata laluan tidak sah.']}));
    final c = container();
    await c.read(authControllerProvider.notifier).login('0123456789', 'pw');
    final state = c.read(authControllerProvider).value!;
    expect(state.loggedIn, isFalse);
    expect(state.errorMessage, 'Nombor telefon atau kata laluan tidak sah.');
    expect(cfg.token, isNull);
  });
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `flutter test test/features/auth_controller_test.dart`
Expected: FAIL — files missing.

- [ ] **Step 3: Write the providers**

Create `sisda_app/lib/providers.dart`:

```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'data/local/database.dart';
import 'data/remote/api_config.dart';
import 'data/remote/mobile_api.dart';
import 'sync/sync_engine.dart';
import 'features/auth/auth_controller.dart';

/// Production host — the URL the old static ApiService used.
final apiConfigProvider = Provider<ApiConfig>(
    (ref) => ApiConfig(baseUrl: 'https://sistemdatapengundi.com'));

final httpClientProvider = Provider<http.Client>((ref) {
  final c = http.Client();
  ref.onDispose(c.close);
  return c;
});

final mobileApiProvider = Provider<MobileApi>((ref) => MobileApi(
      client: ref.watch(httpClientProvider),
      config: ref.watch(apiConfigProvider),
    ));

/// Real on-device Drift DB. Overridden in tests with AppDatabase.forTesting().
final appDatabaseProvider = Provider<AppDatabase>((ref) {
  final db = AppDatabase(openAppDatabaseConnection()); // see database.dart helper
  ref.onDispose(db.close);
  return db;
});

final syncEngineProvider = Provider<SyncEngine>(
    (ref) => SyncEngine(ref.watch(appDatabaseProvider), ref.watch(mobileApiProvider)));

final authControllerProvider =
    AsyncNotifierProvider<AuthController, AuthState>(AuthController.new);
```

Add an `openAppDatabaseConnection()` helper to `database.dart` that opens a `NativeDatabase` in the app-documents dir via `path_provider` (lazy, so tests that use `forTesting()` never touch the filesystem). The connectivity trigger (a `connectivity_plus` listener that calls `ref.read(syncEngineProvider).syncNow(now: DateTime.now())` when connectivity returns) is wired in `main.dart` or a small `ConsumerStatefulWidget` root — **this is the one place `DateTime.now()` is allowed**; the engine itself stays pure. Keep that wiring minimal here; the richer triggers (foreground, pull-to-refresh, post-Hantar) belong to Plan 2b's screens.

- [ ] **Step 4: Write the AuthController**

Create `sisda_app/lib/features/auth/auth_controller.dart`:

```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../data/remote/api_config.dart';
import '../../models/api_result.dart';
import '../../providers.dart';

class AuthState {
  final bool loggedIn;
  final Map<String, dynamic>? user;
  final String? errorMessage;
  const AuthState({this.loggedIn = false, this.user, this.errorMessage});
}

const _tokenKey = 'auth_token'; // same key the old ApiService used — sessions survive upgrade

class AuthController extends AsyncNotifier<AuthState> {
  @override
  Future<AuthState> build() async => _restore();

  Future<AuthState> _restore() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(_tokenKey);
    if (token == null) return const AuthState(loggedIn: false);
    ref.read(apiConfigProvider).token = token;
    return const AuthState(loggedIn: true);
  }

  Future<void> login(String telephone, String password) async {
    state = const AsyncLoading();
    try {
      final result = await ref.read(mobileApiProvider).login(telephone, password);
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_tokenKey, result.token);
      ref.read(apiConfigProvider).token = result.token;
      state = AsyncData(AuthState(loggedIn: true, user: result.user));
    } on ApiException catch (e) {
      state = AsyncData(AuthState(loggedIn: false, errorMessage: e.firstError()));
    }
  }

  Future<void> register(Map<String, dynamic> body) async {
    try {
      await ref.read(mobileApiProvider).register(body);
      state = const AsyncData(AuthState(loggedIn: false)); // pending approval, no auto-login
    } on ApiException catch (e) {
      state = AsyncData(AuthState(loggedIn: false, errorMessage: e.firstError()));
    }
  }

  Future<String?> forgotPassword(String telephone) async {
    try {
      return await ref.read(mobileApiProvider).forgotPassword(telephone);
    } on ApiException catch (e) {
      state = AsyncData(AuthState(loggedIn: false, errorMessage: e.firstError()));
      return null;
    }
  }

  Future<void> logout() async {
    try {
      await ref.read(mobileApiProvider).logout();
    } on ApiException {
      // logout best-effort; clear locally regardless
    }
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    ref.read(apiConfigProvider).token = null;
    state = const AsyncData(AuthState(loggedIn: false));
  }
}
```

> **`register` semantics** match the contract (§2): a new account is `pending`, no token/user returned, so there is no auto-login — the screen shows a "menunggu kelulusan" message. Keep the existing register screen's success copy.

- [ ] **Step 5: Refactor the screens (one repeated pattern across six files)**

Each screen currently calls the static `ApiService`. The change is identical in shape everywhere: make the widget a `ConsumerStatefulWidget` (or `ConsumerWidget` if stateless), then replace each static call with a `ref`-driven one. **Keep every widget tree, style, the OCR scan, and the WebView navigation exactly as-is — this is a data-layer swap, not a redesign.** Map each old call to its replacement:

| Old (`ApiService.*`) | New |
|---|---|
| `ApiService.login(tel, pw)` (login_screen) | `ref.read(authControllerProvider.notifier).login(tel, pw)`, then read `ref.read(authControllerProvider).value` for `loggedIn`/`errorMessage` |
| `ApiService.register({...})` (register_screen) | `ref.read(authControllerProvider.notifier).register({...})` |
| `ApiService.forgotPassword(tel)` (forgot_password_screen) | `ref.read(authControllerProvider.notifier).forgotPassword(tel)` (returns the BM success message or null) |
| `ApiService.getNegeriList()` / `getBandarByNegeri` / `getKadunByBandar` (register_screen) | `ref.read(mobileApiProvider).negeriList()` / `bandarByNegeri(id)` / `kadunByBandar(id)` |
| `ApiService.loadSavedSession()` (splash_screen) | `ref.read(authControllerProvider.notifier)` — session is restored by the notifier's `build()`; splash just reads `ref.watch(authControllerProvider)` and routes on `loggedIn` |
| `ApiService.logout()` (home_screen, webview_screen) | `ref.read(authControllerProvider.notifier).logout()` |
| `ApiService.getWebAuthToken()` (home/webview) | `ref.read(mobileApiProvider).?` — **note:** the web-auth-token call is not in `MobileApi`'s method list above. Add a `Future<String?> webAuthToken()` method to `MobileApi` (POST `/api/mobile/web-auth-token`, returns `body['token']`; contract §7 — bare `{token}`, no `success` key) as part of this step, since the WebView tail still needs it. |
| `ApiService.getWebAuthUrl(t, r)` (home/webview) | pure string builder — move it to a small helper on `MobileApi` or `ApiConfig` (`webAuthUrl(token, redirect)`); it has no HTTP call. |
| `ApiService.token` getter | `ref.read(apiConfigProvider).token` |

`ApiService.searchIc()` (the old `/api/voter/search-ic` call) has **no** caller in these screens' 2a paths — the scan→lookup rewiring to the real `/api/mobile/voters/*` endpoints is a Plan 2b screen concern. Do not wire it here; just ensure nothing references the deleted method.

Add the two web-auth helpers to `MobileApi` (`webAuthToken()`, `webAuthUrl()`) so `home_screen`/`webview_screen` compile without `ApiService`.

- [ ] **Step 6: Delete the static service and verify**

Delete `sisda_app/lib/services/api_service.dart`. Run `flutter analyze`.
Expected: `No issues found!` — no dangling `ApiService` references. If any remain, they were missed screen call-sites; fix them.

- [ ] **Step 7: Run the full test suite**

Run: `flutter test`
Expected: ALL green — every test from Tasks 2–8.

- [ ] **Step 8: Commit**

```bash
cd sisda_app
git add lib/providers.dart lib/features/auth/auth_controller.dart lib/screens/ test/features/auth_controller_test.dart
git rm lib/services/api_service.dart
git commit -m "Klien mudah alih: wiring Riverpod + AuthController; buang ApiService statik"
```

---

## Definition of Done

- [ ] `flutter test` (from `sisda_app/`) — all green.
- [ ] `flutter analyze` — `No issues found!`
- [ ] `sync/` and `models/` contain **no** `package:flutter/` import (verify: `grep -rL` / manual — the engine is device-free).
- [ ] `data/local/` persists **only** drafts — no voter table, no PII column anywhere in `database.dart`.
- [ ] The idempotency key is generated once (Task 2 `newDraft`) and never regenerated on retry (Task 6 re-sends `toApiPayload()` unchanged).
- [ ] The sync engine classifies 429 as transient (Task 4 test) and never surfaces an English `message` (Task 3/7 tests).
- [ ] The existing login/register/forgot/logout flows work through the new `MobileApi` (auth controller test + manual `flutter run` smoke).
- [ ] `ApiService` (static) is deleted; nothing references it.

## Scope boundary — deferred to Plan 2b

No new screens are built here. The search-first home, voter search/scan/detail, the checklist-hub Culaan form (7 sections, incl. the cascading `pekerjaan → jenis_pekerjaan` dependency), and the Perlu Perhatian inbox are Plan 2b, built on this foundation. Real-device / emulator E2E against the **deployed** mobile API also waits for 2b — which means the mobile API branch (`feature/mobile-app-user`) must be merged and deployed before 2b's E2E can run. This plan needs no server: every test uses a fake API.

## Note on branch & the parked API

Plan 2a touches only `sisda_app/` (the Flutter app) — no PHP. It can be built on a branch off `feature/mobile-app-user` (so the client and its API ultimately merge and deploy together) or off `main`; since all tests use fakes, either compiles and passes. Decide at execution time. If built off `feature/mobile-app-user`, note that branch carries the user's uncommitted `MasterDataController.php` WIP — keep it out of the way (stash or leave untouched; this plan never touches PHP).
