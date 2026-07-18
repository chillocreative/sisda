import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:sisda_app/data/remote/mobile_api.dart';
import 'package:sisda_app/features/culaan/culaan_form_spec.dart';
import 'package:sisda_app/features/culaan/section_editor_screen.dart';
import 'package:sisda_app/providers.dart';

class MockMobileApi extends Mock implements MobileApi {}

void main() {
  late MockMobileApi api;
  setUp(() {
    api = MockMobileApi();
    when(() => api.culaanOptions()).thenAnswer((_) async => {
          'pekerjaan': ['Kerajaan', 'Swasta'],
          'jenis_pekerjaan': {
            'Kerajaan': [
              {'category': 'Perkhidmatan', 'items': ['Awam']}
            ],
            'Swasta': [
              {'category': 'Perniagaan', 'items': ['Peniaga']}
            ],
          },
          'pemilik_rumah': ['Sendiri', 'Lain-lain'],
          'jenis_sumbangan': ['Barangan'],
          'tujuan_sumbangan': ['Pendidikan'],
          'bantuan_lain': ['JKM'],
        });
  });

  Future<Map<String, dynamic>?> editSection(
      WidgetTester tester, SectionSpec section, Map<String, dynamic> initial,
      {bool locked = false,
      void Function(Map<String, dynamic>?)? onResult}) async {
    Map<String, dynamic>? result;
    await tester.pumpWidget(ProviderScope(
      overrides: [mobileApiProvider.overrideWithValue(api)],
      child: MaterialApp(
        home: Builder(
          builder: (context) => Scaffold(
            body: Center(
              child: ElevatedButton(
                onPressed: () async {
                  result = await Navigator.push<Map<String, dynamic>>(
                    context,
                    MaterialPageRoute(
                      builder: (_) => SectionEditorScreen(
                          section: section, initialFields: initial, locked: locked),
                    ),
                  );
                  onResult?.call(result);
                },
                child: const Text('open'),
              ),
            ),
          ),
        ),
      ),
    ));
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    return result;
  }

  testWidgets('core section returns only its edited keys', (tester) async {
    final peribadi = sectionsFor(false).firstWhere((s) => s.key == 'peribadi');
    Map<String, dynamic>? result;
    await editSection(tester, peribadi, {'nama': ''},
        onResult: (r) => result = r);
    await tester.enterText(find.widgetWithText(TextField, 'Nama *'), 'Ahmad');
    await tester.tap(find.text('Simpan'));
    await tester.pumpAndSettle();
    // The pushed screen returns {nama: 'Ahmad', ...other peribadi keys} only —
    // no keys from other sections leak in.
    expect(result, isNotNull);
    expect(result!['nama'], 'Ahmad');
    expect(
      result!.keys.toSet(),
      peribadi.fields.map((f) => f.key).toSet(),
    );
  });

  testWidgets('locked sensitive field renders **** and read-only', (tester) async {
    final peribadi = sectionsFor(false).firstWhere((s) => s.key == 'peribadi');
    await editSection(tester, peribadi, {'no_ic': '****'}, locked: true);
    expect(find.text('****'), findsWidgets);
    expect(find.byIcon(Icons.lock_outline), findsWidgets);
  });

  testWidgets('locked sensitive fields are dropped from the returned map', (tester) async {
    final peribadi = sectionsFor(false).firstWhere((s) => s.key == 'peribadi');
    Map<String, dynamic>? result;
    await editSection(tester, peribadi, {'nama': 'Ali', 'no_ic': '****'},
        locked: true, onResult: (r) => result = r);
    await tester.tap(find.text('Simpan'));
    await tester.pumpAndSettle();
    expect(result, isNotNull);
    expect(result!.containsKey('no_ic'), isFalse);
    expect(result!.containsKey('umur'), isFalse);
    expect(result!.containsKey('no_tel'), isFalse);
    expect(result!.containsKey('bangsa'), isFalse);
    expect(result!['nama'], 'Ali');
  });

  testWidgets('cascading Jenis Pekerjaan appears after picking Pekerjaan', (tester) async {
    final isiRumah = sectionsFor(true).firstWhere((s) => s.key == 'isi_rumah');
    await editSection(tester, isiRumah, {});
    expect(find.textContaining('Pilih Pekerjaan'), findsOneWidget);
    await tester.tap(find.byType(DropdownButtonFormField<String>).first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Kerajaan').last);
    await tester.pumpAndSettle();
    expect(find.text('Perkhidmatan'), findsOneWidget); // category header
    expect(find.text('Awam'), findsOneWidget);
  });

  testWidgets('changing Pekerjaan clears previously selected Jenis Pekerjaan', (tester) async {
    final isiRumah = sectionsFor(true).firstWhere((s) => s.key == 'isi_rumah');
    Map<String, dynamic>? result;
    await editSection(
      tester,
      isiRumah,
      {'pekerjaan': 'Kerajaan', 'jenis_pekerjaan': ['Awam']},
      onResult: (r) => result = r,
    );
    expect(find.text('Awam'), findsOneWidget);
    // Switch pekerjaan to Swasta — the old jenis_pekerjaan groups (and any
    // selection that no longer exists under the new employer type) must clear.
    await tester.tap(find.byType(DropdownButtonFormField<String>).first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Swasta').last);
    await tester.pumpAndSettle();
    expect(find.text('Awam'), findsNothing);
    expect(find.text('Peniaga'), findsOneWidget);
    await tester.tap(find.text('Simpan'));
    await tester.pumpAndSettle();
    expect(result!['jenis_pekerjaan'], isEmpty);
  });

  testWidgets('selecting a Lain-lain option reveals the *_lain text field', (tester) async {
    final isiRumah = sectionsFor(true).firstWhere((s) => s.key == 'isi_rumah');
    await editSection(tester, isiRumah, {});
    await tester.tap(find.byType(DropdownButtonFormField<String>).last); // pemilik_rumah
    await tester.pumpAndSettle();
    await tester.tap(find.text('Lain-lain').last);
    await tester.pumpAndSettle();
    expect(find.widgetWithText(TextField, 'Pemilik Rumah (Lain-lain)'), findsOneWidget);
  });

  testWidgets('options load failure shows BM error with Cuba Semula', (tester) async {
    final failingApi = MockMobileApi();
    when(() => failingApi.culaanOptions()).thenThrow(Exception('network'));
    final isiRumah = sectionsFor(true).firstWhere((s) => s.key == 'isi_rumah');
    await tester.pumpWidget(ProviderScope(
      overrides: [mobileApiProvider.overrideWithValue(failingApi)],
      child: MaterialApp(
        home: SectionEditorScreen(
            section: isiRumah, initialFields: const {}, locked: false),
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('Cuba Semula'), findsOneWidget);
  });
}
