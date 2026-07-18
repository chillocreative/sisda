/// Exponential backoff for a transient retry: 2^attempts seconds, capped.
/// Pure — pass `now` in (never call DateTime.now() here, for testability).
DateTime nextRetryAt({
  required int attempts,
  required DateTime now,
  Duration cap = const Duration(minutes: 5),
}) {
  final seconds = (1 << attempts.clamp(0, 30)); // 2^attempts, guarded
  final delay = Duration(seconds: seconds);
  return now.add(delay > cap ? cap : delay);
}
