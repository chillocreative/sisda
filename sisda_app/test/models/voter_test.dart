import 'package:flutter_test/flutter_test.dart';
import 'package:sisda_app/models/voter.dart';
import 'package:sisda_app/models/api_result.dart';

void main() {
  test('Voter.fromJson keeps masked fields as opaque strings', () {
    final v = Voter.fromJson({
      'id': 101, 'nama': 'Ahmad bin Ali', 'no_ic': '****', 'no_tel': '****',
    });
    expect(v.id, 101);
    expect(v.nama, 'Ahmad bin Ali');
    expect(v.field('no_ic'), '****');
    expect(v.field('missing'), '');
  });

  test('ApiException.firstError returns the first field message', () {
    final e = ApiException(status: 422, errors: {
      'bil_isi_rumah': ['Ruangan Bil. Isi Rumah diperlukan.'],
    });
    expect(e.firstError(), 'Ruangan Bil. Isi Rumah diperlukan.');
  });

  test('ApiException with no field errors falls back to a BM message', () {
    final e = ApiException(status: 500, errors: {});
    expect(e.firstError(), isNotEmpty);
    expect(e.firstError(), isNot(contains('field'))); // never leak English default
  });

  test('network error has null status', () {
    final e = ApiException(status: null, errors: {});
    expect(e.isNetworkError, isTrue);
  });
}
