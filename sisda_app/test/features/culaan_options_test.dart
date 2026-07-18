import 'package:flutter_test/flutter_test.dart';
import 'package:sisda_app/features/culaan/culaan_options.dart';

void main() {
  final json = {
    'pekerjaan': ['Kerajaan', 'Swasta', 'Bekerja Sendiri', 'Tidak Bekerja'],
    'jenis_pekerjaan': {
      'Kerajaan': [
        {'category': 'Jenis Perkhidmatan', 'items': ['Perkhidmatan Awam Persekutuan', 'Perkhidmatan Awam Negeri']},
        {'category': 'Lain-lain', 'items': ['Lain-lain']},
      ],
      'Swasta': [
        {'category': 'Sektor', 'items': ['Kilang']},
      ],
    },
    'jenis_sumbangan': ['Barangan Keperluan Dapur', 'Lain-lain'],
    'tujuan_sumbangan': ['Pendidikan', 'Kesihatan'],
    'bantuan_lain': ['Jabatan Kebajikan Masyarakat (JKM)', 'Tiada', 'Lain-lain'],
    'pemilik_rumah': ['Sendiri', 'Sewa', 'Keluarga', 'Lain-lain'],
  };

  test('fromJson parses flat lists', () {
    final o = CulaanOptions.fromJson(json);
    expect(o.pekerjaan, ['Kerajaan', 'Swasta', 'Bekerja Sendiri', 'Tidak Bekerja']);
    expect(o.pemilikRumah.last, 'Lain-lain');
    expect(o.optionsForKey('jenis_sumbangan'), ['Barangan Keperluan Dapur', 'Lain-lain']);
  });

  test('jenisPekerjaanFor returns the category groups for the selected pekerjaan', () {
    final o = CulaanOptions.fromJson(json);
    final groups = o.jenisPekerjaanFor('Kerajaan');
    expect(groups.map((g) => g.category), ['Jenis Perkhidmatan', 'Lain-lain']);
    expect(groups.first.items, ['Perkhidmatan Awam Persekutuan', 'Perkhidmatan Awam Negeri']);
    expect(o.jenisPekerjaanFor('Tidak Bekerja'), isEmpty); // key absent → empty, not crash
  });

  test('empty tujuan_sumbangan is tolerated (Master Data may be empty)', () {
    final o = CulaanOptions.fromJson({...json, 'tujuan_sumbangan': <dynamic>[]});
    expect(o.tujuanSumbangan, isEmpty);
    expect(o.optionsForKey('tujuan_sumbangan'), isEmpty);
  });

  test('missing keys default to empty lists (defensive)', () {
    final o = CulaanOptions.fromJson({});
    expect(o.pekerjaan, isEmpty);
    expect(o.jenisPekerjaanFor('Kerajaan'), isEmpty);
  });
}
