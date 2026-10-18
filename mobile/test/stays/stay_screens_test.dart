import 'dart:convert';

import 'package:chezvous/core/api_client.dart';
import 'package:chezvous/core/token_store.dart';
import 'package:chezvous/stays/my_stays_screen.dart';
import 'package:chezvous/stays/stays_repository.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

/// Réponse JSON en UTF-8 (les libellés français contiennent des accents).
http.Response _json(Object body, [int status = 200]) => http.Response.bytes(
  utf8.encode(jsonEncode(body)),
  status,
  headers: {'content-type': 'application/json; charset=utf-8'},
);

Map<String, dynamic> _booking(String state) => {
  'id': 'B1',
  'state': state,
  'unit': {'name': 'Studio Almadies', 'locality': 'Almadies'},
  'check_in': '2027-03-10',
  'check_out': '2027-03-13',
  'guests': 2,
  'total_xof': 120000,
  'guest': {'first_name': 'Awa'},
  'lines': [
    {
      'code': 'accommodation',
      'label': 'Hébergement (3 nuits)',
      'amount_xof': 120000,
    },
  ],
  'payment_due_at': '2099-01-01T10:00:00+00:00',
  'cancellation_policy': {'code': 'moderate'},
};

void main() {
  testWidgets(
    '« J\'ai payé » ne confirme rien : le séjour reste en attente de vérification',
    (tester) async {
      final paths = <String>[];
      final client = MockClient((r) async {
        paths.add('${r.method} ${r.url.path}');
        if (r.url.path.endsWith('/my/bookings/B1')) {
          return _json({'data': _booking('accepted')}, 200);
        }
        if (r.url.path.endsWith('/payment-intents')) {
          return http.Response(
            jsonEncode({
              'data': {
                'id': 'P1',
                'status': 'created',
                'amount_xof': 120000,
                'reference': 'CV-ABCD1234',
                'instructions': {
                  'methods': [
                    {'label': 'Wave', 'details': '77 000 00 00'},
                  ],
                },
              },
            }),
            201,
          );
        }
        return http.Response(
          jsonEncode({
            'data': {
              'id': 'P1',
              'status': 'pending',
              'amount_xof': 120000,
              'reference': 'CV-ABCD1234',
            },
          }),
          200,
        );
      });
      final repo = StaysRepository(
        ApiClient(client: client, tokens: MemoryTokenStore('t')),
      );

      // Écran haut : tout le bloc de règlement est visible sans défilement.
      tester.view.physicalSize = const Size(800, 2000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);

      await tester.pumpWidget(
        MaterialApp(
          home: MyStayDetailScreen(repository: repo, id: 'B1'),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Acceptée : à régler'), findsOneWidget);

      await tester.tap(find.text('Voir comment payer'));
      await tester.pumpAndSettle();
      expect(find.textContaining('CV-ABCD1234'), findsOneWidget);
      expect(find.textContaining('Wave'), findsWidgets);

      await tester.enterText(find.byType(TextField), 'WV-998877');
      await tester.tap(find.text('J\'ai payé'));
      await tester.pumpAndSettle();

      expect(find.textContaining('en cours de vérification'), findsOneWidget);
      expect(find.text('Séjour confirmé'), findsNothing);
      expect(
        paths.any((p) => p.contains('confirm')),
        isFalse,
      ); // aucun appel de confirmation
    },
  );

  testWidgets(
    'une demande en attente affiche l\'échéance de réponse et rien n\'est débité',
    (tester) async {
      final b = _booking('requested')
        ..['respond_by'] = '2099-01-01T10:00:00+00:00';
      final client = MockClient((r) async => _json({'data': b}, 200));
      final repo = StaysRepository(
        ApiClient(client: client, tokens: MemoryTokenStore('t')),
      );

      await tester.pumpWidget(
        MaterialApp(
          home: MyStayDetailScreen(repository: repo, id: 'B1'),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.textContaining('rien n\'est débité'), findsOneWidget);
      expect(find.text('Voir comment payer'), findsNothing);
    },
  );
}
