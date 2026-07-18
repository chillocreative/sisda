import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';
import '../../data/local/database.dart';
import '../../models/culaan_draft.dart';
import '../../models/voter.dart';
import 'culaan_form_spec.dart' show kSensitiveFields;

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
    // Masked-create only: a sensitive field the server omitted entirely
    // (absent or empty, rather than sent as '****') must still be seeded
    // with the mask literal. Otherwise the locked/read-only field displays
    // '****' in the UI forever while the completeness check sees it as
    // empty, soft-locking Hantar with no way for the agent to fill it in.
    // '****' + lockedSourceId is exactly what the server substitutes from
    // the authoritative source row, so this is never a fabricated value.
    for (final k in _prefillKeys) {
      if (kSensitiveFields.contains(k)) {
        seeded.putIfAbsent(k, () => '****');
      }
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
