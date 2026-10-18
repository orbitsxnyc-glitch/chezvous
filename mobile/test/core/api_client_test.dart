import 'dart:async';
import 'dart:convert';

import 'package:chezvous/core/api_client.dart';
import 'package:chezvous/core/token_store.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  _robustness();
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

  test('un trousseau illisible ne bloque pas une requête publique', () async {
    late http.BaseRequest seen;
    final api = ApiClient(
      tokens: _BrokenStore(),
      baseUrl: 'http://api.test',
      client: MockClient((r) async {
        seen = r;
        return http.Response('{"data":[]}', 200);
      }),
    );
    final res = await api.get('/localities');
    expect(res['data'], isEmpty);
    expect(seen.headers.containsKey('Authorization'), isFalse);
  });
}

class _BrokenStore implements TokenStore {
  @override
  Future<String?> read() async => throw StateError('trousseau indisponible');

  @override
  Future<void> write(String token) async {}

  @override
  Future<void> clear() async {}
}

void _robustness() {
  group('robustesse réseau', () {
    ApiClient client(MockClientHandler h) => ApiClient(
      tokens: MemoryTokenStore('t'),
      baseUrl: 'http://api.test',
      client: MockClient(h),
      retryDelay: Duration.zero,
    );

    test(
      'une lecture est rejouée une fois après une coupure passagère',
      () async {
        var calls = 0;
        final api = client((_) async {
          calls++;
          if (calls == 1) throw Exception('coupure');
          return http.Response('{"data":[1]}', 200);
        });
        final res = await api.get('/listings');
        expect(res['data'], [1]);
        expect(calls, 2);
      },
    );

    test(
      'une lecture qui échoue deux fois renvoie une erreur réseau claire',
      () async {
        var calls = 0;
        final api = client((_) async {
          calls++;
          throw Exception('hors ligne');
        });
        await expectLater(
          api.get('/listings'),
          throwsA(
            isA<ApiException>().having((e) => e.code, 'code', 'network_error'),
          ),
        );
        expect(calls, 2);
      },
    );

    test(
      'un envoi (POST) n\'est jamais rejoué : il pourrait partir en double',
      () async {
        var calls = 0;
        final api = client((_) async {
          calls++;
          throw Exception('coupure');
        });
        await expectLater(
          api.post('/my/favorites', {'listing': 'x'}),
          throwsA(isA<ApiException>()),
        );
        expect(calls, 1);
      },
    );

    test('un 503 passager est rejoué, un 404 ne l\'est pas', () async {
      var calls = 0;
      final flaky = client((_) async {
        calls++;
        return calls == 1
            ? http.Response('', 503)
            : http.Response('{"data":[]}', 200);
      });
      expect((await flaky.get('/listings'))['data'], isEmpty);
      expect(calls, 2);

      calls = 0;
      final missing = client((_) async {
        calls++;
        return http.Response(
          '{"code":"not_found","message":"Ressource introuvable."}',
          404,
        );
      });
      await expectLater(
        missing.get('/listings/x'),
        throwsA(isA<ApiException>().having((e) => e.status, 'status', 404)),
      );
      expect(calls, 1);
    });

    test('une lenteur devient une erreur « timeout » distincte de la coupure, rejouée une fois pour une lecture', () async {
      var calls = 0;
      final api = ApiClient(
        tokens: MemoryTokenStore('t'),
        baseUrl: 'http://api.test',
        retryDelay: Duration.zero,
        requestTimeout: const Duration(milliseconds: 20),
        client: MockClient((_) {
          calls++;
          return Completer<http.Response>().future; // ne répond jamais
        }),
      );
      await expectLater(
        api.get('/listings'),
        throwsA(
          isA<ApiException>()
              .having((e) => e.code, 'code', 'timeout')
              .having((e) => e.message, 'message', contains('trop de temps')),
        ),
      );
      expect(calls, 2);
    });

    test(
      '429 et 500 donnent des messages lisibles, sans détail technique',
      () async {
        final busy = client(
          (_) async => http.Response('{"message":"Too Many Attempts."}', 429),
        );
        await expectLater(
          busy.get('/listings'),
          throwsA(
            isA<ApiException>()
                .having((e) => e.code, 'code', 'throttled')
                .having((e) => e.message, 'message', contains('Patientez')),
          ),
        );
        final broken = client(
          (_) async => http.Response('<html>Whoops</html>', 500),
        );
        await expectLater(
          broken.post('/x', {}),
          throwsA(
            isA<ApiException>().having(
              (e) => e.message,
              'message',
              contains('rencontre un problème'),
            ),
          ),
        );
      },
    );
  });
}
