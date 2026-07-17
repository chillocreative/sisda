/// A voter search/detail result. NEVER persisted to disk (privacy line).
/// Sensitive fields arrive as '****' for viewers who cannot unmask; treat
/// every field as an opaque display string, never a real value.
class Voter {
  final int id;
  final String nama;
  final Map<String, dynamic> fields;

  const Voter({required this.id, required this.nama, required this.fields});

  factory Voter.fromJson(Map<String, dynamic> json) => Voter(
        id: json['id'] as int,
        nama: (json['nama'] ?? '') as String,
        fields: json,
      );

  String field(String key) => (fields[key] ?? '').toString();
}
