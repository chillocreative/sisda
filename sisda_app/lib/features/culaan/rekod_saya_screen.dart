import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../models/api_result.dart';
import '../../providers.dart';
import 'culaan_form_screen.dart';

const _labelCulaan = 'Culaan';
const _labelBorangBaru = 'Borang Culaan Baru';
const _emptyState = 'Tiada rekod culaan lagi.';
const _genericError = 'Ralat tidak dijangka. Sila cuba lagi.';

/// culaanMine() records fetched fresh on build, refreshed by pull-to-refresh
/// (and re-fetched whenever the provider is invalidated).
final _culaanMineProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) {
  final api = ref.watch(mobileApiProvider);
  return api.culaanMine();
});

/// The Culaan tab body: the user's server-side submissions (masked fields
/// shown verbatim — never reconstructed), a "Borang Culaan Baru" entry
/// point, and pull-to-refresh that drains the offline sync queue before
/// reloading the list. Wired into MainShell's Culaan tab.
class RekodSayaScreen extends ConsumerWidget {
  const RekodSayaScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final recordsAsync = ref.watch(_culaanMineProvider);

    Future<void> onRefresh() async {
      // Wiring layer only — DateTime.now() must never leak into sync/.
      await ref.read(syncEngineProvider).syncNow(now: DateTime.now());
      ref.invalidate(_culaanMineProvider);
      await ref.read(_culaanMineProvider.future);
    }

    return Scaffold(
      appBar: AppBar(title: const Text(_labelCulaan)),
      body: RefreshIndicator(
        onRefresh: onRefresh,
        child: recordsAsync.when(
          data: (records) {
            if (records.isEmpty) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 120),
                  Center(child: Text(_emptyState)),
                ],
              );
            }
            return ListView.builder(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.symmetric(vertical: 8),
              itemCount: records.length,
              itemBuilder: (context, index) =>
                  _RecordTile(record: records[index]),
            );
          },
          loading: () => ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            children: const [
              SizedBox(height: 120),
              Center(child: CircularProgressIndicator()),
            ],
          ),
          error: (error, stack) => ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              const SizedBox(height: 120),
              Center(
                child: Text(
                  error is ApiException ? error.firstError() : _genericError,
                ),
              ),
            ],
          ),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const CulaanFormScreen()),
        ),
        icon: const Icon(Icons.add),
        label: const Text(_labelBorangBaru),
      ),
    );
  }
}

/// A single culaanMine() record. `nama` is the voter name; every other
/// field (e.g. no_ic) may be server-masked as '****' — rendered verbatim,
/// never reconstructed client-side.
class _RecordTile extends StatelessWidget {
  final Map<String, dynamic> record;
  const _RecordTile({required this.record});

  @override
  Widget build(BuildContext context) {
    final rawNama = record['nama'];
    final nama = rawNama is String ? rawNama : '—';
    final noIc = record['no_ic'] as String?;
    return ListTile(
      title: Text(nama),
      subtitle: noIc == null ? null : Text('No. IC: $noIc'),
    );
  }
}
