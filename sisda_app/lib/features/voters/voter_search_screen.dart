import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'voter_search_controller.dart';
import 'voter_detail_screen.dart';

/// Name-first voter search. The `nama` field matches as a substring; a full
/// 12-digit `no_ic` also matches exactly (partial-IC search deliberately
/// returns nothing for a `user`-role viewer — anti-oracle design, see
/// task brief). Debounces input here (a `Timer`, NOT inside the controller)
/// so type-ahead doesn't trip the route's 30/min throttle.
class VoterSearchScreen extends ConsumerStatefulWidget {
  const VoterSearchScreen({super.key});

  @override
  ConsumerState<VoterSearchScreen> createState() => _VoterSearchScreenState();
}

class _VoterSearchScreenState extends ConsumerState<VoterSearchScreen> {
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  void _onChanged(String q) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      ref.read(voterSearchControllerProvider.notifier).searchNow(q);
    });
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(voterSearchControllerProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Cari Pengundi')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              onChanged: _onChanged,
              decoration: const InputDecoration(
                hintText: 'Cari nama atau No. IC…',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.search),
              ),
            ),
          ),
          Expanded(child: _buildBody(state)),
        ],
      ),
    );
  }

  Widget _buildBody(VoterSearchState state) {
    if (state.loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (state.error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Text(
            state.error!,
            style: const TextStyle(color: Colors.red),
            textAlign: TextAlign.center,
          ),
        ),
      );
    }
    if (state.tooShort) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Text('Sila masukkan sekurang-kurangnya 3 aksara.'),
        ),
      );
    }
    if (state.results.isEmpty) {
      return const Center(child: Text('Tiada pengundi dijumpai.'));
    }
    return ListView.builder(
      itemCount: state.results.length,
      itemBuilder: (context, index) {
        final voter = state.results[index];
        return ListTile(
          title: Text(voter.nama),
          subtitle: Text(voter.field('kadun')),
          onTap: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => VoterDetailScreen(voter: voter)),
          ),
        );
      },
    );
  }
}
