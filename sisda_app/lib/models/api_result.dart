/// Typed API failure. `status == null` means a transport error (no response).
/// `errors` is the server's `errors.<field>` map (BM for mobile endpoints).
/// `rawMessage` is the server's top-level `message` — NOT shown to users
/// (may be English); kept only for logging.
class ApiException implements Exception {
  final int? status;
  final Map<String, List<String>> errors;
  final String? rawMessage;

  const ApiException({required this.status, required this.errors, this.rawMessage});

  bool get isNetworkError => status == null;

  /// First user-facing BM message. Falls back to a generic BM line rather
  /// than ever surfacing Laravel's English default.
  String firstError() {
    for (final list in errors.values) {
      if (list.isNotEmpty) return list.first;
    }
    return 'Ralat tidak dijangka. Sila cuba lagi.';
  }

  @override
  String toString() => 'ApiException(status: $status, errors: $errors)';
}
