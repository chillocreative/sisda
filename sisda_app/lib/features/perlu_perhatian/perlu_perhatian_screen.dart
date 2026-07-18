import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/local/database.dart';
import '../../models/culaan_draft.dart';
import '../../models/sync_status.dart';
import '../../providers.dart';
import '../culaan/culaan_form_screen.dart';

const _labelPerluPerhatian = 'Perlu Perhatian';
const _emptyState = 'Tiada culaan yang perlu perhatian.';
const _labelBetulkan = 'Betulkan';
const _labelBuang = 'Buang';
const _dialogTitle = 'Buang culaan ini?';
const _dialogBody = 'Tindakan ini tidak boleh dibatalkan.';
const _labelBatal = 'Batal';

/// The Perlu Perhatian tab body: a live list of `failed` Culaan drafts
/// (drafts that permanently failed to sync — out-of-Parlimen, duplicate,
/// validation), each with its BM failure reason, a "Betulkan" re-entry into
/// CulaanFormScreen, and a "Buang" (discard) action.
class PerluPerhatianScreen extends ConsumerWidget {
  const PerluPerhatianScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final db = ref.read(appDatabaseProvider);
    final draftsAsync = ref.watch(_failedDraftsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text(_labelPerluPerhatian)),
      body: draftsAsync.when(
        data: (drafts) {
          if (drafts.isEmpty) {
            return const Center(child: Text(_emptyState));
          }
          return ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: drafts.length,
            itemBuilder: (context, index) =>
                _FailedDraftCard(draft: drafts[index], db: db),
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, stack) => const Center(
          child: Padding(
            padding: EdgeInsets.all(24),
            child: Text('Ralat memuatkan senarai. Sila cuba lagi.', textAlign: TextAlign.center),
          ),
        ),
      ),
    );
  }
}

final _failedDraftsProvider = StreamProvider<List<CulaanDraft>>((ref) {
  final db = ref.watch(appDatabaseProvider);
  return db
      .watchAll()
      .map((drafts) => drafts.where((d) => d.status == SyncStatus.failed).toList());
});

class _FailedDraftCard extends StatelessWidget {
  final CulaanDraft draft;
  final AppDatabase db;
  const _FailedDraftCard({required this.draft, required this.db});

  @override
  Widget build(BuildContext context) {
    final rawNama = draft.fields['nama'];
    final nama = rawNama is String ? rawNama : 'Pengundi';
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(nama, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 4),
            Text(
              draft.failureReason ?? '',
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
            const SizedBox(height: 12),
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                TextButton(
                  onPressed: () => _confirmDiscard(context),
                  child: const Text(_labelBuang),
                ),
                const SizedBox(width: 8),
                FilledButton(
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) =>
                          CulaanFormScreen(draftKey: draft.idempotencyKey),
                    ),
                  ),
                  child: const Text(_labelBetulkan),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _confirmDiscard(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text(_dialogTitle),
        content: const Text(_dialogBody),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text(_labelBatal),
          ),
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text(_labelBuang),
          ),
        ],
      ),
    );
    if (confirmed == true) {
      await db.deleteDraft(draft.idempotencyKey);
    }
  }
}
