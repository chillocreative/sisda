import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sisda_app/data/local/database.dart';
import 'package:sisda_app/models/culaan_draft.dart';
import 'package:sisda_app/models/sync_status.dart';
import 'package:sisda_app/providers.dart';
import 'package:sisda_app/features/home/home_screen.dart';

void main() {
  final now = DateTime.utc(2026, 7, 18);

  Future<void> pumpHome(WidgetTester tester, AppDatabase db) async {
    await tester.pumpWidget(ProviderScope(
      overrides: [appDatabaseProvider.overrideWithValue(db)],
      child: const MaterialApp(home: HomeScreen()),
    ));
    await tester.pump();
  }

  // Drift's query-stream teardown schedules a real (non-fake-async) Timer
  // when the last subscriber (draftCountsProvider's StreamProvider) cancels.
  // Swapping in a throwaway widget disposes HomeScreen/ProviderScope
  // synchronously inside the test body, and the follow-up pump gives that
  // Timer a chance to fire before the test ends — see main_shell_test.dart.
  Future<void> disposeAndDrainTimers(WidgetTester tester) async {
    await tester.pumpWidget(const SizedBox());
    await tester.pump(const Duration(milliseconds: 50));
  }

  testWidgets('amber strip shows the queued-draft count when queued > 0',
      (tester) async {
    final db = AppDatabase.forTesting();
    addTearDown(db.close);
    for (final key in ['q1', 'q2', 'q3']) {
      await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: key, now: now)
          .copyWith(status: SyncStatus.queued));
    }
    await pumpHome(tester, db);
    // draftCountsProvider is a StreamProvider over Drift's watchAll(); its
    // first emission is asynchronous, so give the query stream time to
    // deliver before asserting on the strip.
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.text('3 culaan menunggu talian'), findsOneWidget);
    await disposeAndDrainTimers(tester);
  });

  testWidgets('amber strip is absent when queued == 0', (tester) async {
    final db = AppDatabase.forTesting();
    addTearDown(db.close);
    await pumpHome(tester, db);
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.textContaining('culaan menunggu talian'), findsNothing);
    await disposeAndDrainTimers(tester);
  });

  testWidgets('search bar and Borang Culaan Baru entry point are present',
      (tester) async {
    final db = AppDatabase.forTesting();
    addTearDown(db.close);
    await pumpHome(tester, db);
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.text('Cari nama atau No. IC…'), findsOneWidget);
    expect(find.byIcon(Icons.camera_alt_outlined), findsOneWidget);
    expect(find.text('Borang Culaan Baru'), findsOneWidget);
    await disposeAndDrainTimers(tester);
  });
}
