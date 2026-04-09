import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ApiService {
  static const String baseUrl = 'https://sistemdatapengundi.com';
  static String? _token;

  static Map<String, String> get _headers => {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    if (_token != null) 'Authorization': 'Bearer $_token',
  };

  // ── Login ──
  static Future<Map<String, dynamic>> login(String telephone, String password) async {
    final response = await http.post(
      Uri.parse('$baseUrl/api/mobile/login'),
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
      body: jsonEncode({'telephone': telephone, 'password': password}),
    );

    final body = response.body.isNotEmpty ? jsonDecode(response.body) : {};

    if (response.statusCode == 200 && body['success'] == true) {
      _token = body['token'];
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('auth_token', _token!);
      return {'success': true, 'user': body['user']};
    }

    return {'success': false, 'errors': body['errors'] ?? {'telephone': ['Log masuk gagal.']}};
  }

  // ── Register ──
  static Future<Map<String, dynamic>> register({
    required String name,
    required String telephone,
    String? email,
    required String password,
    required String passwordConfirmation,
    required int negeriId,
    required int bandarId,
    required int kadunId,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/api/mobile/register'),
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
      body: jsonEncode({
        'name': name,
        'telephone': telephone,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
        'negeri_id': negeriId,
        'bandar_id': bandarId,
        'kadun_id': kadunId,
      }),
    );

    final body = response.body.isNotEmpty ? jsonDecode(response.body) : {};

    if (response.statusCode == 200 && body['success'] == true) {
      return {'success': true};
    }

    return {'success': false, 'errors': body['errors'] ?? {'general': ['Pendaftaran gagal.']}};
  }

  // ── Forgot Password ──
  static Future<Map<String, dynamic>> forgotPassword(String telephone) async {
    final response = await http.post(
      Uri.parse('$baseUrl/api/mobile/forgot-password'),
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
      body: jsonEncode({'telephone': telephone}),
    );

    final body = response.body.isNotEmpty ? jsonDecode(response.body) : {};

    if (response.statusCode == 200 && body['success'] == true) {
      return {'success': true, 'message': body['message'], 'password': body['password']};
    }

    return {'success': false, 'errors': body['errors'] ?? {'telephone': ['Nombor telefon tidak dijumpai.']}};
  }

  // ── Dropdown data for registration ──
  static Future<List<dynamic>> getNegeriList() async {
    final response = await http.get(Uri.parse('$baseUrl/api/mobile/negeri-list'), headers: _headers);
    if (response.statusCode == 200) return jsonDecode(response.body);
    return [];
  }

  static Future<List<dynamic>> getBandarByNegeri(int negeriId) async {
    final response = await http.get(Uri.parse('$baseUrl/api/mobile/bandar-by-negeri?negeri_id=$negeriId'), headers: _headers);
    if (response.statusCode == 200) return jsonDecode(response.body);
    return [];
  }

  static Future<List<dynamic>> getKadunByBandar(int bandarId) async {
    final response = await http.get(Uri.parse('$baseUrl/api/mobile/kadun-by-bandar?bandar_id=$bandarId'), headers: _headers);
    if (response.statusCode == 200) return jsonDecode(response.body);
    return [];
  }

  // ── IC Search ──
  static Future<Map<String, dynamic>?> searchIc(String ic) async {
    final response = await http.get(Uri.parse('$baseUrl/api/voter/search-ic?ic=$ic'), headers: _headers);
    if (response.statusCode == 200 && response.body.isNotEmpty) return jsonDecode(response.body);
    return null;
  }

  static String? get token => _token;

  // ── Session persistence ──
  static Future<void> loadSavedSession() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString('auth_token');
  }

  // ── Logout ──
  static Future<void> logout() async {
    try {
      await http.post(Uri.parse('$baseUrl/api/mobile/logout'), headers: _headers);
    } catch (_) {}
    _token = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
  }
}
