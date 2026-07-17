import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:sisda_app/data/remote/api_config.dart';
import 'package:sisda_app/data/remote/mobile_api.dart';
import 'package:sisda_app/models/api_result.dart';

MobileApi apiReturning(int status, String body, {ApiConfig? cfg}) {
  final client = MockClient((req) async => http.Response(body, status,
      headers: {'content-type': 'application/json'}));
  return MobileApi(client: client, config: cfg ?? ApiConfig(baseUrl: 'http://x'));
}

void main() {
  test('login parses the {success, token, user} envelope', () async {
    final api = apiReturning(200,
        jsonEncode({'success': true, 'token': '1|abc', 'user': {'id': 1, 'role': 'user'}}));
    final r = await api.login('0123', 'pw');
    expect(r.token, '1|abc');
    expect(r.user['role'], 'user');
  });

  test('login 422 throws ApiException with the BM field error', () async {
    final api = apiReturning(422, jsonEncode({
      'success': false,
      'errors': {'telephone': ['Nombor telefon atau kata laluan tidak sah.']}
    }));
    expect(() => api.login('0123', 'pw'),
        throwsA(isA<ApiException>().having((e) => e.firstError(), 'msg',
            'Nombor telefon atau kata laluan tidak sah.')));
  });

  test('401 middleware ({message} only, English) throws ApiException status 401, no leaked message', () async {
    final api = apiReturning(401, jsonEncode({'message': 'Unauthenticated.'}));
    try {
      await api.culaanMine();
      fail('expected throw');
    } on ApiException catch (e) {
      expect(e.status, 401);
      // firstError falls back to BM, never surfaces "Unauthenticated."
      expect(e.firstError(), isNot(contains('Unauthenticated')));
    }
  });

  test('negeriList parses a BARE JSON array', () async {
    final api = apiReturning(200, jsonEncode([
      {'id': 1, 'nama': 'JOHOR'}, {'id': 2, 'nama': 'KEDAH'}
    ]));
    final list = await api.negeriList();
    expect(list.length, 2);
    expect(list.first['nama'], 'JOHOR');
  });

  test('searchVoters parses {success, voters:[...]} into Voter list', () async {
    final api = apiReturning(200, jsonEncode({
      'success': true,
      'voters': [{'id': 5, 'nama': 'Ahmad', 'no_ic': '****'}]
    }));
    final voters = await api.searchVoters('Ahmad');
    expect(voters.single.nama, 'Ahmad');
    expect(voters.single.field('no_ic'), '****');
  });

  test('submitCulaan 201 returns normally; 403 throws permanent-classifiable ApiException', () async {
    await apiReturning(201, jsonEncode({'success': true, 'culaan': {'id': 9, 'no_ic': '****'}}))
        .submitCulaan({'idempotency_key': 'k'});
    final api403 = apiReturning(403,
        jsonEncode({'success': false, 'errors': {'parlimen': ['Rekod ini di luar Parlimen anda.']}}));
    expect(() => api403.submitCulaan({'idempotency_key': 'k'}),
        throwsA(isA<ApiException>().having((e) => e.status, 'status', 403)));
  });

  test('sends Bearer token when configured', () async {
    String? seenAuth;
    final client = MockClient((req) async {
      seenAuth = req.headers['Authorization'];
      return http.Response(jsonEncode({'success': true, 'voters': []}), 200,
          headers: {'content-type': 'application/json'});
    });
    final api = MobileApi(client: client, config: ApiConfig(baseUrl: 'http://x', token: 'TKN'));
    await api.searchVoters('abc');
    expect(seenAuth, 'Bearer TKN');
  });
}
