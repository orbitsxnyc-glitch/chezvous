import 'dart:convert';

import 'package:chezvous/auth/auth_controller.dart';
import 'package:chezvous/core/api_client.dart';
import 'package:chezvous/core/token_store.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

const _user = {
  'id': 'u1',
  'name': 'Awa Diop',
  'phone': '+221771234567',
  'email': null,
  'roles': ['particulier'],
};

AuthController _controller(MockClientHandler handler, TokenStore tokens) =>
    AuthController(
      api: ApiClient(
        tokens: tokens,
        client: MockClient(handler),
        baseUrl: 'http://api.test',
      ),
      tokens: tokens,
    );

void main() {
  test(
    "sans jeton, la session démarre déconnectée sans appeler l'API",
    () async {
      var calls = 0;
      final c = _controller((_) async {
        calls++;
        return http.Response('{}', 200);
      }, MemoryTokenStore());
      await c.restore();
      expect(c.status, AuthStatus.signedOut);
      expect(calls, 0);
    },
  );

  test('un jeton valide restaure la session', () async {
    final c = _controller(
      (_) async => http.Response(jsonEncode({'data': _user}), 200),
      MemoryTokenStore('t'),
    );
    await c.restore();
    expect(c.status, AuthStatus.signedIn);
    expect(c.user?.name, 'Awa Diop');
  });

  test('un jeton refusé (401) est effacé', () async {
    final tokens = MemoryTokenStore('périmé');
    final c = _controller(
      (_) async => http.Response('{"code":"unauthenticated"}', 401),
      tokens,
    );
    await c.restore();
    expect(c.status, AuthStatus.signedOut);
    expect(await tokens.read(), isNull);
  });

  test('une panne réseau au démarrage conserve le jeton', () async {
    final tokens = MemoryTokenStore('valide');
    final c = _controller((_) async => throw Exception('hors ligne'), tokens);
    await c.restore();
    expect(c.status, AuthStatus.signedOut);
    expect(await tokens.read(), 'valide');
  });

  test(
    "la connexion envoie le nom d'appareil et enregistre le jeton",
    () async {
      final tokens = MemoryTokenStore();
      Map<String, dynamic>? sent;
      final c = _controller((r) async {
        sent = jsonDecode(r.body);
        return http.Response(
          jsonEncode({
            'data': {'token': '1|secret', 'user': _user},
          }),
          200,
        );
      }, tokens);
      await c.login('+221771234567', 'motdepasse');
      expect(sent?['device_name'], 'Application mobile');
      expect(await tokens.read(), '1|secret');
      expect(c.status, AuthStatus.signedIn);
    },
  );

  test('un échec de connexion ne crée aucune session', () async {
    final tokens = MemoryTokenStore();
    final c = _controller(
      (_) async => http.Response(
        '{"code":"invalid_credentials","message":"Identifiants incorrects."}',
        401,
      ),
      tokens,
    );
    await expectLater(
      c.login('+221771234567', 'x'),
      throwsA(isA<ApiException>()),
    );
    expect(await tokens.read(), isNull);
    expect(c.status, AuthStatus.unknown);
  });

  test(
    'la déconnexion efface le jeton même si le serveur est injoignable',
    () async {
      final tokens = MemoryTokenStore('t');
      final c = _controller((_) async => throw Exception('hors ligne'), tokens);
      await c.logout();
      expect(await tokens.read(), isNull);
      expect(c.status, AuthStatus.signedOut);
    },
  );

  test(
    "un jeton révoqué en cours d'utilisation déconnecte l'application",
    () async {
      final tokens = MemoryTokenStore('t');
      final c = _controller(
        (_) async => http.Response('{"code":"unauthenticated"}', 401),
        tokens,
      );
      await expectLater(
        c.api.get('/my/listings'),
        throwsA(isA<ApiException>()),
      );
      expect(c.status, AuthStatus.signedOut);
      expect(await tokens.read(), isNull);
    },
  );

  test('un trousseau illisible ne bloque pas le démarrage', () async {
    final c = _controller(
      (_) async => http.Response('{}', 200),
      _BrokenStore(),
    );
    await c.restore();
    expect(c.status, AuthStatus.signedOut);
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
