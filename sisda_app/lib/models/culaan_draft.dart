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
