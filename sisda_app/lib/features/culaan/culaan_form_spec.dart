// PURE DART — no Flutter import. Declares the 7-section Culaan form and the
// completeness/gating functions the checklist hub and Hantar button use.

enum FieldKind { text, multilineText, number, dropdown, multiSelect, cascading }

class FieldSpec {
  final String key;
  final String label;
  final FieldKind kind;
  final bool required;
  final bool sensitive;

  /// Companion "*_lain" field key revealed when a "Lain-lain" option is chosen.
  final String? lainKey;

  /// Key into the `culaan/options` map for dropdown/multiSelect/cascading fields.
  final String? optionsKey;

  const FieldSpec({
    required this.key,
    required this.label,
    required this.kind,
    this.required = false,
    this.sensitive = false,
    this.lainKey,
    this.optionsKey,
  });

  bool get isArray => kind == FieldKind.multiSelect || kind == FieldKind.cascading;
}

class SectionSpec {
  final String key;
  final String title;
  final List<FieldSpec> fields;
  final bool sumbanganGated;

  const SectionSpec({
    required this.key,
    required this.title,
    required this.fields,
    this.sumbanganGated = false,
  });

  /// A section with no server-required fields (Maklumat Politik, Status Pengundi).
  bool get isOptional => fields.every((f) => !f.required);
}

const Set<String> kSensitiveFields = {
  'no_ic', 'umur', 'bangsa', 'no_tel', 'alamat',
  'poskod', 'negeri', 'bandar', 'pendapatan_isi_rumah',
};

const List<SectionSpec> _allSections = [
  SectionSpec(key: 'peribadi', title: 'Maklumat Peribadi', fields: [
    FieldSpec(key: 'nama', label: 'Nama', kind: FieldKind.text, required: true),
    FieldSpec(key: 'no_ic', label: 'No. Kad Pengenalan', kind: FieldKind.number, required: true, sensitive: true),
    FieldSpec(key: 'umur', label: 'Umur', kind: FieldKind.number, required: true, sensitive: true),
    FieldSpec(key: 'no_tel', label: 'No. Telefon', kind: FieldKind.text, required: true, sensitive: true),
    FieldSpec(key: 'bangsa', label: 'Bangsa', kind: FieldKind.text, required: true, sensitive: true),
  ]),
  SectionSpec(key: 'alamat', title: 'Maklumat Alamat', fields: [
    FieldSpec(key: 'alamat', label: 'Alamat', kind: FieldKind.multilineText, required: true, sensitive: true),
    FieldSpec(key: 'poskod', label: 'Poskod', kind: FieldKind.text, required: true, sensitive: true),
    FieldSpec(key: 'negeri', label: 'Negeri', kind: FieldKind.text, required: true, sensitive: true),
    FieldSpec(key: 'bandar', label: 'Bandar', kind: FieldKind.text, required: true, sensitive: true),
  ]),
  SectionSpec(key: 'kawasan', title: 'Kawasan Mengundi', fields: [
    FieldSpec(key: 'parlimen', label: 'Parlimen', kind: FieldKind.text, required: true),
    FieldSpec(key: 'kadun', label: 'DUN / Kadun', kind: FieldKind.text, required: true),
    FieldSpec(key: 'mpkk', label: 'MPKK', kind: FieldKind.text),
    FieldSpec(key: 'daerah_mengundi', label: 'Daerah Mengundi', kind: FieldKind.text),
    FieldSpec(key: 'lokaliti', label: 'Lokaliti', kind: FieldKind.text),
  ]),
  SectionSpec(key: 'politik', title: 'Maklumat Politik', fields: [
    FieldSpec(key: 'keahlian_parti', label: 'Keahlian Parti', kind: FieldKind.text),
    FieldSpec(key: 'kecenderungan_politik', label: 'Kecenderungan Politik', kind: FieldKind.text),
  ]),
  SectionSpec(key: 'status', title: 'Status Pengundi', fields: [
    FieldSpec(key: 'status_pengundi', label: 'Status Pengundi', kind: FieldKind.text),
    FieldSpec(key: 'nota', label: 'Nota', kind: FieldKind.multilineText),
  ]),
  SectionSpec(key: 'isi_rumah', title: 'Isi Rumah', sumbanganGated: true, fields: [
    FieldSpec(key: 'bil_isi_rumah', label: 'Bilangan Isi Rumah', kind: FieldKind.number, required: true),
    FieldSpec(key: 'pendapatan_isi_rumah', label: 'Pendapatan Isi Rumah', kind: FieldKind.number, sensitive: true),
    FieldSpec(key: 'pekerjaan', label: 'Pekerjaan', kind: FieldKind.dropdown, required: true, optionsKey: 'pekerjaan'),
    FieldSpec(key: 'jenis_pekerjaan', label: 'Jenis Pekerjaan', kind: FieldKind.cascading, required: true, lainKey: 'jenis_pekerjaan_lain', optionsKey: 'jenis_pekerjaan'),
    FieldSpec(key: 'pemilik_rumah', label: 'Pemilik Rumah', kind: FieldKind.dropdown, required: true, lainKey: 'pemilik_rumah_lain', optionsKey: 'pemilik_rumah'),
  ]),
  SectionSpec(key: 'bantuan', title: 'Bantuan', sumbanganGated: true, fields: [
    FieldSpec(key: 'jenis_sumbangan', label: 'Jenis Sumbangan', kind: FieldKind.multiSelect, required: true, lainKey: 'jenis_sumbangan_lain', optionsKey: 'jenis_sumbangan'),
    FieldSpec(key: 'tujuan_sumbangan', label: 'Tujuan Sumbangan', kind: FieldKind.multiSelect, required: true, lainKey: 'tujuan_sumbangan_lain', optionsKey: 'tujuan_sumbangan'),
    FieldSpec(key: 'bantuan_lain', label: 'Bantuan Lain', kind: FieldKind.multiSelect, required: true, lainKey: 'bantuan_lain_lain', optionsKey: 'bantuan_lain'),
    FieldSpec(key: 'isejahtera_program', label: 'Program iSejahtera', kind: FieldKind.text),
    FieldSpec(key: 'bkb_program', label: 'Program BKB', kind: FieldKind.text),
    FieldSpec(key: 'jumlah_bantuan_tunai', label: 'Jumlah Bantuan Tunai (RM)', kind: FieldKind.number),
    FieldSpec(key: 'jumlah_wang_tunai', label: 'Jumlah Wang Tunai (RM)', kind: FieldKind.number),
  ]),
];

List<SectionSpec> sectionsFor(bool hasSumbangan) => _allSections
    .where((s) => !s.sumbanganGated || hasSumbangan)
    .toList(growable: false);

bool _isFilled(dynamic v) {
  if (v == null) return false;
  if (v is String) return v.trim().isNotEmpty;
  if (v is List) return v.isNotEmpty;
  return true;
}

bool isSectionComplete(SectionSpec section, Map<String, dynamic> fields) =>
    section.fields.where((f) => f.required).every((f) => _isFilled(fields[f.key]));

List<SectionSpec> incompleteRequiredSections(
        Map<String, dynamic> fields, bool hasSumbangan) =>
    sectionsFor(hasSumbangan)
        .where((s) => !s.isOptional && !isSectionComplete(s, fields))
        .toList(growable: false);

bool hasLainSelected(FieldSpec spec, dynamic value) {
  bool matches(String s) => s.toLowerCase().contains('lain');
  if (value is List) return value.any((e) => e is String && matches(e));
  if (value is String) return matches(value);
  return false;
}
