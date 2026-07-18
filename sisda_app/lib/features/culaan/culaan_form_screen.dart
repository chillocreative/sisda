import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../models/culaan_draft.dart';
import '../../models/sync_status.dart';
import '../../models/voter.dart';
import '../../providers.dart';
import 'culaan_draft_loader.dart';
import 'culaan_form_spec.dart';
import 'section_editor_screen.dart';

class CulaanFormScreen extends ConsumerStatefulWidget {
  final String? draftKey;
  final Voter? prefillVoter;
  const CulaanFormScreen({super.key, this.draftKey, this.prefillVoter});

  @override
  ConsumerState<CulaanFormScreen> createState() => _CulaanFormScreenState();
}

class _CulaanFormScreenState extends ConsumerState<CulaanFormScreen> {
  CulaanDraft? _draft;
  Object? _loadError;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final draft = await loadOrCreateDraft(
        store: ref.read(appDatabaseProvider),
        idGenerator: ref.read(idGeneratorProvider),
        now: DateTime.now(),
        draftKey: widget.draftKey,
        prefillVoter: widget.prefillVoter,
      );
      if (mounted) setState(() => _draft = draft);
    } catch (e) {
      if (mounted) setState(() => _loadError = e);
    }
  }

  bool get _locked => (_draft?.lockedSourceId) != null;
  Map<String, dynamic> get _fields => _draft?.fields ?? const {};

  Future<void> _persist(CulaanDraft next) async {
    await ref.read(appDatabaseProvider).upsertDraft(next);
    if (mounted) setState(() => _draft = next);
  }

  Future<void> _openSection(SectionSpec section) async {
    final result = await Navigator.push<Map<String, dynamic>>(
      context,
      MaterialPageRoute(
        builder: (_) => SectionEditorScreen(
          section: section,
          initialFields: _fields,
          locked: _locked,
        ),
      ),
    );
    if (result == null || _draft == null) return;
    final merged = {..._fields, ...result};
    await _persist(_draft!.copyWith(fields: merged, updatedAt: DateTime.now()));
  }

  Future<void> _toggleSumbangan(bool value) async {
    if (_draft == null) return;
    var fields = _fields;
    if (!value) {
      // Strip the two gated sections' keys so the payload stays clean.
      fields = {..._fields};
      for (final s in [_section('isi_rumah'), _section('bantuan')]) {
        for (final f in s.fields) {
          fields.remove(f.key);
          if (f.lainKey != null) fields.remove(f.lainKey);
        }
      }
    }
    await _persist(_draft!.copyWith(
        hasSumbangan: value, fields: fields, updatedAt: DateTime.now()));
  }

  SectionSpec _section(String key) =>
      sectionsFor(true).firstWhere((s) => s.key == key);

  Future<void> _submit() async {
    final draft = _draft;
    if (draft == null) return;
    final missing = incompleteRequiredSections(_fields, draft.hasSumbangan);
    if (missing.isNotEmpty) {
      final names = missing.map((s) => s.title).join(', ');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Sila lengkapkan: $names')),
      );
      return;
    }
    final now = DateTime.now();
    final queued = draft.copyWith(
      status: SyncStatus.queued,
      failureReason: null, // clear any prior failure → leaves Perlu Perhatian
      updatedAt: now,
    );
    await ref.read(appDatabaseProvider).upsertDraft(queued);
    await ref.read(syncEngineProvider).syncNow(now: DateTime.now());
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Culaan disimpan. Ia akan dihantar bila ada talian.')),
    );
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    if (_loadError != null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Borang Culaan')),
        body: const Center(
          child: Padding(
            padding: EdgeInsets.all(24),
            child: Text('Draf ini tidak lagi wujud. Sila cari semula pengundi.',
                textAlign: TextAlign.center),
          ),
        ),
      );
    }
    if (_draft == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final draft = _draft!;
    final sections = sectionsFor(draft.hasSumbangan);
    final requiredSections = sections.where((s) => !s.isOptional).toList();
    final done = requiredSections.where((s) => isSectionComplete(s, _fields)).length;
    final nama = (_fields['nama'] ?? '').toString();

    return Scaffold(
      appBar: AppBar(title: Text(nama.isEmpty ? 'Culaan Baru' : nama)),
      body: ListView(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Text('$done daripada ${requiredSections.length} bahagian wajib siap',
                style: Theme.of(context).textTheme.titleMedium),
          ),
          for (final s in sectionsFor(false)) _sectionTile(s),
          SwitchListTile(
            title: const Text('Ada Sumbangan'),
            value: draft.hasSumbangan,
            onChanged: _toggleSumbangan,
          ),
          if (draft.hasSumbangan) ...[
            _sectionTile(_section('isi_rumah')),
            _sectionTile(_section('bantuan')),
          ],
          const SizedBox(height: 12),
          Padding(
            padding: const EdgeInsets.all(16),
            child: FilledButton(onPressed: _submit, child: const Text('Hantar')),
          ),
        ],
      ),
    );
  }

  Widget _sectionTile(SectionSpec s) {
    final Widget trailing;
    if (s.isOptional) {
      trailing = const Text('pilihan');
    } else if (isSectionComplete(s, _fields)) {
      trailing = const Icon(Icons.check_circle, color: Colors.green);
    } else {
      trailing = const Text('Belum diisi ›');
    }
    return ListTile(
      title: Text(s.title),
      trailing: trailing,
      onTap: () => _openSection(s),
    );
  }
}
