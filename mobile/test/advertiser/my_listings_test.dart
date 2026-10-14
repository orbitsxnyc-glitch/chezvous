import 'dart:convert';

import 'package:chezvous/advertiser/my_listings.dart';
import 'package:chezvous/core/api_client.dart';
import 'package:chezvous/core/token_store.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

http.Response _json(Object body, [int status = 200]) => http.Response.bytes(
  utf8.encode(jsonEncode(body)),
  status,
  headers: {'content-type': 'application/json; charset=utf-8'},
);

Map<String, dynamic> _l(
  String id,
  String status, {
  Map<String, dynamic> extra = const {},
}) => {
  'id': id,
  'reference': 'DK-YOF-$id',
  'status': status,
  'transaction': 'rent_long',
  'title': 'Annonce $id',
  'price_xof': 300000,
  'negotiable': false,
  'expires_at': '2026-12-01T00:00:00Z',
  ...extra,
};

MyListingsRepository _repo(MockClientHandler h) => MyListingsRepository(
  ApiClient(
    tokens: MemoryTokenStore('t'),
    client: MockClient(h),
    baseUrl: 'http://api.test',
  ),
);

void main() {
  test('la confirmation de disponibilité envoie la réponse choisie', () async {
    late http.Request seen;
    final repo = _repo((r) async {
      seen = r;
      return _json({'data': {}});
    });
    await repo.confirmAvailability('abc', 'reserved');
    expect(seen.url.path, '/api/v1/my/listings/abc/availability');
    expect(jsonDecode(seen.body), {'response': 'reserved'});
  });

  testWidgets(
    "les actions dépendent de l'état : pas de pause sur un brouillon, reprise sur une pause",
    (tester) async {
      final repo = _repo(
        (r) async => _json({
          'data': [
            _l('A', 'published'),
            _l('B', 'paused'),
            _l(
              'C',
              'draft',
              extra: {
                'missing_fields': ['photos'],
              },
            ),
            _l(
              'D',
              'rejected',
              extra: {
                'rejection': {'reason': 'x', 'comment': 'Photos floues'},
              },
            ),
          ],
        }),
      );
      await tester.pumpWidget(
        MaterialApp(home: MyListingsScreen(repository: repo)),
      );
      await tester.pumpAndSettle();
      expect(find.text('Confirmer la disponibilité'), findsOneWidget);
      expect(find.text('Mettre en pause'), findsOneWidget);
      expect(find.text('Reprendre'), findsOneWidget);
      expect(find.text('Clôturer'), findsNWidgets(2));
      expect(find.textContaining('photos'), findsOneWidget);
      expect(find.textContaining('Photos floues'), findsOneWidget);
    },
  );

  testWidgets(
    'un compte sans droit de publication voit un message clair, pas une erreur technique',
    (tester) async {
      final repo = _repo(
        (r) async => _json({
          'code': 'forbidden',
          'message': 'Action non autorisée.',
        }, 403),
      );
      await tester.pumpWidget(
        MaterialApp(home: MyListingsScreen(repository: repo)),
      );
      await tester.pumpAndSettle();
      expect(find.textContaining("ne publie pas"), findsOneWidget);
    },
  );
}
