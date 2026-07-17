import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sisda_app/data/local/database.dart';
import 'package:sisda_app/models/culaan_draft.dart';
import 'package:sisda_app/models/sync_status.dart';
import 'package:sisda_app/providers.dart';
import 'package:sisda_app/features/perlu_perhatian/perlu_perhatian_screen.dart';

void main() {
  final now = DateTime.utc(2026, 7, 18);

  const reason1 = 'Rekod ini di luar Parlimen anda.';
  const reason2 = 'Ruangan Bil. Isi Rumah diperlukan.';

  Future<void> pumpScreen(WidgetTester tester, AppDatabase db) async {
    await tester.pumpWidget(ProviderScope(
      overrides: [appDatabaseProvider.overrideWithValue(db)],
      child: const MaterialApp(home: PerluPerhatianScreen()),
    ));
    await tester.pump(); // build
    await tester.pump(const Duration(milliseconds: 300)); // let watchAll() emit
  }

  // Drift's query-stream teardown schedules a real (non-fake-async) Timer
  // when the last subscriber cancels — see main_shell_test.dart /
  // home_screen_test.dart. Swap in a throwaway widget to dispose the screen
  // synchronously inside the test body, then pump to drain the Timer.
  Future<void> disposeAndDrainTimers(WidgetTester tester) async {
    await tester.pumpWidget(const SizedBox());
    await tester.pump(const Duration(milliseconds: 50));
  }

  Future<void> seedThreeDrafts(AppDatabase db) async {
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'f1', now: now).copyWith(
      status: SyncStatus.failed,
      failureReason: reason1,
      fields: {'nama': 'Ahmad bin Ali'},
    ));
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'f2', now: now).copyWith(
      status: SyncStatus.failed,
      failureReason: reason2,
      fields: {'nama': 'Siti binti Hassan'},
    ));
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'q1', now: now)
        .copyWith(status: SyncStatus.queued));
  }

  testWidgets('lists only failed drafts with their BM failure reasons',
      (tester) async {
    final db = AppDatabase.forTesting();
    addTearDown(db.close);
    await seedThreeDrafts(db);

    await pumpScreen(tester, db);

    expect(find.text(reason1), findsOneWidget);
    expect(find.text(reason2), findsOneWidget);
    expect(find.text('Ahmad bin Ali'), findsOneWidget);
    expect(find.text('Siti binti Hassan'), findsOneWidget);
    // Only two cards for the two failed drafts — the queued one is excluded.
    expect(find.text('Betulkan'), findsNWidgets(2));

    await disposeAndDrainTimers(tester);
  });

  testWidgets('shows the empty state when there are no failed drafts',
      (tester) async {
    final db = AppDatabase.forTesting();
    addTearDown(db.close);
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'q1', now: now)
        .copyWith(status: SyncStatus.queued));

    await pumpScreen(tester, db);

    expect(find.text('Tiada culaan yang perlu perhatian.'), findsOneWidget);

    await disposeAndDrainTimers(tester);
  });

  testWidgets('Buang shows a BM confirm dialog and deletes the draft on confirm',
      (tester) async {
    final db = AppDatabase.forTesting();
    addTearDown(db.close);
    await seedThreeDrafts(db);

    await pumpScreen(tester, db);

    // Scope to the card for f1 (reason1) so we tap its own Buang button.
    final card1 = find.ancestor(
      of: find.text(reason1),
      matching: find.byType(Card),
    );
    expect(card1, findsOneWidget);

    await tester.tap(find.descendant(of: card1, matching: find.text('Buang')));
    await tester.pumpAndSettle();

    expect(find.text('Buang culaan ini?'), findsOneWidget);

    final dialog = find.byType(AlertDialog);
    await tester
        .tap(find.descendant(of: dialog, matching: find.text('Buang')));
    await tester.pump(const Duration(milliseconds: 300)); // let the stream re-emit

    expect(find.text(reason1), findsNothing);
    expect(find.text(reason2), findsOneWidget);
    expect(await db.getDraft('f1'), isNull);

    await disposeAndDrainTimers(tester);
  });
}
