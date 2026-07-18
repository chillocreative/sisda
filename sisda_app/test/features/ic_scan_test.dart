import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:sisda_app/data/remote/mobile_api.dart';
import 'package:sisda_app/models/api_result.dart';
import 'package:sisda_app/models/voter.dart';
import 'package:sisda_app/features/voters/ic_scan.dart';

class MockMobileApi extends Mock implements MobileApi {}

void main() {
  late MockMobileApi api;
  setUp(() => api = MockMobileApi());

  test('a found voter → IcFound', () async {
    when(() => api.showVoter('800101015555')).thenAnswer(
        (_) async => Voter.fromJson({'id': 1, 'nama': 'Ahmad', 'no_ic': '****'}));
    final r = await resolveIc('800101015555', api);
    expect(r, isA<IcFound>());
    expect((r as IcFound).voter.nama, 'Ahmad');
  });

  test('404 → IcNotFound', () async {
    when(() => api.showVoter(any())).thenThrow(const ApiException(
        status: 404, errors: {'no_ic': ['Pengundi tidak dijumpai.']}));
    expect(await resolveIc('999999999999', api), isA<IcNotFound>());
  });

  test('network error → IcError with a BM message (no English)', () async {
    when(() => api.showVoter(any())).thenThrow(
        const ApiException(status: null, errors: {}, rawMessage: 'SocketException'));
    final r = await resolveIc('800101015555', api);
    expect(r, isA<IcError>());
    expect((r as IcError).bmMessage, isNot(contains('SocketException')));
  });
}
