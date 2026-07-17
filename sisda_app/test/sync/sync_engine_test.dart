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
