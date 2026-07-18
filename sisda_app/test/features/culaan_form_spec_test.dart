import 'package:flutter_test/flutter_test.dart';
import 'package:sisda_app/features/culaan/culaan_form_spec.dart';

void main() {
  test('sectionsFor(false) yields 5 sections; (true) adds Isi Rumah + Bantuan', () {
    expect(sectionsFor(false).map((s) => s.key),
        ['peribadi', 'alamat', 'kawasan', 'politik', 'status']);
    expect(sectionsFor(true).map((s) => s.key),
        ['peribadi', 'alamat', 'kawasan', 'politik', 'status', 'isi_rumah', 'bantuan']);
  });

  test('kSensitiveFields matches the contract SENSITIVE_FIELDS exactly', () {
    expect(kSensitiveFields, {
      'no_ic', 'umur', 'bangsa', 'no_tel', 'alamat',
      'poskod', 'negeri', 'bandar', 'pendapatan_isi_rumah',
    });
  });

  test('peribadi complete only when all 5 required fields present', () {
    final peribadi = sectionsFor(false).firstWhere((s) => s.key == 'peribadi');
    expect(isSectionComplete(peribadi, {'nama': 'Ali'}), isFalse);
    expect(
        isSectionComplete(peribadi, {
          'nama': 'Ali', 'no_ic': '800101015555', 'umur': '44',
          'no_tel': '0121234567', 'bangsa': 'Melayu',
        }),
        isTrue);
  });

  test('kawasan complete on parlimen+kadun; optional fields ignored', () {
    final kawasan = sectionsFor(false).firstWhere((s) => s.key == 'kawasan');
    expect(isSectionComplete(kawasan, {'parlimen': 'P.044', 'kadun': 'N.11'}), isTrue);
    expect(isSectionComplete(kawasan, {'parlimen': 'P.044'}), isFalse);
  });

  test('optional-only sections are never required and count as complete', () {
    final politik = sectionsFor(false).firstWhere((s) => s.key == 'politik');
    expect(politik.isOptional, isTrue);
    expect(isSectionComplete(politik, {}), isTrue); // nothing required → complete
  });

  test('incompleteRequiredSections excludes optional sections', () {
    final missing = incompleteRequiredSections({}, false).map((s) => s.key);
    expect(missing, containsAll(['peribadi', 'alamat', 'kawasan']));
    expect(missing, isNot(contains('politik')));
    expect(missing, isNot(contains('status')));
  });

  test('has_sumbangan adds Isi Rumah/Bantuan required fields to the gate', () {
    final full = {
      'nama': 'Ali', 'no_ic': '800101015555', 'umur': '44',
      'no_tel': '0121234567', 'bangsa': 'Melayu', 'alamat': 'Jln 1',
      'poskod': '13200', 'negeri': 'Pulau Pinang', 'bandar': 'Kepala Batas',
      'parlimen': 'P.044', 'kadun': 'N.11',
    };
    expect(incompleteRequiredSections(full, false), isEmpty);
    // same fields but has_sumbangan on: Isi Rumah + Bantuan now incomplete
    final missing = incompleteRequiredSections(full, true).map((s) => s.key);
    expect(missing, ['isi_rumah', 'bantuan']);
  });

  test('array required field needs at least one entry', () {
    final bantuan = sectionsFor(true).firstWhere((s) => s.key == 'bantuan');
    expect(isSectionComplete(bantuan, {
      'jenis_sumbangan': <String>[], 'tujuan_sumbangan': ['Pendidikan'],
      'bantuan_lain': ['JKM'],
    }), isFalse);
    expect(isSectionComplete(bantuan, {
      'jenis_sumbangan': ['Barangan'], 'tujuan_sumbangan': ['Pendidikan'],
      'bantuan_lain': ['JKM'],
    }), isTrue);
  });

  test('hasLainSelected true when a selected value contains "lain"', () {
    final jenisSumbangan =
        sectionsFor(true).firstWhere((s) => s.key == 'bantuan').fields
            .firstWhere((f) => f.key == 'jenis_sumbangan');
    expect(hasLainSelected(jenisSumbangan, ['Barangan', 'Lain-lain']), isTrue);
    expect(hasLainSelected(jenisSumbangan, ['Barangan']), isFalse);
  });
}
