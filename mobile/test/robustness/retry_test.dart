import 'dart:convert';

import 'package:chezvous/auth/auth_controller.dart';
import 'package:chezvous/core/api_client.dart';
import 'package:chezvous/core/token_store.dart';
import 'package:chezvous/listings/listing_detail_screen.dart';
import 'package:chezvous/listings/listings_repository.dart';
import 'package:chezvous/listings/search_screen.dart';
import 'package:chezvous/shell/app_shell.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

http.Response _json(Object body, [int status = 200]) => http.Response.bytes(
  utf8.encode(jsonEncode(body)),
  status,
  headers: {'content-type': 'application/json; charset=utf-8'},
);

Map<String, dynamic> _row() => {
  'id': 'u1',
  'reference': 'CV-2026-000001',
  'transaction': 'rent_long',
  'title': 'Appartement F3 à Yoff',
  'price_xof': 300000,
  'negotiable': false,
  'furnished': false,
  'availability': {'state': 'available', 'confirmed_at': null},
};

ApiClient _api(MockClientHandler h) => ApiClient(
  tokens: MemoryTokenStore('t'),
  client: MockClient(h),
  baseUrl: 'http://api.test',
  retryDelay: Duration.zero,
);

void main() {
  testWidgets(
    'la recherche coupée propose « Réessayer » et affiche les annonces au second essai',
    (tester) async {
      var healthy = false;
      final repo = ListingsRepository(
        _api((r) async {
          if (r.url.path.endsWith('/localities')) return _json({'data': []});
          if (!healthy) throw Exception('hors ligne');
          return _json({
            'data': [_row()],
            'meta': {'next_cursor': null},
          });
        }),
      );
      await tester.pumpWidget(
        MaterialApp(home: SearchScreen(repository: repo, signedIn: false)),
      );
      await tester.pumpAndSettle();
      expect(find.textContaining('Connexion impossible'), findsOneWidget);
      expect(find.text('Réessayer'), findsOneWidget);

      healthy = true;
      await tester.tap(find.text('Réessayer'));
      await tester.pumpAndSettle();
      expect(find.text('Appartement F3 à Yoff'), findsOneWidget);
      expect(find.text('Réessayer'), findsNothing);
    },
  );

  testWidgets(
    "une fiche en panne passagère propose « Réessayer » ; une annonce retirée n'en propose pas",
    (tester) async {
      var status = 503;
      final repo = ListingsRepository(
        _api((r) async => _json({'message': 'x'}, status)),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: ListingDetailScreen(repository: repo, reference: 'CV-1'),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.textContaining('rencontre un problème'), findsOneWidget);
      expect(find.text('Réessayer'), findsOneWidget);

      status = 404;
      await tester.tap(find.text('Réessayer'));
      await tester.pumpAndSettle();
      expect(find.textContaining("n'est plus disponible"), findsOneWidget);
      expect(find.text('Réessayer'), findsNothing);
    },
  );

  testWidgets(
    "une session révoquée en cours d'usage déconnecte et le dit une fois",
    (tester) async {
      final tokens = MemoryTokenStore('t');
      final api = ApiClient(
        tokens: tokens,
        client: MockClient(
          (r) async => _json({'code': 'unauthenticated'}, 401),
        ),
        baseUrl: 'http://api.test',
        retryDelay: Duration.zero,
      );
      final auth = AuthController(api: api, tokens: tokens)
        ..status = AuthStatus.signedIn
        ..user = const AppUser(
          id: 'u',
          name: 'Awa',
          phone: '+221771234567',
          roles: [],
        );
      await tester.pumpWidget(MaterialApp(home: AppShell(auth: auth)));
      await tester.pump();

      await expectLater(api.get('/my/listings'), throwsA(isA<ApiException>()));
      await tester.pumpAndSettle();

      expect(auth.status, AuthStatus.signedOut);
      expect(await tokens.read(), isNull);
      expect(
        find.text('Votre session a expiré : reconnectez-vous.'),
        findsOneWidget,
      );
      expect(auth.sessionExpired, isFalse);
    },
  );
}
