import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../models/voter.dart';
import '../culaan/culaan_form_screen.dart';

/// Read-only voter detail view. Shared destination of voter search (Task 3)
/// and IC scan (Task 4). Fields arrive pre-masked (`'****'`) from the API
/// for viewers who cannot unmask — this screen never interprets or edits
/// them, only displays `voter.field(key)` verbatim. An absent/empty field
/// (unknown, not zero) renders as '—'.
class VoterDetailScreen extends ConsumerWidget {
  final Voter voter;
  const VoterDetailScreen({super.key, required this.voter});

  /// (field key, Bahasa Melayu label). 'nama' is shown prominently above
  /// this list, not repeated in it, so it doesn't collide with the header.
  static const _rows = [
    ('no_ic', 'No. IC'),
    ('umur', 'Umur'),
    ('no_tel', 'No. Tel'),
    ('bangsa', 'Bangsa'),
    ('alamat', 'Alamat'),
    ('poskod', 'Poskod'),
    ('negeri', 'Negeri'),
    ('bandar', 'Bandar'),
    ('parlimen', 'Parlimen'),
    ('kadun', 'DUN'),
    ('mpkk', 'MPKK'),
    ('daerah_mengundi', 'Daerah Mengundi'),
    ('lokaliti', 'Lokaliti'),
    ('keahlian_parti', 'Keahlian Parti'),
    ('kecenderungan_politik', 'Kecenderungan Politik'),
    ('status_pengundi', 'Status Pengundi'),
    ('nota', 'Nota'),
  ];

  static String _display(String value) => value.isEmpty ? '—' : value;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      // Deliberately generic — NOT voter.nama — so the name renders exactly
      // once, in the body header below, rather than also in the AppBar.
      appBar: AppBar(title: const Text('Butiran Pengundi')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Text(
                voter.nama,
                style: Theme.of(context).textTheme.headlineSmall,
              ),
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: SingleChildScrollView(
              // Column (not ListView.builder) so every row is built eagerly
              // — a lazy list only builds items near the viewport, which
              // would silently drop fields that scroll off-screen.
              child: Column(
                children: [
                  for (final (key, label) in _rows) ...[
                    ListTile(
                      title: Text(
                        label,
                        style: Theme.of(context).textTheme.labelMedium,
                      ),
                      subtitle: Text(_display(voter.field(key))),
                    ),
                    const Divider(height: 1),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.all(16),
        child: SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => CulaanFormScreen(prefillVoter: voter),
              ),
            ),
            child: const Text('Culaan baru untuk pengundi ini'),
          ),
        ),
      ),
    );
  }
}
