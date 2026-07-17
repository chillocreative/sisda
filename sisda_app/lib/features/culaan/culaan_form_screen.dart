import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../models/voter.dart';

/// STUB — Plan 2b-ii replaces this body with the real checklist-hub form.
/// It accepts the navigation arguments 2b-i's entry points pass, so the
/// navigation graph is complete and testable now.
///  - draftKey: re-open an existing draft (from Perlu Perhatian "Betulkan")
///  - prefillVoter: masked-create prefill (from voter detail)
class CulaanFormScreen extends ConsumerWidget {
  final String? draftKey;
  final Voter? prefillVoter;
  const CulaanFormScreen({super.key, this.draftKey, this.prefillVoter});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(title: const Text('Borang Culaan')),
      body: const Center(
        child: Padding(
          padding: EdgeInsets.all(24),
          child: Text('Borang Culaan akan tersedia tidak lama lagi.',
              textAlign: TextAlign.center),
        ),
      ),
    );
  }
}
