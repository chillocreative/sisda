import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:sisda_app/features/webview/web_screens.dart';
import 'package:sisda_app/screens/webview_screen.dart';

class MockNavigatorObserver extends Mock implements NavigatorObserver {}

class _FakeRoute extends Fake implements Route<dynamic> {}

/// `openDashboard`/etc. push a real `WebViewScreen`, whose `initState`
/// creates a `WebViewController` — a platform channel not available under
/// `flutter_test` ("No implementation found for method ... on channel
/// plugin.flutter.io/webview"). So these tests never let the pushed route's
/// page actually build: they capture the `Route` via a `NavigatorObserver`
/// the instant it's pushed (synchronous, before any frame/build), then call
/// the captured `MaterialPageRoute.builder` directly to obtain the
/// `WebViewScreen` *widget instance* for inspection. Constructing a
/// StatefulWidget only runs its constructor — `initState`/`createState`
/// happen on mount, which this deliberately never triggers (no `pump()`
/// after `tap()`).
void main() {
  late MockNavigatorObserver observer;

  setUpAll(() {
    registerFallbackValue(_FakeRoute());
  });

  setUp(() {
    observer = MockNavigatorObserver();
  });

  Future<BuildContext> pumpButton(
      WidgetTester tester, void Function(BuildContext) onPressed) async {
    late BuildContext capturedContext;
    await tester.pumpWidget(MaterialApp(
      navigatorObservers: [observer],
      home: Builder(
        builder: (context) {
          capturedContext = context;
          return ElevatedButton(
            onPressed: () => onPressed(context),
            child: const Text('go'),
          );
        },
      ),
    ));
    return capturedContext;
  }

  WebViewScreen pushedWebViewScreen(BuildContext context) {
    // MaterialApp's own initial-route push notifies the observer too, so
    // exactly two didPush calls are expected: the app's home route, then
    // the WebViewScreen route pushed by the helper under test.
    final captured =
        verify(() => observer.didPush(captureAny(), any())).captured;
    expect(captured, hasLength(2),
        reason: 'expected the initial route plus one helper-pushed route');
    final route = captured.last as MaterialPageRoute;
    final widget = route.builder(context);
    expect(widget, isA<WebViewScreen>());
    return widget as WebViewScreen;
  }

  testWidgets('openDashboard pushes WebViewScreen with path /dashboard',
      (tester) async {
    final context = await pumpButton(tester, openDashboard);
    await tester.tap(find.byType(ElevatedButton));

    expect(pushedWebViewScreen(context).path, '/dashboard');
  });

  testWidgets('openLaporan pushes WebViewScreen with path /reports/hasil-culaan',
      (tester) async {
    final context = await pumpButton(tester, openLaporan);
    await tester.tap(find.byType(ElevatedButton));

    expect(pushedWebViewScreen(context).path, '/reports/hasil-culaan');
  });

  testWidgets(
      'openDataPengundi pushes WebViewScreen with path /reports/data-pengundi',
      (tester) async {
    final context = await pumpButton(tester, openDataPengundi);
    await tester.tap(find.byType(ElevatedButton));

    expect(pushedWebViewScreen(context).path, '/reports/data-pengundi');
  });

  testWidgets('openProfil pushes WebViewScreen with path /profile',
      (tester) async {
    final context = await pumpButton(tester, openProfil);
    await tester.tap(find.byType(ElevatedButton));

    expect(pushedWebViewScreen(context).path, '/profile');
  });
}
