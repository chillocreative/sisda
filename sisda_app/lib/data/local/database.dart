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
