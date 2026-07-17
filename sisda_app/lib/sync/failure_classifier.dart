import '../models/sync_status.dart';

/// Maps an HTTP status to a retry bucket, per the API contract's
/// status→bucket table. Returns null for 2xx (success — delete the draft).
///
/// The load-bearing case: 429 is TRANSIENT. It is the only 4xx that must be
/// retried; classifying it permanent would strand a valid submission in
/// Perlu Perhatian forever.
FailureBucket? classifyStatus(int status) {
  if (status >= 200 && status < 300) return null;
  if (status == 401) return FailureBucket.auth;
  if (status == 429) return FailureBucket.transient;
  if (status >= 500) return FailureBucket.transient;
  // 403 Parlimen, 409 duplicate/source, 422 validation — and any other 4xx —
  // are permanent: retrying will never help.
  return FailureBucket.permanent;
}

/// Any transport-level error (no HTTP response: timeout, socket, DNS) is
/// transient — normal life in the field.
FailureBucket classifyException(Object error) => FailureBucket.transient;
