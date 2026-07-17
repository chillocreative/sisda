/// Lifecycle of a Culaan draft. `draft` (editing) → `queued` (user tapped
/// Hantar) → `syncing` → `synced` (deleted locally) or `failed` (→ Perlu
/// Perhatian). Pure Dart — no Flutter import.
enum SyncStatus { draft, queued, syncing, synced, failed }

/// How a sync attempt failed, which decides what happens next.
/// See the status→bucket table in the API contract.
enum FailureBucket { transient, auth, permanent }
