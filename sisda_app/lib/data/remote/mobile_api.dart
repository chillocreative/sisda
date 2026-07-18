import 'dart:convert';
import 'package:http/http.dart' as http;
import '../../models/api_result.dart';
import '../../models/voter.dart';
import '../../sync/sync_engine.dart' show CulaanApi;
import 'api_config.dart';

class LoginResult {
  final String token;
  final Map<String, dynamic> user;
  const LoginResult(this.token, this.user);
}

/// Typed client for /api/mobile/*. Every method returns a model or throws
/// ApiException. Handles the contract's envelope variance:
///  - most endpoints: {"success": bool, ...}
///  - geography: bare JSON arrays
///  - 401/429 middleware, register/login-missing-field: bare {"message":...} (English)
/// The client reads `errors.<field>` and NEVER surfaces `message` to users.
class MobileApi implements CulaanApi {
  final http.Client _client;
  final ApiConfig _config;

  MobileApi({required http.Client client, required ApiConfig config})
      : _client = client,
        _config = config;

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (_config.token != null) 'Authorization': 'Bearer ${_config.token}',
      };

  Uri _uri(String path, [Map<String, dynamic>? query]) =>
      Uri.parse('${_config.baseUrl}$path').replace(
          queryParameters: query?.map((k, v) => MapEntry(k, '$v')));

  /// Decodes a response body, throwing ApiException for any non-2xx.
  /// Parses `errors.<field>` when present; otherwise an empty errors map (so
  /// ApiException.firstError falls back to BM).
  dynamic _decode(http.Response res) {
    final body = res.body.isEmpty ? null : jsonDecode(res.body);
    if (res.statusCode >= 200 && res.statusCode < 300) return body;

    final errors = <String, List<String>>{};
    if (body is Map && body['errors'] is Map) {
      (body['errors'] as Map).forEach((k, v) {
        errors['$k'] = (v as List).map((e) => '$e').toList();
      });
    }
    throw ApiException(
      status: res.statusCode,
      errors: errors,
      rawMessage: body is Map ? body['message'] as String? : null,
    );
  }

  Future<dynamic> _get(String path, [Map<String, dynamic>? q]) async {
    try {
      return _decode(await _client.get(_uri(path, q), headers: _headers));
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException(status: null, errors: {}, rawMessage: '$e');
    }
  }

  Future<dynamic> _post(String path, Map<String, dynamic> body) async {
    try {
      return _decode(await _client.post(_uri(path), headers: _headers, body: jsonEncode(body)));
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException(status: null, errors: {}, rawMessage: '$e');
    }
  }

  Future<LoginResult> login(String telephone, String password) async {
    final body = await _post('/api/mobile/login', {'telephone': telephone, 'password': password});
    return LoginResult(body['token'] as String, (body['user'] as Map).cast<String, dynamic>());
  }

  Future<void> register(Map<String, dynamic> body) => _post('/api/mobile/register', body);

  Future<String> forgotPassword(String telephone) async {
    final body = await _post('/api/mobile/forgot-password', {'telephone': telephone});
    // Contract §3: WhatsApp-send failure is a 200 with success:false — surface it.
    if (body is Map && body['success'] == false) {
      throw ApiException(status: 200, errors: {'telephone': ['${body['message']}']});
    }
    return (body['message'] ?? 'Kata laluan baharu telah dihantar.') as String;
  }

  Future<List<Map<String, dynamic>>> negeriList() async =>
      ((await _get('/api/mobile/negeri-list')) as List).map((e) => (e as Map).cast<String, dynamic>()).toList();

  Future<List<Map<String, dynamic>>> bandarByNegeri(int negeriId) async =>
      ((await _get('/api/mobile/bandar-by-negeri', {'negeri_id': negeriId})) as List)
          .map((e) => (e as Map).cast<String, dynamic>()).toList();

  Future<List<Map<String, dynamic>>> kadunByBandar(int bandarId) async =>
      ((await _get('/api/mobile/kadun-by-bandar', {'bandar_id': bandarId})) as List)
          .map((e) => (e as Map).cast<String, dynamic>()).toList();

  Future<List<Voter>> searchVoters(String q) async {
    final body = await _get('/api/mobile/voters/search', {'q': q});
    return ((body['voters'] as List)).map((e) => Voter.fromJson((e as Map).cast<String, dynamic>())).toList();
  }

  Future<Voter> showVoter(String ic) async {
    final body = await _get('/api/mobile/voters/$ic');
    return Voter.fromJson((body['voter'] as Map).cast<String, dynamic>());
  }

  Future<Map<String, dynamic>> culaanOptions() async =>
      ((await _get('/api/mobile/culaan/options'))['options'] as Map).cast<String, dynamic>();

  Future<List<Map<String, dynamic>>> culaanMine() async =>
      (((await _get('/api/mobile/culaan/mine'))['culaan']) as List)
          .map((e) => (e as Map).cast<String, dynamic>()).toList();

  @override
  Future<void> submitCulaan(Map<String, dynamic> payload) => _post('/api/mobile/culaan', payload);

  Future<void> logout() => _post('/api/mobile/logout', {});

  /// One-time token for WebView session auth. Contract §7: bare {token},
  /// no `success` key, so we read it straight off the decoded body.
  Future<String?> webAuthToken() async {
    final body = await _post('/api/mobile/web-auth-token', {});
    return body is Map ? body['token'] as String? : null;
  }

  /// Pure string builder — no HTTP call.
  String webAuthUrl(String webToken, String redirect) =>
      '${_config.baseUrl}/mobile-web-auth?token=$webToken&redirect=${Uri.encodeComponent(redirect)}';
}
