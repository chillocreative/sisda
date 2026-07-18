import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'culaan_form_spec.dart';
import 'culaan_options.dart';
import 'culaan_options_provider.dart';
import 'field_widgets.dart';

/// Generic, spec-driven editor for ANY one of the 7 Culaan sections. Renders
/// `section.fields` via the shared field widgets (Task 3) and pops the merged
/// map for just this section back to the checklist hub on "Simpan" — the DRY
/// core that avoids a bespoke screen per section.
class SectionEditorScreen extends ConsumerStatefulWidget {
  final SectionSpec section;
  final Map<String, dynamic> initialFields;
  final bool locked; // masked-create: sensitive fields read-only
  const SectionEditorScreen({
    super.key,
    required this.section,
    required this.initialFields,
    this.locked = false,
  });

  @override
  ConsumerState<SectionEditorScreen> createState() => _SectionEditorScreenState();
}

class _SectionEditorScreenState extends ConsumerState<SectionEditorScreen> {
  late final Map<String, dynamic> _work;

  bool get _needsOptions =>
      widget.section.fields.any((f) => f.optionsKey != null);

  @override
  void initState() {
    super.initState();
    // Copy only this section's keys (plus any *_lain companions) into the work map.
    _work = {};
    for (final f in widget.section.fields) {
      _work[f.key] = widget.initialFields[f.key] ??
          (f.isArray ? <String>[] : '');
      if (f.lainKey != null) {
        _work[f.lainKey!] = widget.initialFields[f.lainKey!] ?? '';
      }
    }
  }

  bool _isLocked(FieldSpec f) => widget.locked && kSensitiveFields.contains(f.key);

  void _set(String key, dynamic value) => setState(() => _work[key] = value);

  void _save() {
    // Drop locked sensitive keys so the draft keeps its '****'/existing value.
    final out = <String, dynamic>{};
    _work.forEach((k, v) {
      final spec = widget.section.fields
          .cast<FieldSpec?>()
          .firstWhere((f) => f?.key == k, orElse: () => null);
      if (spec != null && _isLocked(spec)) return;
      out[k] = v;
    });
    Navigator.pop(context, out);
  }

  @override
  Widget build(BuildContext context) {
    final body = _needsOptions
        ? ref.watch(culaanOptionsProvider).when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (_, __) => _OptionsError(onRetry: () => ref.invalidate(culaanOptionsProvider)),
              data: (options) => _form(options),
            )
        : _form(null);

    return Scaffold(
      appBar: AppBar(title: Text(widget.section.title)),
      body: body,
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: FilledButton(onPressed: _save, child: const Text('Simpan')),
        ),
      ),
    );
  }

  Widget _form(CulaanOptions? options) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        for (final f in widget.section.fields) ..._fieldWidgets(f, options),
      ],
    );
  }

  List<Widget> _fieldWidgets(FieldSpec f, CulaanOptions? options) {
    final widgets = <Widget>[];
    switch (f.kind) {
      case FieldKind.text:
      case FieldKind.multilineText:
      case FieldKind.number:
        widgets.add(CulaanTextField(
          key: ValueKey(f.key), // stable identity so the controller stays bound
          spec: f,
          value: (_work[f.key] ?? '').toString(),
          locked: _isLocked(f),
          onChanged: (v) => _set(f.key, v),
        ));
        break;
      case FieldKind.dropdown:
        widgets.add(CulaanDropdownField(
          key: ValueKey(f.key),
          spec: f,
          value: (_work[f.key] ?? '').toString(),
          options: options?.optionsForKey(f.optionsKey!) ?? const [],
          onChanged: (v) {
            _set(f.key, v);
            // Changing pekerjaan invalidates jenis_pekerjaan selections.
            if (f.key == 'pekerjaan') _set('jenis_pekerjaan', <String>[]);
          },
        ));
        break;
      case FieldKind.multiSelect:
        widgets.add(CulaanMultiSelectField(
          key: ValueKey(f.key),
          spec: f,
          value: List<String>.from(_work[f.key] as List? ?? const []),
          options: options?.optionsForKey(f.optionsKey!) ?? const [],
          onChanged: (v) => _set(f.key, v),
        ));
        break;
      case FieldKind.cascading:
        final pekerjaan = (_work['pekerjaan'] ?? '').toString();
        widgets.add(CulaanCascadingField(
          key: ValueKey(f.key),
          spec: f,
          value: List<String>.from(_work[f.key] as List? ?? const []),
          groups: options?.jenisPekerjaanFor(pekerjaan) ?? const [],
          onChanged: (v) => _set(f.key, v),
        ));
        break;
    }
    // Reveal the *_lain companion when a "Lain-lain" option is selected.
    if (f.lainKey != null && hasLainSelected(f, _work[f.key])) {
      widgets.add(CulaanTextField(
        key: ValueKey(f.lainKey!),
        spec: FieldSpec(
            key: f.lainKey!, label: '${f.label} (Lain-lain)', kind: FieldKind.text),
        value: (_work[f.lainKey!] ?? '').toString(),
        onChanged: (v) => _set(f.lainKey!, v),
      ));
    }
    return widgets;
  }
}

class _OptionsError extends StatelessWidget {
  final VoidCallback onRetry;
  const _OptionsError({required this.onRetry});
  @override
  Widget build(BuildContext context) => Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text('Gagal memuat pilihan borang. Sila cuba semula bila ada talian.',
                  textAlign: TextAlign.center),
            ),
            OutlinedButton(onPressed: onRetry, child: const Text('Cuba Semula')),
          ],
        ),
      );
}
