import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../models/api_result.dart';
import '../../providers.dart';

class AuthState {
  final bool loggedIn;
  final Map<String, dynamic>? user;
  final String? errorMessage;
  const AuthState({this.loggedIn = false, this.user, this.errorMessage});
}

const _tokenKey = 'auth_token'; // same key the old ApiService used — sessions survive upgrade

class AuthController extends AsyncNotifier<AuthState> {
  @override
  Future<AuthState> build() async => _restore();

  Future<AuthState> _restore() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(_tokenKey);
    if (token == null) return const AuthState(loggedIn: false);
    ref.read(apiConfigProvider).token = token;
    return const AuthState(loggedIn: true);
  }

  Future<void> login(String telephone, String password) async {
    state = const AsyncLoading();
    try {
      final result = await ref.read(mobileApiProvider).login(telephone, password);
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_tokenKey, result.token);
      ref.read(apiConfigProvider).token = result.token;
      state = AsyncData(AuthState(loggedIn: true, user: result.user));
    } on ApiException catch (e) {
      state = AsyncData(AuthState(loggedIn: false, errorMessage: e.firstError()));
    }
  }

  Future<void> register(Map<String, dynamic> body) async {
    try {
      await ref.read(mobileApiProvider).register(body);
      state = const AsyncData(AuthState(loggedIn: false)); // pending approval, no auto-login
    } on ApiException catch (e) {
      state = AsyncData(AuthState(loggedIn: false, errorMessage: e.firstError()));
    }
  }

  Future<String?> forgotPassword(String telephone) async {
    try {
      return await ref.read(mobileApiProvider).forgotPassword(telephone);
    } on ApiException catch (e) {
      state = AsyncData(AuthState(loggedIn: false, errorMessage: e.firstError()));
      return null;
    }
  }

  Future<void> logout() async {
    try {
      await ref.read(mobileApiProvider).logout();
    } on ApiException {
      // logout best-effort; clear locally regardless
    }
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    ref.read(apiConfigProvider).token = null;
    state = const AsyncData(AuthState(loggedIn: false));
  }
}
