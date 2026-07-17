import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sisda_app/data/local/database.dart';
import 'package:sisda_app/models/culaan_draft.dart';
import 'package:sisda_app/models/sync_status.dart';
import 'package:sisda_app/providers.dart';
import 'package:sisda_app/features/shell/main_shell.dart';

void main() {
  final now = DateTime.utc(2026, 7, 18);

  // Scope label/badge lookups to the NavigationBar so they don't collide
  // with whatever the tab bodies render (placeholders today, real screens
  // later) — the IndexedStack builds all four tab bodies at once.
  Finder inNavBar(Finder matching) =>
      find.descendant(of: find.byType(NavigationBar), matching: matching);

  Future<void> pumpShell(WidgetTester tester, AppDatabase db) async {
    await tester.pumpWidget(ProviderScope(
      overrides: [appDatabaseProvider.overrideWithValue(db)],
      child: const MaterialApp(home: MainShell()),
    ));
    await tester.pump(); // let the stream emit
  }

  // Drift's query-stream teardown schedules a real (non-fake-async) Timer
  // when a subscriber (draftCountsProvider's StreamProvider) cancels, as
  // part of its debounced-unsubscribe bookkeeping. flutter_test's binding
  // otherwise only disposes the widget tree (and cancels that subscription)
  // during its own post-test teardown, after the test body has already
  // returned — leaving that Timer pending and tripping the framework's
  // "Timer is still pending" invariant check. Swapping in a throwaway
  // widget disposes MainShell/ProviderScope synchronously inside the test
  // body, and the follow-up pump gives the scheduled Timer a chance to
  // fire before the test ends.
  Future<void> disposeShellAndDrainTimers(WidgetTester tester) async {
    await tester.pumpWidget(const SizedBox());
    await tester.pump(const Duration(milliseconds: 50));
  }

  testWidgets('renders four tabs', (tester) async {
    final db = AppDatabase.forTesting();
    addTearDown(db.close);
    await pumpShell(tester, db);
    expect(inNavBar(find.text('Utama')), findsOneWidget);
    expect(inNavBar(find.text('Culaan')), findsOneWidget);
    expect(inNavBar(find.text('Perlu Perhatian')), findsOneWidget);
    expect(inNavBar(find.text('Profil')), findsOneWidget);
    await disposeShellAndDrainTimers(tester);
  });

  testWidgets('Perlu Perhatian badge shows the failed-draft count', (tester) async {
    final db = AppDatabase.forTesting();
    addTearDown(db.close);
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'f1', now: now)
        .copyWith(status: SyncStatus.failed, failureReason: 'Rekod ini di luar Parlimen anda.'));
    await db.upsertDraft(CulaanDraft.newDraft(idempotencyKey: 'q1', now: now)
        .copyWith(status: SyncStatus.queued));
    await pumpShell(tester, db);
    // draftCountsProvider is a StreamProvider over Drift's watchAll(); its
    // first emission is asynchronous, so give the query stream time to
    // deliver before asserting on the badge.
    await tester.pump(); // build
    await tester.pump(const Duration(milliseconds: 300)); // let watchAll() emit
    expect(inNavBar(find.text('1')), findsOneWidget); // one failed draft → badge "1"
    await disposeShellAndDrainTimers(tester);
  });
}
