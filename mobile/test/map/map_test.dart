import 'dart:convert';

import 'package:chezvous/core/api_client.dart';
import 'package:chezvous/core/format.dart';
import 'package:chezvous/core/token_store.dart';
import 'package:chezvous/listings/listings_repository.dart';
import 'package:chezvous/listings/map_view.dart';
import 'package:chezvous/listings/models.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

http.Response _json(Object body) => http.Response.bytes(
  utf8.encode(jsonEncode(body)),
  200,
  headers: {'content-type': 'application/json; charset=utf-8'},
);

Map<String, dynamic> _point(int i) => {
  'id': 'p$i',
  'reference': 'CV-2026-00000$i',
  'transaction': 'rent_long',
  'title': 'Appartement $i',
  'price_xof': 250000 + i * 100000,
  'lat': 14.7167 + i * 0.02,
  'lon': -17.4677 + i * 0.02,
  'cover': null,
  'locality': 'Yoff',
  'property_type_label': 'Appartement',
};

ListingsRepository _repo(MockClientHandler h) => ListingsRepository(
  ApiClient(
    tokens: MemoryTokenStore(),
    client: MockClient(h),
    baseUrl: 'http://api.test',
    retryDelay: Duration.zero,
  ),
);

void main() {
  test('prix compacts pour les pastilles et emprise dans l\'ordre attendu par l\'API', () {
    expect(compactPrice(450000), '450 k');
    expect(compactPrice(1250000), '1,25 M');
    expect(compactPrice(2000000), '2 M');
    expect(compactPrice(800), '800');
    expect(compactPrice(null), '—');
    expect(bboxParam(-17.52, 14.7, -17.45, 14.78), '-17.52,14.7,-17.45,14.78');
  });

  test('la carte transmet l\'emprise et les filtres, et signale une réponse tronquée', () async {
    late Uri seen;
    final repo = _repo((r) async {
      seen = r.url;
      return _json({
        'data': [_point(1)],
        'meta': {'count': 1, 'truncated': true},
      });
    });
    final r = await repo.mapPoints(
      '-17.6,14.6,-17.3,14.85',
      const SearchFilters(locality: 'yoff', priceMax: 500000),
    );
    expect(seen.path, '/api/v1/listings/map');
    expect(seen.queryParameters, {
      'transaction': 'rent_long',
      'locality': 'yoff',
      'price_max': '500000',
      'bbox': '-17.6,14.6,-17.3,14.85',
    });
    expect(r.points.single.reference, 'CV-2026-000001');
    expect(r.truncated, isTrue);
  });

  testWidgets(
    'la carte affiche les pastilles de prix et un aperçu au toucher',
    (tester) async {
      MapPoint? opened;
      final repo = _repo(
        (r) async => _json({
          'data': [_point(1), _point(2)],
          'meta': {'count': 2, 'truncated': false},
        }),
      );
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: ListingsMapView(
              repository: repo,
              filters: const SearchFilters(),
              onOpen: (p) => opened = p,
              showTiles: false,
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('2 biens dans cette zone'), findsOneWidget);
      expect(find.text('350 k'), findsOneWidget);

      await tester.tap(find.text('350 k'));
      await tester.pumpAndSettle();
      expect(find.text('Appartement 1'), findsOneWidget);
      expect(find.text('Appartement · Yoff'), findsOneWidget);

      await tester.tap(find.text('Appartement 1'));
      await tester.pump();
      expect(opened?.reference, 'CV-2026-000001');
    },
  );

  testWidgets('une panne réseau ne casse pas la carte : message clair', (
    tester,
  ) async {
    final repo = _repo((r) async => throw Exception('hors ligne'));
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: ListingsMapView(
            repository: repo,
            filters: const SearchFilters(),
            onOpen: (_) {},
            showTiles: false,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.textContaining('Carte indisponible'), findsOneWidget);
  });

  testWidgets('dézoomée, la carte affiche des groupes avec leur nombre', (
    tester,
  ) async {
    final repo = _repo(
      (r) async => _json({
        'data': [
          {
            'cluster': true,
            'count': 7,
            'lat': 14.72,
            'lon': -17.47,
            'min_price_xof': 100000,
          },
          {
            'cluster': true,
            'count': 3,
            'lat': 14.9,
            'lon': -17.2,
            'min_price_xof': 250000,
          },
        ],
        'meta': {'clustered': true, 'count': 10, 'truncated': false},
      }),
    );
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: ListingsMapView(
            repository: repo,
            filters: const SearchFilters(),
            onOpen: (_) {},
            showTiles: false,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('10 biens dans cette zone'), findsOneWidget);
    expect(find.text('7'), findsOneWidget);
  });

  test("la demande de carte transmet le niveau de zoom et sépare groupes et annonces", () async {
    late Uri seen;
    final repo = _repo((r) async {
      seen = r.url;
      return _json({
        'data': [
          {
            'cluster': true,
            'count': 4,
            'lat': 14.7,
            'lon': -17.4,
            'min_price_xof': 1,
          },
          _point(1),
        ],
        'meta': {'truncated': false},
      });
    });
    final r = await repo.mapPoints(
      '-17.6,14.6,-17.3,14.85',
      const SearchFilters(),
      zoom: 11,
    );
    expect(seen.queryParameters['zoom'], '11');
    expect(r.clusters.single.count, 4);
    expect(r.points.single.reference, 'CV-2026-000001');
  });
}
