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
