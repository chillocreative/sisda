import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mocktail/mocktail.dart';
import 'package:sisda_app/data/remote/mobile_api.dart';
import 'package:sisda_app/models/api_result.dart';
import 'package:sisda_app/providers.dart';
import 'package:sisda_app/sync/sync_engine.dart';
import 'package:sisda_app/features/culaan/rekod_saya_screen.dart';

class MockMobileApi extends Mock implements MobileApi {}

class MockSyncEngine extends Mock implements SyncEngine {}

void main() {
  late MockMobileApi api;
  late MockSyncEngine syncEngine;

  setUp(() {
    api = MockMobileApi();
    syncEngine = MockSyncEngine();
    when(() => syncEngine.syncNow(now: any(named: 'now')))
        .thenAnswer((_) async => SyncOutcome());
  });

  Future<void> pumpScreen(WidgetTester tester) async {
    await tester.pumpWidget(ProviderScope(
      overrides: [
        mobileApiProvider.overrideWithValue(api),
        syncEngineProvider.overrideWithValue(syncEngine),
      ],
      child: const MaterialApp(home: RekodSayaScreen()),
    ));
    await tester.pump(); // build, kicks off the future
    // culaanMine() resolves via a FutureProvider — a single pump only shows
    // the loading spinner; give the future time to resolve before asserting.
    await tester.pump(const Duration(milliseconds: 300));
  }

  testWidgets('lists culaanMine() records with masked fields shown verbatim',
      (tester) async {
    when(() => api.culaanMine()).thenAnswer((_) async => [
          {'nama': 'Ahmad bin Ali', 'no_ic': '****'},
          {'nama': 'Siti binti Hassan', 'no_ic': '****'},
        ]);

    await pumpScreen(tester);

    expect(
        find.descendant(
            of: find.byType(ListView), matching: find.text('Ahmad bin Ali')),
        findsOneWidget);
    expect(
        find.descendant(
            of: find.byType(ListView),
            matching: find.text('Siti binti Hassan')),
        findsOneWidget);
    expect(find.textContaining('****'), findsNWidgets(2)); // masked, never reconstructed
  });

  testWidgets('shows the BM empty state when there are no records',
      (tester) async {
    when(() => api.culaanMine()).thenAnswer((_) async => []);

    await pumpScreen(tester);

    expect(find.text('Tiada rekod culaan lagi.'), findsOneWidget);
  });

  testWidgets('Borang Culaan Baru entry point is present', (tester) async {
    when(() => api.culaanMine()).thenAnswer((_) async => []);

    await pumpScreen(tester);

    expect(find.text('Borang Culaan Baru'), findsOneWidget);
  });

  testWidgets('culaanMine() error shows the BM firstError message, never rawMessage',
      (tester) async {
    when(() => api.culaanMine()).thenThrow(const ApiException(
        status: 500, errors: {'culaan': ['Ralat pelayan.']}, rawMessage: 'Server Error'));

    await pumpScreen(tester);

    expect(find.text('Ralat pelayan.'), findsOneWidget);
    expect(find.textContaining('Server Error'), findsNothing);
  });
}
