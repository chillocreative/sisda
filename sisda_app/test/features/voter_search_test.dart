import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mocktail/mocktail.dart';
import 'package:sisda_app/data/remote/mobile_api.dart';
import 'package:sisda_app/models/api_result.dart';
import 'package:sisda_app/models/voter.dart';
import 'package:sisda_app/providers.dart';
import 'package:sisda_app/features/voters/voter_search_controller.dart';

class MockMobileApi extends Mock implements MobileApi {}

void main() {
  late MockMobileApi api;
  setUp(() => api = MockMobileApi());

  ProviderContainer container() =>
      ProviderContainer(overrides: [mobileApiProvider.overrideWithValue(api)]);

  test('query under 3 chars sets tooShort and never calls the API', () async {
    final c = container();
    await c.read(voterSearchControllerProvider.notifier).searchNow('Ah');
    final s = c.read(voterSearchControllerProvider);
    expect(s.tooShort, isTrue);
    expect(s.results, isEmpty);
    verifyNever(() => api.searchVoters(any()));
  });

  test('valid query populates results', () async {
    when(() => api.searchVoters('Ahmad')).thenAnswer((_) async =>
        [Voter.fromJson({'id': 1, 'nama': 'Ahmad bin Ali', 'no_ic': '****'})]);
    final c = container();
    await c.read(voterSearchControllerProvider.notifier).searchNow('Ahmad');
    final s = c.read(voterSearchControllerProvider);
    expect(s.results.single.nama, 'Ahmad bin Ali');
    expect(s.error, isNull);
    expect(s.tooShort, isFalse);
  });

  test('ApiException surfaces BM firstError and clears results, no English', () async {
    when(() => api.searchVoters(any())).thenThrow(const ApiException(
        status: 500, errors: {}, rawMessage: 'Server Error'));
    final c = container();
    await c.read(voterSearchControllerProvider.notifier).searchNow('Ahmad');
    final s = c.read(voterSearchControllerProvider);
    expect(s.error, isNotEmpty);
    expect(s.error, isNot(contains('Server Error'))); // never leak rawMessage
    expect(s.results, isEmpty);
  });
}
