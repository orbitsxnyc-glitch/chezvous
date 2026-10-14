import 'dart:convert';

import 'package:chezvous/core/api_client.dart';
import 'package:chezvous/core/token_store.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  test('envoie le jeton en Bearer et lit la réponse JSON', () async {
    late http.BaseRequest seen;
    final api = ApiClient(
      tokens: MemoryTokenStore('abc'),
      baseUrl: 'http://api.test',
      client: MockClient((r) async {
        seen = r;
        return http.Response(jsonEncode({'data': 1}), 200);
      }),
    );
    final res = await api.get('/me');
    expect(res['data'], 1);
    expect(seen.headers['Authorization'], 'Bearer abc');
    expect(seen.url.toString(), 'http://api.test/api/v1/me');
  });

  test("n'envoie pas d'en-tête Authorization sans jeton", () async {
    late http.BaseRequest seen;
    final api = ApiClient(
      tokens: MemoryTokenStore(),
      baseUrl: 'http://api.test',
      client: MockClient((r) async {
        seen = r;
        return http.Response('{}', 200);
      }),
    );
    await api.get('/plans');
    expect(seen.headers.containsKey('Authorization'), isFalse);
  });

  test("normalise les erreurs de l'API avec les erreurs de champ", () async {
    final api = ApiClient(
      tokens: MemoryTokenStore(),
      baseUrl: 'http://api.test',
      client: MockClient(
        (_) async => http.Response.bytes(
          utf8.encode(
            jsonEncode({
              'code': 'validation_error',
              'message': 'Invalide.',
              'errors': {
                'phone': ['Numéro invalide.'],
              },
            }),
          ),
          422,
        ),
      ),
    );
    await expectLater(
      api.post('/auth/register', {}),
      throwsA(
        isA<ApiException>()
            .having((e) => e.code, 'code', 'validation_error')
            .having((e) => e.field('phone'), 'phone', 'Numéro invalide.'),
      ),
    );
  });

  test('une coupure réseau devient une erreur lisible', () async {
    final api = ApiClient(
      tokens: MemoryTokenStore(),
      baseUrl: 'http://api.test',
      client: MockClient((_) async => throw Exception('hors ligne')),
    );
    await expectLater(
      api.get('/plans'),
      throwsA(
        isA<ApiException>().having((e) => e.code, 'code', 'network_error'),
      ),
    );
  });

  test('un 401 avec jeton déclenche onUnauthorized, pas sans jeton', () async {
    var called = 0;
    final client = MockClient(
      (_) async => http.Response('{"code":"unauthenticated"}', 401),
    );
    final withToken = ApiClient(
      tokens: MemoryTokenStore('t'),
      baseUrl: 'http://api.test',
      client: client,
    )..onUnauthorized = () => called++;
    await expectLater(withToken.get('/me'), throwsA(isA<ApiException>()));
    expect(called, 1);
    final anonymous = ApiClient(
      tokens: MemoryTokenStore(),
      baseUrl: 'http://api.test',
      client: client,
    )..onUnauthorized = () => called++;
    await expectLater(anonymous.get('/me'), throwsA(isA<ApiException>()));
    expect(called, 1);
  });
}
