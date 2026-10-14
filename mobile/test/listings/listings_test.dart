import 'dart:convert';

import 'package:chezvous/core/api_client.dart';
import 'package:chezvous/core/token_store.dart';
import 'package:chezvous/listings/listings_repository.dart';
import 'package:chezvous/listings/models.dart';
import 'package:chezvous/listings/search_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

http.Response _json(Object body, [int status = 200]) => http.Response.bytes(
  utf8.encode(jsonEncode(body)),
  status,
  headers: {'content-type': 'application/json; charset=utf-8'},
);

Map<String, dynamic> _summary(String ref, {String title = 'Appartement F3'}) =>
    {
      'id': 'ulid-$ref',
      'reference': ref,
      'transaction': 'rent_long',
      'title': title,
      'price_xof': 450000,
      'negotiable': true,
      'property_type': 'Appartement',
      'locality': {'name': 'Yoff', 'slug': 'yoff'},
      'surface_m2': 90,
      'bedrooms': 3,
      'furnished': false,
      'agency': null,
      'cover': null,
      'availability': {
        'state': 'available',
        'confirmed_at': '2026-10-13T10:00:00Z',
      },
    };

ListingsRepository _repo(MockClientHandler h) => ListingsRepository(
  ApiClient(
    tokens: MemoryTokenStore(),
    client: MockClient(h),
    baseUrl: 'http://api.test',
  ),
);

void main() {
  test('la recherche transmet les filtres et lit le curseur', () async {
    late Uri seen;
    final repo = _repo((r) async {
      seen = r.url;
      return _json({
        'data': [_summary('DK-YOF-000001')],
        'meta': {'next_cursor': 'abc'},
      });
    });
    final page = await repo.search(
      const SearchFilters(locality: 'yoff', bedroomsMin: 2, priceMax: 500000),
      cursor: 'c1',
    );
    expect(seen.path, '/api/v1/listings');
    expect(seen.queryParameters, {
      'transaction': 'rent_long',
      'locality': 'yoff',
      'bedrooms_min': '2',
      'price_max': '500000',
      'cursor': 'c1',
    });
    expect(page.items.single.reference, 'DK-YOF-000001');
    expect(page.items.single.priceXof, 450000);
    expect(page.nextCursor, 'abc');
  });

  test('les filtres vides ne sont pas envoyés', () {
    expect(const SearchFilters().toParams(), {'transaction': 'rent_long'});
    expect(
      const SearchFilters(locality: 'yoff')
          .copyWith(locality: () => null)
          .toParams(),
      {'transaction': 'rent_long'},
    );
  });

  test("la fiche expose le Passeport, les frais saisis et jamais de coordonnées de l'annonceur", () async {
    final repo = _repo(
      (r) async => _json({
        'data': {
          ..._summary('DK-YOF-000001'),
          'description': 'Beau bien.',
          'entry_costs': {
            'rent_xof': 450000,
            'deposit_xof': 900000,
            'advance_xof': null,
            'agency_fee_xof': 450000,
            'other_fees_xof': null,
            'disclaimer': 'À confirmer.',
          },
          'property': {
            'type': {'code': 'apartment', 'label': 'Appartement'},
            'locality': {'name': 'Yoff', 'slug': 'yoff'},
            'surface_m2': 90,
            'bedrooms': 3,
            'furnished': false,
            'amenities': [
              {'code': 'parking', 'label': 'Parking'},
            ],
            'images': [
              {
                'id': 'i1',
                'is_cover': true,
                'urls': {'large': 'http://img/l.jpg'},
              },
            ],
          },
          'passport': {
            'reference': 'DK-YOF-000001',
            'availability': {'state': 'to_reconfirm', 'confirmed_at': null},
            'checks': [
              {
                'type': 'phone',
                'status': 'passed',
                'performed_at': '2026-10-01T00:00:00Z',
              },
            ],
            'notice': 'Un contrôle de document ne garantit pas la propriété juridique du bien.',
          },
          'advertiser': {'name': 'Awa D.', 'account_type': 'individual'},
        },
      }),
    );
    final d = await repo.detail('DK-YOF-000001');
    expect(d.imageUrls, ['http://img/l.jpg']);
    expect(d.amenities, ['Parking']);
    expect(d.costs['Caution'], 900000);
    expect(d.costs['Avance'], isNull);
    expect(d.checks.single.type, 'phone');
    expect(d.summary.availability.state, 'to_reconfirm');
    expect(d.passportNotice, contains('propriété juridique'));
    expect(d.advertiserName, 'Awa D.');
  });

  test('un résumé incomplet ne fait pas planter la lecture', () {
    final s = ListingSummary.fromJson({'id': 'x', 'reference': 'R'});
    expect(s.transaction, 'rent_long');
    expect(s.availability.state, 'stale');
    expect(s.priceXof, isNull);
  });

  testWidgets(
    "l'écran de recherche affiche les annonces et un message si rien ne correspond",
    (tester) async {
      var empty = false;
      final repo = _repo((r) async {
        if (r.url.path.endsWith('/localities')) return _json({'data': []});
        return _json({
          'data': empty
              ? []
              : [_summary('DK-YOF-000001', title: 'Beau F3 à Yoff')],
          'meta': {'next_cursor': null},
        });
      });
      await tester.pumpWidget(
        MaterialApp(home: SearchScreen(repository: repo, signedIn: false)),
      );
      await tester.pumpAndSettle();
      expect(find.text('Beau F3 à Yoff'), findsOneWidget);
      expect(find.textContaining('450 000 FCFA / mois'), findsOneWidget);

      empty = true;
      await tester.tap(find.byType(DropdownButton<String>).first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Vente').last);
      await tester.pumpAndSettle();
      expect(find.textContaining('Aucune annonce'), findsOneWidget);
    },
  );
}
