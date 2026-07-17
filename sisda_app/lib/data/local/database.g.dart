// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'database.dart';

// ignore_for_file: type=lint
class $DraftsTable extends Drafts with TableInfo<$DraftsTable, Draft> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $DraftsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idempotencyKeyMeta = const VerificationMeta(
    'idempotencyKey',
  );
  @override
  late final GeneratedColumn<String> idempotencyKey = GeneratedColumn<String>(
    'idempotency_key',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _fieldsJsonMeta = const VerificationMeta(
    'fieldsJson',
  );
  @override
  late final GeneratedColumn<String> fieldsJson = GeneratedColumn<String>(
    'fields_json',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('{}'),
  );
  static const VerificationMeta _hasSumbanganMeta = const VerificationMeta(
    'hasSumbangan',
  );
  @override
  late final GeneratedColumn<bool> hasSumbangan = GeneratedColumn<bool>(
    'has_sumbangan',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("has_sumbangan" IN (0, 1))',
    ),
    defaultValue: const Constant(false),
  );
  static const VerificationMeta _lockedSourceIdMeta = const VerificationMeta(
    'lockedSourceId',
  );
  @override
  late final GeneratedColumn<int> lockedSourceId = GeneratedColumn<int>(
    'locked_source_id',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _statusMeta = const VerificationMeta('status');
  @override
  late final GeneratedColumn<int> status = GeneratedColumn<int>(
    'status',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _attemptsMeta = const VerificationMeta(
    'attempts',
  );
  @override
  late final GeneratedColumn<int> attempts = GeneratedColumn<int>(
    'attempts',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _nextRetryAtMeta = const VerificationMeta(
    'nextRetryAt',
  );
  @override
  late final GeneratedColumn<DateTime> nextRetryAt = GeneratedColumn<DateTime>(
    'next_retry_at',
    aliasedName,
    true,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _failureReasonMeta = const VerificationMeta(
    'failureReason',
  );
  @override
  late final GeneratedColumn<String> failureReason = GeneratedColumn<String>(
    'failure_reason',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _createdAtMeta = const VerificationMeta(
    'createdAt',
  );
  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
    'created_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _updatedAtMeta = const VerificationMeta(
    'updatedAt',
  );
  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    idempotencyKey,
    fieldsJson,
    hasSumbangan,
    lockedSourceId,
    status,
    attempts,
    nextRetryAt,
    failureReason,
    createdAt,
    updatedAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'drafts';
  @override
  VerificationContext validateIntegrity(
    Insertable<Draft> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('idempotency_key')) {
      context.handle(
        _idempotencyKeyMeta,
        idempotencyKey.isAcceptableOrUnknown(
          data['idempotency_key']!,
          _idempotencyKeyMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_idempotencyKeyMeta);
    }
    if (data.containsKey('fields_json')) {
      context.handle(
        _fieldsJsonMeta,
        fieldsJson.isAcceptableOrUnknown(data['fields_json']!, _fieldsJsonMeta),
      );
    }
    if (data.containsKey('has_sumbangan')) {
      context.handle(
        _hasSumbanganMeta,
        hasSumbangan.isAcceptableOrUnknown(
          data['has_sumbangan']!,
          _hasSumbanganMeta,
        ),
      );
    }
    if (data.containsKey('locked_source_id')) {
      context.handle(
        _lockedSourceIdMeta,
        lockedSourceId.isAcceptableOrUnknown(
          data['locked_source_id']!,
          _lockedSourceIdMeta,
        ),
      );
    }
    if (data.containsKey('status')) {
      context.handle(
        _statusMeta,
        status.isAcceptableOrUnknown(data['status']!, _statusMeta),
      );
    }
    if (data.containsKey('attempts')) {
      context.handle(
        _attemptsMeta,
        attempts.isAcceptableOrUnknown(data['attempts']!, _attemptsMeta),
      );
    }
    if (data.containsKey('next_retry_at')) {
      context.handle(
        _nextRetryAtMeta,
        nextRetryAt.isAcceptableOrUnknown(
          data['next_retry_at']!,
          _nextRetryAtMeta,
        ),
      );
    }
    if (data.containsKey('failure_reason')) {
      context.handle(
        _failureReasonMeta,
        failureReason.isAcceptableOrUnknown(
          data['failure_reason']!,
          _failureReasonMeta,
        ),
      );
    }
    if (data.containsKey('created_at')) {
      context.handle(
        _createdAtMeta,
        createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta),
      );
    } else if (isInserting) {
      context.missing(_createdAtMeta);
    }
    if (data.containsKey('updated_at')) {
      context.handle(
        _updatedAtMeta,
        updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_updatedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {idempotencyKey};
  @override
  Draft map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return Draft(
      idempotencyKey: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}idempotency_key'],
      )!,
      fieldsJson: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}fields_json'],
      )!,
      hasSumbangan: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}has_sumbangan'],
      )!,
      lockedSourceId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}locked_source_id'],
      ),
      status: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}status'],
      )!,
      attempts: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}attempts'],
      )!,
      nextRetryAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}next_retry_at'],
      ),
      failureReason: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}failure_reason'],
      ),
      createdAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}created_at'],
      )!,
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $DraftsTable createAlias(String alias) {
    return $DraftsTable(attachedDatabase, alias);
  }
}

