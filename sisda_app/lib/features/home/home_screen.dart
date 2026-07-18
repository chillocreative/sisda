import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers.dart';
import '../culaan/culaan_form_screen.dart';
import '../sync/draft_counts_provider.dart';
import '../voters/ic_scan.dart';
import '../voters/voter_search_screen.dart';

const _labelUtama = 'Utama';
const _hintCari = 'Cari nama atau No. IC…';
const _labelBorangBaru = 'Borang Culaan Baru';

/// The Utama tab: search-first home. A tappable search bar up top — with a
/// camera icon inside it for "search by photo" (OCR → IC lookup) — the
/// amber offline-queue strip when there are queued drafts, and quick entry
/// points. Replaces the old WebView-shell `lib/screens/home_screen.dart`.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final queued = ref.watch(draftCountsProvider).valueOrNull?.queued ?? 0;

    return Scaffold(
      appBar: AppBar(title: const Text(_labelUtama)),
      body: RefreshIndicator(
        // Wiring layer only — DateTime.now() must never leak into sync/.
        // draftCountsProvider watches the local DB directly, so it picks up
        // whatever the sync drains without a separate reload step.
        onRefresh: () =>
            ref.read(syncEngineProvider).syncNow(now: DateTime.now()),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            const _SearchBar(),
            if (queued > 0) ...[
              const SizedBox(height: 12),
              _QueueStrip(queued: queued),
            ],
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const CulaanFormScreen()),
              ),
              icon: const Icon(Icons.add),
              label: const Text(_labelBorangBaru),
            ),
          ],
        ),
      ),
    );
  }
}

/// A tappable, read-only-looking search field: tapping anywhere on the bar
/// opens `VoterSearchScreen`; the trailing camera icon runs
/// `startScanAndLookup` instead — "search by photo" is the same job as
/// typed search (OCR pulls the IC, which then feeds the same lookup).
class _SearchBar extends ConsumerWidget {
  const _SearchBar();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Material(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(28),
      child: InkWell(
        borderRadius: BorderRadius.circular(28),
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const VoterSearchScreen()),
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
          child: Row(
            children: [
              const Icon(Icons.search),
              const SizedBox(width: 12),
              const Expanded(
                child: Text(_hintCari, style: TextStyle(color: Colors.grey)),
              ),
              IconButton(
                icon: const Icon(Icons.camera_alt_outlined),
                tooltip: 'Cari guna gambar',
                onPressed: () => startScanAndLookup(context, ref),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// The amber offline-queue strip — visible only when there are queued
/// drafts, so offline state is never hidden but never falsely implied.
class _QueueStrip extends StatelessWidget {
  final int queued;
  const _QueueStrip({required this.queued});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.amber.shade100,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          const Icon(Icons.sync, color: Colors.black87),
          const SizedBox(width: 8),
          Expanded(child: Text('$queued culaan menunggu talian')),
        ],
      ),
    );
  }
}
