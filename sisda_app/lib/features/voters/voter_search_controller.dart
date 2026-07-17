import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../models/api_result.dart';
import '../../models/voter.dart';
import '../../providers.dart';

/// State for voter search. `const VoterSearchState()` is the empty/idle
/// default — no query typed yet.
class VoterSearchState {
  final bool loading;
  final List<Voter> results;
  final String? error;
  final bool tooShort;

  const VoterSearchState({
    this.loading = false,
    this.results = const [],
    this.error,
    this.tooShort = false,
  });
}

/// Holds voter-search state. NO debounce timer lives here — `searchNow` is
/// called directly by tests (deterministic) and by the screen's own
/// debounce `Timer` (see VoterSearchScreen), keeping this class free of
/// real delays so it stays synchronously testable.
class VoterSearchController extends Notifier<VoterSearchState> {
  int _generation = 0;

  @override
  VoterSearchState build() => const VoterSearchState();

  Future<void> searchNow(String q) async {
    final trimmed = q.trim();
    if (trimmed.length < 3) {
      _generation++; // a too-short call also supersedes any in-flight search
      state = const VoterSearchState(tooShort: true, results: []);
      return;
    }
    final gen = ++_generation;
    state = const VoterSearchState(loading: true);
    try {
      final results = await ref.read(mobileApiProvider).searchVoters(trimmed);
      if (gen != _generation) return; // superseded — drop stale results
      state = VoterSearchState(results: results);
    } on ApiException catch (e) {
      if (gen != _generation) return; // superseded — drop stale error
      state = VoterSearchState(error: e.firstError(), results: const []);
    }
  }
}

final voterSearchControllerProvider =
    NotifierProvider<VoterSearchController, VoterSearchState>(VoterSearchController.new);
