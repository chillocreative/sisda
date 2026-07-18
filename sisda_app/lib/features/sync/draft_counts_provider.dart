import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../models/sync_status.dart';
import '../../providers.dart';

class DraftCounts {
  final int queued;
  final int failed;
  const DraftCounts({required this.queued, required this.failed});
}

/// Live counts over the local draft store, for the home queue strip
/// ("N culaan menunggu talian") and the Perlu Perhatian badge.
final draftCountsProvider = StreamProvider<DraftCounts>((ref) {
  final db = ref.watch(appDatabaseProvider);
  return db.watchAll().map((drafts) => DraftCounts(
        queued: drafts
            .where((d) => d.status == SyncStatus.queued || d.status == SyncStatus.syncing)
            .length,
        failed: drafts.where((d) => d.status == SyncStatus.failed).length,
      ));
});
