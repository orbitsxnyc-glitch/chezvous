import 'dart:convert';

import 'package:chezvous/core/api_client.dart';
import 'package:chezvous/core/token_store.dart';
import 'package:chezvous/stays/stays_repository.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  test('isoDay et nightsBetween suivent l\'intervalle demi-ouvert', () {
    expect(isoDay(DateTime(2027, 3, 5)), '2027-03-05');
    expect(nightsBetween(DateTime(2027, 3, 10), DateTime(2027, 3, 13)), 3);
    expect(nightsBetween(DateTime(2027, 3, 13), DateTime(2027, 3, 10)), 0);
    expect(nightsBetween(DateTime(2027, 3, 10), DateTime(2027, 3, 10)), 0);
  });

  test('chaque état de séjour a un libellé français et seuls les séjours acceptés attendent un règlement', () {
    for (final l in bookingLabels.values) {
      expect(l.length, greaterThan(3));
    }
    StayBooking b(String state) => StayBooking.fromJson({
      'id': 'b',
      'state': state,
      'unit': {'name': 'U'},
      'check_in': '2027-03-10',
      'check_out': '2027-03-13',
      'guests': 2,
      'total_xof': 120000,
      'guest': {'first_name': 'Awa'},
    });
    expect(b('accepted').needsPayment, isTrue);
    expect(b('awaiting_payment').needsPayment, isTrue);
    expect(b('requested').needsPayment, isFalse);
    expect(b('confirmed').needsPayment, isFalse);
    expect(b('confirmed').cancellable, isTrue);
    expect(b('declined').cancellable, isFalse);
  });

  test('la demande de séjour crée un devis puis une demande avec une clé d\'idempotence', () async {
    final calls = <String>[];
    Map<String, dynamic>? requestBody;
    final client = MockClient((r) async {
      calls.add('${r.method} ${r.url.path}');
      if (r.url.path.endsWith('/quotes')) {
        return http.Response(
          jsonEncode({
            'data': {'id': 'Q1'},
          }),
          201,
        );
      }
      requestBody = jsonDecode(r.body) as Map<String, dynamic>;
      return http.Response(
        jsonEncode({
          'data': {
            'id': 'B1',
            'state': 'requested',
            'unit': {'name': 'Studio'},
            'check_in': '2027-03-10',
            'check_out': '2027-03-13',
            'guests': 2,
            'total_xof': 120000,
            'guest': {'first_name': 'Awa'},
          },
        }),
        201,
      );
    });
    final repo = StaysRepository(
      ApiClient(client: client, tokens: MemoryTokenStore('t')),
    );

    final b = await repo.request(
      'U1',
      DateTime(2027, 3, 10),
      DateTime(2027, 3, 13),
      2,
      'cle-123',
    );

    expect(calls, ['POST /api/v1/quotes', 'POST /api/v1/booking-requests']);
    expect(requestBody, {'quote': 'Q1', 'idempotency_key': 'cle-123'});
    expect(b.state, 'requested');
  });

  test(
    'la recherche transmet dates, voyageurs et quartier, et lit le prix total',
    () async {
      Uri? seen;
      final client = MockClient((r) async {
        seen = r.url;
        return http.Response(
          jsonEncode({
            'data': [
              {
                'id': 'U1',
                'name': 'Studio',
                'capacity_guests': 2,
                'property': {
                  'locality': {'name': 'Yoff'},
                },
                'rate': {'from_nightly_xof': 40000, 'min_nights': 2},
                'pricing': {'lines': [], 'total_xof': 120000},
              },
            ],
          }),
          200,
        );
      });
      final repo = StaysRepository(
        ApiClient(client: client, tokens: MemoryTokenStore()),
      );

      final r = await repo.search(
        checkIn: DateTime(2027, 3, 10),
        checkOut: DateTime(2027, 3, 13),
        guests: 3,
        locality: 'yoff',
      );

      expect(seen!.queryParameters, {
        'check_in': '2027-03-10',
        'check_out': '2027-03-13',
        'guests': '3',
        'locality': 'yoff',
      });
      expect(r.single.pricing!.totalXof, 120000);
      expect(r.single.locality, 'Yoff');
    },
  );
}
