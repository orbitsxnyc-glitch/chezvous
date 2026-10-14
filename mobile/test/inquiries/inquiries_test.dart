import 'dart:convert';

import 'package:chezvous/core/api_client.dart';
import 'package:chezvous/core/token_store.dart';
import 'package:chezvous/inquiries/inquiries_repository.dart';
import 'package:chezvous/inquiries/inquiries_screens.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

http.Response _json(Object body, [int status = 200]) => http.Response.bytes(
  utf8.encode(jsonEncode(body)),
  status,
  headers: {'content-type': 'application/json; charset=utf-8'},
);

InquiriesRepository _repo(MockClientHandler h) => InquiriesRepository(
  ApiClient(
    tokens: MemoryTokenStore('t'),
    client: MockClient(h),
    baseUrl: 'http://api.test',
  ),
);

final _detail = {
  'id': 'inq1',
  'status': 'visit_scheduled',
  'role': 'requester',
  'unread': 0,
  'listing': {'id': 'l1', 'reference': 'DK-YOF-000001', 'title': 'Beau F3'},
  'last_activity_at': '2026-10-14T10:00:00Z',
  'can_respond': true,
  'messages': [
    {
      'id': 1,
      'mine': true,
      'body': 'Bonjour, est-il disponible ?',
      'created_at': '2026-10-14T09:00:00Z',
      'sender_name': 'Moi',
    },
    {
      'id': 2,
      'mine': false,
      'body': 'Oui, vous pouvez visiter demain.',
      'created_at': '2026-10-14T09:30:00Z',
      'sender_name': 'Awa D.',
    },
  ],
  'visits': [
    {
      'id': 'v1',
      'status': 'proposed',
      'proposed_slots': ['2026-10-15T10:00:00Z'],
      'scheduled_at': null,
      'proposed_by_me': false,
      'awaiting_me': true,
      'cancel_reason': null,
      'feedback': null,
    },
  ],
};

void main() {
  test("l'ouverture d'une demande passe par la plateforme et n'envoie que le message", () async {
    late http.Request seen;
    final repo = _repo((r) async {
      seen = r;
      return _json({'data': _detail}, 201);
    });
    await repo.open('l1', 'Bonjour');
    expect(seen.url.path, '/api/v1/listings/l1/inquiries');
    expect(jsonDecode(seen.body), {
      'message': 'Bonjour',
      'preferred_channel': 'message',
    });
  });

  test('la confirmation de visite envoie le créneau en UTC', () async {
    late http.Request seen;
    final repo = _repo((r) async {
      seen = r;
      return _json({'data': {}});
    });
    await repo.confirmVisit('v1', DateTime.utc(2026, 10, 15, 10));
    expect(seen.method, 'PATCH');
    expect(jsonDecode(seen.body), {
      'action': 'confirm',
      'slot': '2026-10-15T10:00:00.000Z',
    });
  });

  test('une notification ne contient que du texte sans donnée personnelle', () {
    final n = AppNotification.fromJson({
      'id': 'n1',
      'type': 'visit.proposed',
      'data': {'listing': 'DK-YOF-000001'},
      'read': false,
      'created_at': '2026-10-14T10:00:00Z',
    });
    expect(n.text, 'Nouvelle proposition de visite (DK-YOF-000001)');
    expect(
      AppNotification.fromJson({'id': 'n2', 'type': 'inconnu', 'data': []})
          .text,
      'Nouvelle notification',
    );
  });

  testWidgets(
    'la conversation affiche les messages et permet d\'accepter un créneau proposé',
    (tester) async {
      final calls = <String>[];
      final repo = _repo((r) async {
        calls.add('${r.method} ${r.url.path}');
        return _json({'data': _detail});
      });
      await tester.pumpWidget(
        MaterialApp(
          home: ThreadScreen(repository: repo, id: 'inq1'),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Oui, vous pouvez visiter demain.'), findsOneWidget);
      expect(find.text('Visite proposée'), findsOneWidget);

      await tester.tap(find.text('Accepter'));
      await tester.pumpAndSettle();
      expect(calls, contains('PATCH /api/v1/visits/v1'));
    },
  );

  testWidgets("l'envoi d'un message vide ne fait aucun appel", (tester) async {
    final calls = <String>[];
    final repo = _repo((r) async {
      calls.add('${r.method} ${r.url.path}');
      return _json({'data': _detail});
    });
    await tester.pumpWidget(
      MaterialApp(
        home: ThreadScreen(repository: repo, id: 'inq1'),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Envoyer'));
    await tester.pumpAndSettle();
    expect(calls.where((c) => c.startsWith('POST')), isEmpty);
  });
}
