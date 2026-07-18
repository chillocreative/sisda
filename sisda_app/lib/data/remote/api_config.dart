/// Holds the base URL and the current Sanctum token. Mutable token so login
/// can set it and logout clear it. Replaces the static fields on the old
/// ApiService.
class ApiConfig {
  final String baseUrl;
  String? token;
  ApiConfig({required this.baseUrl, this.token});
}
