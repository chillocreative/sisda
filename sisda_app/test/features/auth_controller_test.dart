import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mocktail/mocktail.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sisda_app/data/remote/api_config.dart';
import 'package:sisda_app/data/remote/mobile_api.dart';
import 'package:sisda_app/models/api_result.dart';
import 'package:sisda_app/providers.dart';
import 'package:sisda_app/features/auth/auth_controller.dart';

class MockMobileApi extends Mock implements MobileApi {}

void main() {
  late MockMobileApi api;
  late ApiConfig cfg;

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    api = MockMobileApi();
    cfg = ApiConfig(baseUrl: 'http://x');
  });

  ProviderContainer container() => ProviderContainer(overrides: [
        mobileApiProvider.overrideWithValue(api),
        apiConfigProvider.overrideWithValue(cfg),
      ]);

  test('login success sets loggedIn and stores the token into ApiConfig', () async {
    when(() => api.login(any(), any()))
        .thenAnswer((_) async => const LoginResult('1|abc', {'id': 1, 'role': 'user'}));
    final c = container();
    await c.read(authControllerProvider.notifier).login('0123456789', 'pw');
    final AuthState state = c.read(authControllerProvider).value!;
    expect(state.loggedIn, isTrue);
    expect(cfg.token, '1|abc');
  });

  test('login 422 surfaces the BM error and stays logged out', () async {
    when(() => api.login(any(), any())).thenThrow(const ApiException(
        status: 422, errors: {'telephone': ['Nombor telefon atau kata laluan tidak sah.']}));
    final c = container();
    await c.read(authControllerProvider.notifier).login('0123456789', 'pw');
    final AuthState state = c.read(authControllerProvider).value!;
    expect(state.loggedIn, isFalse);
    expect(state.errorMessage, 'Nombor telefon atau kata laluan tidak sah.');
    expect(cfg.token, isNull);
  });
}
