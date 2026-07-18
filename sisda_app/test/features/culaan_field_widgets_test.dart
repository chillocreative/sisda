import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sisda_app/features/culaan/culaan_form_spec.dart';
import 'package:sisda_app/features/culaan/culaan_options.dart';
import 'package:sisda_app/features/culaan/field_widgets.dart';

Widget _host(Widget child) => MaterialApp(home: Scaffold(body: SingleChildScrollView(child: child)));

void main() {
  testWidgets('locked text field shows **** and is read-only', (tester) async {
    const spec = FieldSpec(key: 'no_ic', label: 'No. KP', kind: FieldKind.number, required: true, sensitive: true);
    var changed = false;
    await tester.pumpWidget(_host(CulaanTextField(
        spec: spec, value: '****', locked: true, onChanged: (_) => changed = true)));
    expect(find.text('****'), findsOneWidget);
    await tester.enterText(find.byType(TextField), '9999'); // ignored
    expect(changed, isFalse);
  });

  testWidgets('dropdown emits selected value', (tester) async {
    const spec = FieldSpec(key: 'pemilik_rumah', label: 'Pemilik Rumah', kind: FieldKind.dropdown, required: true, optionsKey: 'pemilik_rumah');
    String? picked;
    await tester.pumpWidget(_host(CulaanDropdownField(
        spec: spec, value: '', options: const ['Sendiri', 'Sewa'], onChanged: (v) => picked = v)));
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Sewa').last);
    await tester.pumpAndSettle();
    expect(picked, 'Sewa');
  });

  testWidgets('multiselect toggles a value into/out of the list', (tester) async {
    const spec = FieldSpec(key: 'jenis_sumbangan', label: 'Jenis Sumbangan', kind: FieldKind.multiSelect, required: true, optionsKey: 'jenis_sumbangan');
    List<String>? out;
    await tester.pumpWidget(_host(CulaanMultiSelectField(
        spec: spec, value: const ['Barangan'], options: const ['Barangan', 'Tunai'], onChanged: (v) => out = v)));
    await tester.tap(find.text('Tunai'));
    await tester.pump();
    expect(out, ['Barangan', 'Tunai']);
  });

  testWidgets('cascading shows category headers and a hint when no pekerjaan', (tester) async {
    const spec = FieldSpec(key: 'jenis_pekerjaan', label: 'Jenis Pekerjaan', kind: FieldKind.cascading, required: true, optionsKey: 'jenis_pekerjaan');
    await tester.pumpWidget(_host(CulaanCascadingField(
        spec: spec, value: const [], groups: const [], onChanged: (_) {})));
    expect(find.textContaining('Pilih Pekerjaan'), findsOneWidget);

    await tester.pumpWidget(_host(CulaanCascadingField(
        spec: spec, value: const [], onChanged: (_) {},
        groups: const [PekerjaanCategory('Jenis Perkhidmatan', ['Awam'])])));
    expect(find.text('Jenis Perkhidmatan'), findsOneWidget);
    expect(find.text('Awam'), findsOneWidget);
  });
}
