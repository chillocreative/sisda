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

/// Loads an existing draft by key (Betulkan flow), or creates a new one —
/// optionally seeded from a Voter (masked-create flow). Never fabricates a
/// value the caller couldn't see: masked voter fields ('****') carry through
/// verbatim, and empty ('') fields are skipped entirely.
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
