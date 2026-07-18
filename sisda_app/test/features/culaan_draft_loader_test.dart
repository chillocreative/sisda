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
    // poskod is sensitive + a prefill key: absent/empty on masked-create is
    // now seeded to the mask literal (see soft-lock test below), not skipped.
    expect(draft.fields['poskod'], '****');
    // mpkk is a prefill key but not sensitive: absent stays absent.
    expect(draft.fields.containsKey('mpkk'), isFalse);
  });

  test('prefill: masked-create seeds mask for absent sensitive fields '
      '(prevents permanent Hantar soft-lock)', () async {
    final voter = Voter.fromJson({
      'id': 88, 'nama': 'Siti binti Yusof',
      // umur (sensitive) is entirely omitted by the server.
      'bangsa': 'Melayu', // sensitive, but provided as a real-ish value.
      'keahlian_parti': '', // non-sensitive, empty -> must stay absent.
    });
    final draft = await loadOrCreateDraft(
        store: db, idGenerator: () => 'KEY-4', now: now, prefillVoter: voter);
    expect(draft.lockedSourceId, 88);
    expect(draft.fields['umur'], '****'); // seeded, prevents soft-lock
    expect(draft.fields['bangsa'], 'Melayu'); // provided value preserved
    expect(draft.fields.containsKey('keahlian_parti'), isFalse); // not sensitive
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
