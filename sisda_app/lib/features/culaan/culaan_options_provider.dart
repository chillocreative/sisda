import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers.dart';
import 'culaan_options.dart';

/// Fetches the Culaan form taxonomy. autoDispose so it re-fetches when the
/// form is re-opened (Master Data can change server-side between sessions).
final culaanOptionsProvider = FutureProvider.autoDispose<CulaanOptions>((ref) async {
  final api = ref.watch(mobileApiProvider);
  return CulaanOptions.fromJson(await api.culaanOptions());
});
