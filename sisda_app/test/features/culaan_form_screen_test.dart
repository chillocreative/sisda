import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:sisda_app/data/local/database.dart';
import 'package:sisda_app/data/remote/mobile_api.dart';
import 'package:sisda_app/features/culaan/culaan_draft_loader.dart';
import 'package:sisda_app/features/culaan/culaan_form_screen.dart';
import 'package:sisda_app/models/culaan_draft.dart';
import 'package:sisda_app/models/sync_status.dart';
import 'package:sisda_app/models/voter.dart';
import 'package:sisda_app/providers.dart';
import 'package:sisda_app/sync/sync_engine.dart';

class MockMobileApi extends Mock implements MobileApi {}
class MockSyncEngine extends Mock implements SyncEngine {}

Future<void> _drain(WidgetTester tester) async {
  await tester.pumpWidget(const SizedBox());
  await tester.pump(const Duration(milliseconds: 50));
}

void main() {
  late AppDatabase db;
  late MockMobileApi api;
  late MockSyncEngine sync;
  final now = DateTime(2026, 7, 18);

  setUp(() {
    db = AppDatabase.forTesting();
    api = MockMobileApi();
    sync = MockSyncEngine();
    when(() => api.culaanOptions()).thenAnswer((_) async => {'pekerjaan': [], 'jenis_pekerjaan': {}, 'jenis_sumbangan': [], 'tujuan_sumbangan': [], 'bantuan_lain': [], 'pemilik_rumah': []});
    when(() => sync.syncNow(now: any(named: 'now'))).thenAnswer((_) async => SyncOutcome());
  });
  tearDown(() => db.close());

  Future<void> pump(WidgetTester tester, {String? draftKey, Voter? prefill}) async {
    await tester.pumpWidget(ProviderScope(
      overrides: [
        appDatabaseProvider.overrideWithValue(db),
        mobileApiProvider.overrideWithValue(api),
        syncEngineProvider.overrideWithValue(sync),
        idGeneratorProvider.overrideWithValue(() => 'TEST-KEY'),
      ],
      child: MaterialApp(home: CulaanFormScreen(draftKey: draftKey, prefillVoter: prefill)),
    ));
    await tester.pump();               // build
    await tester.pump();               // let loadOrCreateDraft future resolve
  }

  testWidgets('blank form shows 5 sections and "0 daripada 3 bahagian wajib siap"', (tester) async {
    await pump(tester);
    expect(find.text('Maklumat Peribadi'), findsOneWidget);
    expect(find.text('Isi Rumah'), findsNothing); // gated off
    expect(find.textContaining('0 daripada 3'), findsOneWidget);
    await _drain(tester);
  });

  testWidgets('Ada Sumbangan toggle adds Isi Rumah + Bantuan', (tester) async {
    await pump(tester);
    await tester.tap(find.byType(SwitchListTile));
    await tester.pump();
    expect(find.text('Isi Rumah'), findsOneWidget);
    expect(find.text('Bantuan'), findsOneWidget);
    expect(find.textContaining('daripada 5'), findsOneWidget); // now 5 required sections
    await _drain(tester);
  });

  testWidgets('optional sections render a "pilihan" tag, not Belum diisi', (tester) async {
    await pump(tester);
    final politikRow = find.ancestor(of: find.text('Maklumat Politik'), matching: find.byType(ListTile));
    expect(find.descendant(of: politikRow, matching: find.text('pilihan')), findsOneWidget);
    await _drain(tester);
  });

  testWidgets('reopen a seeded draft shows its saved section completeness', (tester) async {
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'D1', now: now).copyWith(
      status: SyncStatus.failed,
      fields: {
        'nama': 'Ali', 'no_ic': '800101015555', 'umur': '44',
        'no_tel': '0121234567', 'bangsa': 'Melayu',
      },
    ));
    await pump(tester, draftKey: 'D1');
    // Peribadi complete → ✓; Alamat incomplete → Belum diisi
    final peribadiRow = find.ancestor(of: find.text('Maklumat Peribadi'), matching: find.byType(ListTile));
    expect(find.descendant(of: peribadiRow, matching: find.byIcon(Icons.check_circle)), findsOneWidget);
    expect(find.textContaining('1 daripada 3'), findsOneWidget);
    await _drain(tester);
  });

  testWidgets('masked prefill locks name shown but marks locked_source_id', (tester) async {
    final voter = Voter.fromJson({'id': 9, 'nama': 'Ahmad', 'no_ic': '****'});
    await pump(tester, prefill: voter);
    expect(find.text('Ahmad'), findsOneWidget); // title from prefilled nama
    // draft persisted with lockedSourceId
    final saved = await db.getDraft('TEST-KEY');
    expect(saved!.lockedSourceId, 9);
    await _drain(tester);
  });

  testWidgets('Hantar with missing required fields shows BM message, does NOT enqueue', (tester) async {
    await pump(tester);
    await tester.tap(find.text('Hantar'));
    await tester.pump();
    expect(find.textContaining('Sila lengkapkan'), findsOneWidget);
    verifyNever(() => sync.syncNow(now: any(named: 'now')));
    final saved = await db.getDraft('TEST-KEY');
    expect(saved!.status, SyncStatus.draft); // still a draft, not queued
    await _drain(tester);
  });

  testWidgets('Hantar with a complete form enqueues (queued) and triggers syncNow', (tester) async {
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'C1', now: now).copyWith(
      status: SyncStatus.failed,
      failureReason: 'Di luar Parlimen anda.',
      fields: {
        'nama': 'Ali', 'no_ic': '800101015555', 'umur': '44', 'no_tel': '0121234567',
        'bangsa': 'Melayu', 'alamat': 'Jln 1', 'poskod': '13200', 'negeri': 'P. Pinang',
        'bandar': 'Kepala Batas', 'parlimen': 'P.044', 'kadun': 'N.11',
      },
    ));
    await pump(tester, draftKey: 'C1');
    await tester.tap(find.text('Hantar'));
    await tester.pump();
    final saved = await db.getDraft('C1');
    expect(saved!.status, SyncStatus.queued);
    expect(saved.failureReason, isNull); // cleared on re-submit (leaves Perlu Perhatian)
    verify(() => sync.syncNow(now: any(named: 'now'))).called(1);
    await _drain(tester);
  });

  testWidgets('toggling Ada Sumbangan OFF strips Isi Rumah/Bantuan keys but keeps core fields', (tester) async {
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'C2', now: now).copyWith(
      hasSumbangan: true,
      fields: {
        'nama': 'Ali', 'no_ic': '800101015555', 'umur': '44', 'no_tel': '0121234567',
        'bangsa': 'Melayu', 'alamat': 'Jln 1', 'poskod': '13200', 'negeri': 'P. Pinang',
        'bandar': 'Kepala Batas', 'parlimen': 'P.044', 'kadun': 'N.11',
        'bil_isi_rumah': '4', 'pekerjaan': 'Kerajaan', 'jenis_sumbangan': ['Barangan'],
      },
    ));
    await pump(tester, draftKey: 'C2');
    await tester.tap(find.byType(SwitchListTile));
    await tester.pump();
    final saved = await db.getDraft('C2');
    expect(saved!.hasSumbangan, isFalse);
    expect(saved.fields.containsKey('bil_isi_rumah'), isFalse);
    expect(saved.fields.containsKey('pekerjaan'), isFalse);
    expect(saved.fields.containsKey('jenis_sumbangan'), isFalse);
    expect(saved.fields['nama'], 'Ali');
    await _drain(tester);
  });
}
