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