class Draft extends DataClass implements Insertable<Draft> {
  final String idempotencyKey;
  final String fieldsJson;
  final bool hasSumbangan;
  final int? lockedSourceId;
  final int status;
  final int attempts;
  final DateTime? nextRetryAt;
  final String? failureReason;
  final DateTime createdAt;
  final DateTime updatedAt;
  const Draft({
    required this.idempotencyKey,
    required this.fieldsJson,
    required this.hasSumbangan,
    this.lockedSourceId,
    required this.status,
    required this.attempts,
    this.nextRetryAt,
    this.failureReason,
    required this.createdAt,
    required this.updatedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['idempotency_key'] = Variable<String>(idempotencyKey);
    map['fields_json'] = Variable<String>(fieldsJson);
    map['has_sumbangan'] = Variable<bool>(hasSumbangan);
    if (!nullToAbsent || lockedSourceId != null) {
      map['locked_source_id'] = Variable<int>(lockedSourceId);
    }
    map['status'] = Variable<int>(status);
    map['attempts'] = Variable<int>(attempts);
    if (!nullToAbsent || nextRetryAt != null) {
      map['next_retry_at'] = Variable<DateTime>(nextRetryAt);
    }
    if (!nullToAbsent || failureReason != null) {
      map['failure_reason'] = Variable<String>(failureReason);
    }
    map['created_at'] = Variable<DateTime>(createdAt);
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  DraftsCompanion toCompanion(bool nullToAbsent) {
    return DraftsCompanion(
      idempotencyKey: Value(idempotencyKey),
      fieldsJson: Value(fieldsJson),
      hasSumbangan: Value(hasSumbangan),
      lockedSourceId: lockedSourceId == null && nullToAbsent
          ? const Value.absent()
          : Value(lockedSourceId),
      status: Value(status),
      attempts: Value(attempts),
      nextRetryAt: nextRetryAt == null && nullToAbsent
          ? const Value.absent()
          : Value(nextRetryAt),
      failureReason: failureReason == null && nullToAbsent
          ? const Value.absent()
          : Value(failureReason),
      createdAt: Value(createdAt),
      updatedAt: Value(updatedAt),
    );
  }

  factory Draft.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return Draft(
      idempotencyKey: serializer.fromJson<String>(json['idempotencyKey']),
      fieldsJson: serializer.fromJson<String>(json['fieldsJson']),
      hasSumbangan: serializer.fromJson<bool>(json['hasSumbangan']),
      lockedSourceId: serializer.fromJson<int?>(json['lockedSourceId']),
      status: serializer.fromJson<int>(json['status']),
      attempts: serializer.fromJson<int>(json['attempts']),
      nextRetryAt: serializer.fromJson<DateTime?>(json['nextRetryAt']),
      failureReason: serializer.fromJson<String?>(json['failureReason']),
      createdAt: serializer.fromJson<DateTime>(json['createdAt']),
      updatedAt: serializer.fromJson<DateTime>(json['updatedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'idempotencyKey': serializer.toJson<String>(idempotencyKey),
      'fieldsJson': serializer.toJson<String>(fieldsJson),
      'hasSumbangan': serializer.toJson<bool>(hasSumbangan),
      'lockedSourceId': serializer.toJson<int?>(lockedSourceId),
      'status': serializer.toJson<int>(status),
      'attempts': serializer.toJson<int>(attempts),
      'nextRetryAt': serializer.toJson<DateTime?>(nextRetryAt),
      'failureReason': serializer.toJson<String?>(failureReason),
      'createdAt': serializer.toJson<DateTime>(createdAt),
      'updatedAt': serializer.toJson<DateTime>(updatedAt),
    };
  }

  Draft copyWith({
    String? idempotencyKey,
    String? fieldsJson,
    bool? hasSumbangan,
    Value<int?> lockedSourceId = const Value.absent(),
    int? status,
    int? attempts,
    Value<DateTime?> nextRetryAt = const Value.absent(),
    Value<String?> failureReason = const Value.absent(),
    DateTime? createdAt,
    DateTime? updatedAt,
  }) => Draft(
    idempotencyKey: idempotencyKey ?? this.idempotencyKey,
    fieldsJson: fieldsJson ?? this.fieldsJson,
    hasSumbangan: hasSumbangan ?? this.hasSumbangan,
    lockedSourceId: lockedSourceId.present
        ? lockedSourceId.value
        : this.lockedSourceId,
    status: status ?? this.status,
    attempts: attempts ?? this.attempts,
    nextRetryAt: nextRetryAt.present ? nextRetryAt.value : this.nextRetryAt,
    failureReason: failureReason.present
        ? failureReason.value
        : this.failureReason,
    createdAt: createdAt ?? this.createdAt,
    updatedAt: updatedAt ?? this.updatedAt,
  );
  Draft copyWithCompanion(DraftsCompanion data) {
    return Draft(
      idempotencyKey: data.idempotencyKey.present
          ? data.idempotencyKey.value
          : this.idempotencyKey,
      fieldsJson: data.fieldsJson.present
          ? data.fieldsJson.value
          : this.fieldsJson,
      hasSumbangan: data.hasSumbangan.present
          ? data.hasSumbangan.value
          : this.hasSumbangan,
      lockedSourceId: data.lockedSourceId.present
          ? data.lockedSourceId.value
          : this.lockedSourceId,
      status: data.status.present ? data.status.value : this.status,
      attempts: data.attempts.present ? data.attempts.value : this.attempts,
      nextRetryAt: data.nextRetryAt.present
          ? data.nextRetryAt.value
          : this.nextRetryAt,
      failureReason: data.failureReason.present
          ? data.failureReason.value
          : this.failureReason,
      createdAt: data.createdAt.present ? data.createdAt.value : this.createdAt,
      updatedAt: data.updatedAt.present ? data.updatedAt.value : this.updatedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('Draft(')
          ..write('idempotencyKey: $idempotencyKey, ')
          ..write('fieldsJson: $fieldsJson, ')
          ..write('hasSumbangan: $hasSumbangan, ')
          ..write('lockedSourceId: $lockedSourceId, ')
          ..write('status: $status, ')
          ..write('attempts: $attempts, ')
          ..write('nextRetryAt: $nextRetryAt, ')
          ..write('failureReason: $failureReason, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    idempotencyKey,
    fieldsJson,
    hasSumbangan,
    lockedSourceId,
    status,
    attempts,
    nextRetryAt,
    failureReason,
    createdAt,
    updatedAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Draft &&
          other.idempotencyKey == this.idempotencyKey &&
          other.fieldsJson == this.fieldsJson &&
          other.hasSumbangan == this.hasSumbangan &&
          other.lockedSourceId == this.lockedSourceId &&
          other.status == this.status &&
          other.attempts == this.attempts &&
          other.nextRetryAt == this.nextRetryAt &&
          other.failureReason == this.failureReason &&
          other.createdAt == this.createdAt &&
          other.updatedAt == this.updatedAt);
}

class DraftsCompanion extends UpdateCompanion<Draft> {
  final Value<String> idempotencyKey;
  final Value<String> fieldsJson;
  final Value<bool> hasSumbangan;
  final Value<int?> lockedSourceId;
  final Value<int> status;
  final Value<int> attempts;
  final Value<DateTime?> nextRetryAt;
  final Value<String?> failureReason;
  final Value<DateTime> createdAt;
  final Value<DateTime> updatedAt;
  final Value<int> rowid;
  const DraftsCompanion({
    this.idempotencyKey = const Value.absent(),
    this.fieldsJson = const Value.absent(),
    this.hasSumbangan = const Value.absent(),
    this.lockedSourceId = const Value.absent(),
    this.status = const Value.absent(),
    this.attempts = const Value.absent(),
    this.nextRetryAt = const Value.absent(),
    this.failureReason = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.updatedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  DraftsCompanion.insert({
    required String idempotencyKey,
    this.fieldsJson = const Value.absent(),
    this.hasSumbangan = const Value.absent(),
    this.lockedSourceId = const Value.absent(),
    this.status = const Value.absent(),
    this.attempts = const Value.absent(),
    this.nextRetryAt = const Value.absent(),
    this.failureReason = const Value.absent(),
    required DateTime createdAt,
    required DateTime updatedAt,
    this.rowid = const Value.absent(),
  }) : idempotencyKey = Value(idempotencyKey),
       createdAt = Value(createdAt),
       updatedAt = Value(updatedAt);
  static Insertable<Draft> custom({
    Expression<String>? idempotencyKey,
    Expression<String>? fieldsJson,
    Expression<bool>? hasSumbangan,
    Expression<int>? lockedSourceId,
    Expression<int>? status,
    Expression<int>? attempts,
    Expression<DateTime>? nextRetryAt,
    Expression<String>? failureReason,
    Expression<DateTime>? createdAt,
    Expression<DateTime>? updatedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (idempotencyKey != null) 'idempotency_key': idempotencyKey,
      if (fieldsJson != null) 'fields_json': fieldsJson,
      if (hasSumbangan != null) 'has_sumbangan': hasSumbangan,
      if (lockedSourceId != null) 'locked_source_id': lockedSourceId,
      if (status != null) 'status': status,
      if (attempts != null) 'attempts': attempts,
      if (nextRetryAt != null) 'next_retry_at': nextRetryAt,
      if (failureReason != null) 'failure_reason': failureReason,
      if (createdAt != null) 'created_at': createdAt,
      if (updatedAt != null) 'updated_at': updatedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  DraftsCompanion copyWith({
    Value<String>? idempotencyKey,
    Value<String>? fieldsJson,
    Value<bool>? hasSumbangan,
    Value<int?>? lockedSourceId,
    Value<int>? status,
    Value<int>? attempts,
    Value<DateTime?>? nextRetryAt,
    Value<String?>? failureReason,
    Value<DateTime>? createdAt,
    Value<DateTime>? updatedAt,
    Value<int>? rowid,
  }) {
    return DraftsCompanion(
      idempotencyKey: idempotencyKey ?? this.idempotencyKey,
      fieldsJson: fieldsJson ?? this.fieldsJson,
      hasSumbangan: hasSumbangan ?? this.hasSumbangan,
      lockedSourceId: lockedSourceId ?? this.lockedSourceId,
      status: status ?? this.status,
      attempts: attempts ?? this.attempts,
      nextRetryAt: nextRetryAt ?? this.nextRetryAt,
      failureReason: failureReason ?? this.failureReason,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (idempotencyKey.present) {
      map['idempotency_key'] = Variable<String>(idempotencyKey.value);
    }
    if (fieldsJson.present) {
      map['fields_json'] = Variable<String>(fieldsJson.value);
    }
    if (hasSumbangan.present) {
      map['has_sumbangan'] = Variable<bool>(hasSumbangan.value);
    }
    if (lockedSourceId.present) {
      map['locked_source_id'] = Variable<int>(lockedSourceId.value);
    }
    if (status.present) {
      map['status'] = Variable<int>(status.value);
    }
    if (attempts.present) {
      map['attempts'] = Variable<int>(attempts.value);
    }
    if (nextRetryAt.present) {
      map['next_retry_at'] = Variable<DateTime>(nextRetryAt.value);
    }
    if (failureReason.present) {
      map['failure_reason'] = Variable<String>(failureReason.value);
    }
    if (createdAt.present) {
      map['created_at'] = Variable<DateTime>(createdAt.value);
    }
    if (updatedAt.present) {
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('DraftsCompanion(')
          ..write('idempotencyKey: $idempotencyKey, ')
          ..write('fieldsJson: $fieldsJson, ')
          ..write('hasSumbangan: $hasSumbangan, ')
          ..write('lockedSourceId: $lockedSourceId, ')
          ..write('status: $status, ')
          ..write('attempts: $attempts, ')
          ..write('nextRetryAt: $nextRetryAt, ')
          ..write('failureReason: $failureReason, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

abstract class _$AppDatabase extends GeneratedDatabase {
  _$AppDatabase(QueryExecutor e) : super(e);
  $AppDatabaseManager get managers => $AppDatabaseManager(this);
  late final $DraftsTable drafts = $DraftsTable(this);
  @override
  Iterable<TableInfo<Table, Object?>> get allTables =>
      allSchemaEntities.whereType<TableInfo<Table, Object?>>();
  @override
  List<DatabaseSchemaEntity> get allSchemaEntities => [drafts];
}

typedef $$DraftsTableCreateCompanionBuilder =
    DraftsCompanion Function({
      required String idempotencyKey,
      Value<String> fieldsJson,
      Value<bool> hasSumbangan,
      Value<int?> lockedSourceId,
      Value<int> status,
      Value<int> attempts,
      Value<DateTime?> nextRetryAt,
      Value<String?> failureReason,
      required DateTime createdAt,
      required DateTime updatedAt,
      Value<int> rowid,
    });
typedef $$DraftsTableUpdateCompanionBuilder =
    DraftsCompanion Function({
      Value<String> idempotencyKey,
      Value<String> fieldsJson,
      Value<bool> hasSumbangan,
      Value<int?> lockedSourceId,
      Value<int> status,
      Value<int> attempts,
      Value<DateTime?> nextRetryAt,
      Value<String?> failureReason,
      Value<DateTime> createdAt,
      Value<DateTime> updatedAt,
      Value<int> rowid,
    });

class $$DraftsTableFilterComposer
    extends Composer<_$AppDatabase, $DraftsTable> {
  $$DraftsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get idempotencyKey => $composableBuilder(
    column: $table.idempotencyKey,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get fieldsJson => $composableBuilder(
    column: $table.fieldsJson,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get hasSumbangan => $composableBuilder(
    column: $table.hasSumbangan,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get lockedSourceId => $composableBuilder(
    column: $table.lockedSourceId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get attempts => $composableBuilder(
    column: $table.attempts,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get nextRetryAt => $composableBuilder(
    column: $table.nextRetryAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get failureReason => $composableBuilder(
    column: $table.failureReason,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$DraftsTableOrderingComposer
    extends Composer<_$AppDatabase, $DraftsTable> {
  $$DraftsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get idempotencyKey => $composableBuilder(
    column: $table.idempotencyKey,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get fieldsJson => $composableBuilder(
    column: $table.fieldsJson,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get hasSumbangan => $composableBuilder(
    column: $table.hasSumbangan,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get lockedSourceId => $composableBuilder(
    column: $table.lockedSourceId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get attempts => $composableBuilder(
    column: $table.attempts,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get nextRetryAt => $composableBuilder(
    column: $table.nextRetryAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get failureReason => $composableBuilder(
    column: $table.failureReason,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$DraftsTableAnnotationComposer
    extends Composer<_$AppDatabase, $DraftsTable> {
  $$DraftsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get idempotencyKey => $composableBuilder(
    column: $table.idempotencyKey,
    builder: (column) => column,
  );

  GeneratedColumn<String> get fieldsJson => $composableBuilder(
    column: $table.fieldsJson,
    builder: (column) => column,
  );

  GeneratedColumn<bool> get hasSumbangan => $composableBuilder(
    column: $table.hasSumbangan,
    builder: (column) => column,
  );

  GeneratedColumn<int> get lockedSourceId => $composableBuilder(
    column: $table.lockedSourceId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get status =>
      $composableBuilder(column: $table.status, builder: (column) => column);

  GeneratedColumn<int> get attempts =>
      $composableBuilder(column: $table.attempts, builder: (column) => column);

  GeneratedColumn<DateTime> get nextRetryAt => $composableBuilder(
    column: $table.nextRetryAt,
    builder: (column) => column,
  );

  GeneratedColumn<String> get failureReason => $composableBuilder(
    column: $table.failureReason,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get createdAt =>
      $composableBuilder(column: $table.createdAt, builder: (column) => column);

  GeneratedColumn<DateTime> get updatedAt =>
      $composableBuilder(column: $table.updatedAt, builder: (column) => column);
}

class $$DraftsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $DraftsTable,
          Draft,
          $$DraftsTableFilterComposer,
          $$DraftsTableOrderingComposer,
          $$DraftsTableAnnotationComposer,
          $$DraftsTableCreateCompanionBuilder,
          $$DraftsTableUpdateCompanionBuilder,
          (Draft, BaseReferences<_$AppDatabase, $DraftsTable, Draft>),
          Draft,
          PrefetchHooks Function()
        > {
  $$DraftsTableTableManager(_$AppDatabase db, $DraftsTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$DraftsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$DraftsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$DraftsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> idempotencyKey = const Value.absent(),
                Value<String> fieldsJson = const Value.absent(),
                Value<bool> hasSumbangan = const Value.absent(),
                Value<int?> lockedSourceId = const Value.absent(),
                Value<int> status = const Value.absent(),
                Value<int> attempts = const Value.absent(),
                Value<DateTime?> nextRetryAt = const Value.absent(),
                Value<String?> failureReason = const Value.absent(),
                Value<DateTime> createdAt = const Value.absent(),
                Value<DateTime> updatedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => DraftsCompanion(
                idempotencyKey: idempotencyKey,
                fieldsJson: fieldsJson,
                hasSumbangan: hasSumbangan,
                lockedSourceId: lockedSourceId,
                status: status,
                attempts: attempts,
                nextRetryAt: nextRetryAt,
                failureReason: failureReason,
                createdAt: createdAt,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String idempotencyKey,
                Value<String> fieldsJson = const Value.absent(),
                Value<bool> hasSumbangan = const Value.absent(),
                Value<int?> lockedSourceId = const Value.absent(),
                Value<int> status = const Value.absent(),
                Value<int> attempts = const Value.absent(),
                Value<DateTime?> nextRetryAt = const Value.absent(),
                Value<String?> failureReason = const Value.absent(),
                required DateTime createdAt,
                required DateTime updatedAt,
                Value<int> rowid = const Value.absent(),
              }) => DraftsCompanion.insert(
                idempotencyKey: idempotencyKey,
                fieldsJson: fieldsJson,
                hasSumbangan: hasSumbangan,
                lockedSourceId: lockedSourceId,
                status: status,
                attempts: attempts,
                nextRetryAt: nextRetryAt,
                failureReason: failureReason,
                createdAt: createdAt,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$DraftsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $DraftsTable,
      Draft,
      $$DraftsTableFilterComposer,
      $$DraftsTableOrderingComposer,
      $$DraftsTableAnnotationComposer,
      $$DraftsTableCreateCompanionBuilder,
      $$DraftsTableUpdateCompanionBuilder,
      (Draft, BaseReferences<_$AppDatabase, $DraftsTable, Draft>),
      Draft,
      PrefetchHooks Function()
    >;

class $AppDatabaseManager {
  final _$AppDatabase _db;
  $AppDatabaseManager(this._db);
  $$DraftsTableTableManager get drafts =>
      $$DraftsTableTableManager(_db, _db.drafts);
}
