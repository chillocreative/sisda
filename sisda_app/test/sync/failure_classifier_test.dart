import 'package:flutter_test/flutter_test.dart';
import 'package:sisda_app/models/sync_status.dart';
import 'package:sisda_app/sync/failure_classifier.dart';

void main() {
  test('2xx is success — no bucket', () {
    expect(classifyStatus(200), isNull);
    expect(classifyStatus(201), isNull);
  });

  test('401 is auth', () {
    expect(classifyStatus(401), FailureBucket.auth);
  });

  test('403/409/422 are permanent', () {
    expect(classifyStatus(403), FailureBucket.permanent);
    expect(classifyStatus(409), FailureBucket.permanent);
    expect(classifyStatus(422), FailureBucket.permanent);
  });

  test('429 is TRANSIENT, not permanent — the one 4xx that must retry', () {
    expect(classifyStatus(429), FailureBucket.transient);
  });

  test('5xx is transient', () {
    expect(classifyStatus(500), FailureBucket.transient);
    expect(classifyStatus(503), FailureBucket.transient);
  });

  test('any transport exception is transient', () {
    expect(classifyException(Exception('SocketException: no signal')),
        FailureBucket.transient);
  });
}
