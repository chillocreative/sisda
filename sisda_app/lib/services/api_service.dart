import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ApiService {
  static const String baseUrl = 'https://sistemdatapengundi.com';
  static String? _sessionCookie;
  static String? _xsrfToken;

  static Future<void> _fetchCsrfToken() async {
    final response = await http.get(
      Uri.parse('$baseUrl/sanctum/csrf-cookie'),
      headers: {'Accept': 'application/json'},
    );
    final cookies = response.headers['set-cookie'];
    if (cookies != null) {
      _parseCookies(cookies);
    }
  }

  static void _parseCookies(String cookieHeader) {
    for (var cookie in cookieHeader.split(',')) {
      cookie = cookie.trim();
      if (cookie.startsWith('XSRF-TOKEN=')) {
        _xsrfToken = Uri.decodeFull(cookie.split(';').first.split('=').sublist(1).join('='));
      }
      if (cookie.startsWith('sisda-session=') || cookie.startsWith('laravel_session=')) {
        _sessionCookie = cookie.split(';').first;
      }
    }
  }

  static Map<String, String> get _headers => {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    if (_xsrfToken != null) 'X-XSRF-TOKEN': _xsrfToken!,
    if (_sessionCookie != null) 'Cookie': _sessionCookie!,
  };

  static Future<Map<String, dynamic>> login(String telephone, String password) async {
    await _fetchCsrfToken();

    final response = await http.post(
      Uri.parse('$baseUrl/login'),
      headers: _headers,
      body: jsonEncode({'telephone': telephone, 'password': password, 'remember': true}),
    );

    if (response.headers['set-cookie'] != null) {
      _parseCookies(response.headers['set-cookie']!);
    }

    if (response.statusCode == 200 || response.statusCode == 302) {
      // Save credentials for session
      final prefs = await SharedPreferences.getInstance();
      if (_sessionCookie != null) {
        await prefs.setString('session_cookie', _sessionCookie!);
      }
      if (_xsrfToken != null) {
        await prefs.setString('xsrf_token', _xsrfToken!);
      }
      return {'success': true};
    }

    final body = response.body.isNotEmpty ? jsonDecode(response.body) : {};
    return {'success': false, 'errors': body['errors'] ?? {'telephone': ['Login gagal']}};
  }

  static Future<Map<String, dynamic>?> searchIc(String ic) async {
    final response = await http.get(
      Uri.parse('$baseUrl/api/voter/search-ic?ic=$ic'),
      headers: _headers,
    );
    if (response.statusCode == 200 && response.body.isNotEmpty) {
      return jsonDecode(response.body);
    }
    return null;
  }

  static String? get sessionCookie => _sessionCookie;
  static String? get xsrfToken => _xsrfToken;

  static Future<void> loadSavedSession() async {
    final prefs = await SharedPreferences.getInstance();
    _sessionCookie = prefs.getString('session_cookie');
    _xsrfToken = prefs.getString('xsrf_token');
  }

  static Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('session_cookie');
    await prefs.remove('xsrf_token');
    _sessionCookie = null;
    _xsrfToken = null;
  }
}
