// PURE DART. Typed view over GET /api/mobile/culaan/options (contract §11).

class PekerjaanCategory {
  final String category;
  final List<String> items;
  const PekerjaanCategory(this.category, this.items);
}

class CulaanOptions {
  final List<String> pekerjaan;
  final Map<String, List<PekerjaanCategory>> jenisPekerjaan;
  final List<String> jenisSumbangan;
  final List<String> tujuanSumbangan;
  final List<String> bantuanLain;
  final List<String> pemilikRumah;

  const CulaanOptions({
    required this.pekerjaan,
    required this.jenisPekerjaan,
    required this.jenisSumbangan,
    required this.tujuanSumbangan,
    required this.bantuanLain,
    required this.pemilikRumah,
  });

  static List<String> _stringList(dynamic v) =>
      v is List ? v.map((e) => e.toString()).toList() : const [];

  factory CulaanOptions.fromJson(Map<String, dynamic> json) {
    final rawJp = json['jenis_pekerjaan'];
    final jp = <String, List<PekerjaanCategory>>{};
    if (rawJp is Map) {
      rawJp.forEach((pekerjaan, groups) {
        if (groups is List) {
          jp[pekerjaan.toString()] = groups
              .whereType<Map>()
              .map((g) => PekerjaanCategory(
                    (g['category'] ?? '').toString(),
                    _stringList(g['items']),
                  ))
              .toList();
        }
      });
    }
    return CulaanOptions(
      pekerjaan: _stringList(json['pekerjaan']),
      jenisPekerjaan: jp,
      jenisSumbangan: _stringList(json['jenis_sumbangan']),
      tujuanSumbangan: _stringList(json['tujuan_sumbangan']),
      bantuanLain: _stringList(json['bantuan_lain']),
      pemilikRumah: _stringList(json['pemilik_rumah']),
    );
  }

  List<String> optionsForKey(String optionsKey) {
    switch (optionsKey) {
      case 'pekerjaan':
        return pekerjaan;
      case 'jenis_sumbangan':
        return jenisSumbangan;
      case 'tujuan_sumbangan':
        return tujuanSumbangan;
      case 'bantuan_lain':
        return bantuanLain;
      case 'pemilik_rumah':
        return pemilikRumah;
      default:
        return const [];
    }
  }

  List<PekerjaanCategory> jenisPekerjaanFor(String pekerjaan) =>
      jenisPekerjaan[pekerjaan] ?? const [];
}
