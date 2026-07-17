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
        _applyBucket(bucket, outcome);
        await _persist(draft, bucket, e.firstError(), now);
      } catch (e) {
        // Transport error with no HTTP status → transient.
        _applyBucket(FailureBucket.transient, outcome);
        await _persist(draft, FailureBucket.transient, null, now);
      }
    }
    return outcome;
  }

  void _applyBucket(FailureBucket bucket, SyncOutcome out) {
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
        // gated on re-login, not on time. Draft survives logout. Clear any
        // stale nextRetryAt left over from an earlier transient failure, or
        // queuedReadyToSync would keep skipping this draft until that old
        // timer elapses even though it's eligible the instant the user
        // re-authenticates.
        await _store.upsertDraft(d.copyWith(
            status: SyncStatus.queued, nextRetryAt: null, updatedAt: now));
      case FailureBucket.permanent:
        await _store.upsertDraft(d.copyWith(
          status: SyncStatus.failed,
          failureReason: reason,
          updatedAt: now,
        ));
    }
  }
}
