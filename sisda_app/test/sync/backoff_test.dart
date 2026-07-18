import 'package:flutter_test/flutter_test.dart';
import 'package:sisda_app/sync/backoff.dart';

void main() {
  final now = DateTime.utc(2026, 7, 18, 10, 0);

  test('backoff grows with attempts', () {
    final a1 = nextRetryAt(attempts: 1, now: now);
    final a2 = nextRetryAt(attempts: 2, now: now);
    final a3 = nextRetryAt(attempts: 3, now: now);
    expect(a2.isAfter(a1), isTrue);
    expect(a3.isAfter(a2), isTrue);
  });

  test('backoff is capped at ~5 minutes', () {
    final far = nextRetryAt(attempts: 20, now: now);
    expect(far.difference(now).inSeconds, lessThanOrEqualTo(300));
  });
}
