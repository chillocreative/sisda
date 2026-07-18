import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'culaan_form_spec.dart';
import 'culaan_options.dart';

/// Stateful so the controller is created ONCE — the section editor rebuilds on
/// every keystroke (setState in `_set`), and a controller rebuilt each frame
/// would jump the cursor to the end mid-typing. The controller is the source of
/// truth for text; `onChanged` flows edits up. Parent never pushes text back
/// down (the only source of `value` change is this field's own `onChanged`),
/// so no `didUpdateWidget` resync is needed.
class CulaanTextField extends StatefulWidget {
  final FieldSpec spec;
  final String value;
  final bool locked;
  final ValueChanged<String> onChanged;
  const CulaanTextField({
    super.key,
    required this.spec,
    required this.value,
    required this.onChanged,
    this.locked = false,
  });

  @override
  State<CulaanTextField> createState() => _CulaanTextFieldState();
}

class _CulaanTextFieldState extends State<CulaanTextField> {
  late final TextEditingController _controller =
      TextEditingController(text: widget.locked ? '****' : widget.value);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isNumber = widget.spec.kind == FieldKind.number;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: TextField(
        controller: _controller,
        readOnly: widget.locked,
        enabled: !widget.locked,
        maxLines: widget.spec.kind == FieldKind.multilineText ? 3 : 1,
        keyboardType: isNumber ? TextInputType.number : TextInputType.text,
        inputFormatters: isNumber ? [FilteringTextInputFormatter.digitsOnly] : null,
        decoration: InputDecoration(
          labelText: widget.spec.required ? '${widget.spec.label} *' : widget.spec.label,
          border: const OutlineInputBorder(),
          suffixIcon: widget.locked ? const Icon(Icons.lock_outline, size: 18) : null,
        ),
        onChanged: widget.locked ? null : widget.onChanged,
      ),
    );
  }
}

class CulaanDropdownField extends StatelessWidget {
  final FieldSpec spec;
  final String value;
  final List<String> options;
  final ValueChanged<String> onChanged;
  const CulaanDropdownField({
    super.key,
    required this.spec,
    required this.value,
    required this.options,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: DropdownButtonFormField<String>(
        initialValue: value.isEmpty ? null : value,
        isExpanded: true,
        decoration: InputDecoration(
          labelText: spec.required ? '${spec.label} *' : spec.label,
          border: const OutlineInputBorder(),
        ),
        items: [
          for (final o in options) DropdownMenuItem(value: o, child: Text(o)),
        ],
        onChanged: (v) => onChanged(v ?? ''),
      ),
    );
  }
}

class CulaanMultiSelectField extends StatelessWidget {
  final FieldSpec spec;
  final List<String> value;
  final List<String> options;
  final ValueChanged<List<String>> onChanged;
  const CulaanMultiSelectField({
    super.key,
    required this.spec,
    required this.value,
    required this.options,
    required this.onChanged,
  });

  void _toggle(String option) {
    final next = List<String>.from(value);
    next.contains(option) ? next.remove(option) : next.add(option);
    onChanged(next);
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(spec.required ? '${spec.label} *' : spec.label,
              style: Theme.of(context).textTheme.titleSmall),
          if (options.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: Text('Tiada pilihan tersedia.', style: TextStyle(color: Colors.grey)),
            ),
          Wrap(
            spacing: 8,
            children: [
              for (final o in options)
                FilterChip(
                  label: Text(o),
                  selected: value.contains(o),
                  onSelected: (_) => _toggle(o),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class CulaanCascadingField extends StatelessWidget {
  final FieldSpec spec;
  final List<String> value;
  final List<PekerjaanCategory> groups;
  final ValueChanged<List<String>> onChanged;
  const CulaanCascadingField({
    super.key,
    required this.spec,
    required this.value,
    required this.groups,
    required this.onChanged,
  });

  void _toggle(String item) {
    final next = List<String>.from(value);
    next.contains(item) ? next.remove(item) : next.add(item);
    onChanged(next);
  }

  @override
  Widget build(BuildContext context) {
    if (groups.isEmpty) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 8),
        child: Text('Pilih Pekerjaan dahulu untuk memaparkan jenis pekerjaan.',
            style: TextStyle(color: Colors.grey)),
      );
    }
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(spec.required ? '${spec.label} *' : spec.label,
              style: Theme.of(context).textTheme.titleSmall),
          for (final g in groups) ...[
            Padding(
              padding: const EdgeInsets.only(top: 8, bottom: 4),
              child: Text(g.category,
                  style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
            ),
            Wrap(
              spacing: 8,
              children: [
                for (final item in g.items)
                  FilterChip(
                    label: Text(item),
                    selected: value.contains(item),
                    onSelected: (_) => _toggle(item),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
