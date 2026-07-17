import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'data/local/database.dart';
import 'data/remote/api_config.dart';
import 'data/remote/mobile_api.dart';
import 'sync/sync_engine.dart';
import 'features/auth/auth_controller.dart';

/// Production host — the URL the old static ApiService used.
final apiConfigProvider = Provider<ApiConfig>(
    (ref) => ApiConfig(baseUrl: 'https://sistemdatapengundi.com'));

final httpClientProvider = Provider<http.Client>((ref) {
  final c = http.Client();
  ref.onDispose(c.close);
  return c;
});

final mobileApiProvider = Provider<MobileApi>((ref) => MobileApi(
      client: ref.watch(httpClientProvider),
      config: ref.watch(apiConfigProvider),
    ));

/// Real on-device Drift DB. Overridden in tests with AppDatabase.forTesting().
final appDatabaseProvider = Provider<AppDatabase>((ref) {
  final db = AppDatabase(openAppDatabaseConnection()); // see database.dart helper
  ref.onDispose(db.close);
  return db;
});

final syncEngineProvider = Provider<SyncEngine>(
    (ref) => SyncEngine(ref.watch(appDatabaseProvider), ref.watch(mobileApiProvider)));

final authControllerProvider =
    AsyncNotifierProvider<AuthController, AuthState>(AuthController.new);
