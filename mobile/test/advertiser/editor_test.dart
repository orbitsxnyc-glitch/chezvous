import 'dart:convert';

import 'package:chezvous/advertiser/editor_repository.dart';
import 'package:chezvous/advertiser/listing_editor.dart';
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

Map<String, dynamic> _listing({
  List<String> missing = const ['title', 'cover_image'],
  List<Map<String, dynamic>> images = const [],
  String status = 'draft',
}) => {
  'id': 'L1',
  'reference': 'DK-YOF-000001',
  'status': status,
  'transaction': 'rent_long',
  'title': null,
  'description': null,
  'price_xof': null,
  'negotiable': false,
  'missing_fields': missing,
  'property': {
    'locality_id': null,
    'furnished': false,
    'amenity_ids': [],
    'images': images,
  },
};

EditorRepository _repo(MockClientHandler h) => EditorRepository(
  ApiClient(
    tokens: MemoryTokenStore('t'),
    client: MockClient(h),
    baseUrl: 'http://api.test',
  ),
);

void main() {
  test(
    'les entiers saisis sont validés : vide efface, invalide est ignoré',
    () {
      expect(parseInt('450 000'), (valid: true, value: 450000));
      expect(parseInt(''), (valid: true, value: null));
      expect(parseInt('12,5').valid, isFalse);
      expect(parseInt('-3').valid, isFalse);
    },
  );

  test(
    "l'envoi de photos utilise le champ images[] attendu par l'API",
    () async {
      late http.Request seen;
      final repo = _repo((r) async {
        seen = r;
        return _json({'data': _listing()});
      });
      await repo.uploadPhotos('L1', [
        (name: 'a.jpg', bytes: [1, 2, 3]),
      ]);
      expect(seen.url.path, '/api/v1/my/listings/L1/images');
      expect(seen.method, 'POST');
      expect(seen.headers['content-type'], startsWith('multipart/form-data'));
      expect(seen.body, contains('name="images[]"'));
      expect(seen.headers['Authorization'], 'Bearer t');
    },
  );

  test('une annonce avec une photo en traitement est signalée', () {
    final l = EditableListing.fromJson(
      _listing(
        images: [
          {'id': 'i1', 'status': 'processing', 'is_cover': false, 'urls': {}},
        ],
      ),
    );
    expect(l.processing, isTrue);
    expect(l.canSubmit, isTrue);
    expect(
      EditableListing.fromJson(_listing(status: 'published')).canSubmit,
      isFalse,
    );
  });

  testWidgets("l'éditeur affiche ce qui manque et bloque la soumission", (
    tester,
  ) async {
    final posts = <String>[];
    final repo = _repo((r) async {
      if (r.method != 'GET') posts.add('${r.method} ${r.url.path}');
      if (r.url.path.endsWith('/localities') ||
          r.url.path.endsWith('/amenities')) {
        return _json({'data': []});
      }
      return _json({'data': _listing()});
    });
    await tester.pumpWidget(
      MaterialApp(
        home: ListingEditorScreen(repository: repo, id: 'L1'),
      ),
    );
    await tester.pumpAndSettle();
    // Assistant par étapes : l'étape « Aperçu » indique ce qui manque.
    await tester.tap(find.text('Aperçu'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Il manque'), findsOneWidget);
    expect(
      find.textContaining('le titre, une photo de couverture'),
      findsOneWidget,
    );

    await tester.tap(
      find.text('Soumettre à la modération'),
      warnIfMissed: false,
    );
    await tester.pump();
    expect(posts.where((p) => p.contains('submit')), isEmpty);
  });

  testWidgets(
    "l'enregistrement envoie les montants saisis sans total calculé",
    (tester) async {
      Map<String, dynamic>? sent;
      final repo = _repo((r) async {
        if (r.method == 'PATCH') {
          sent = jsonDecode((r).body) as Map<String, dynamic>;
        }
        if (r.url.path.endsWith('/localities') ||
            r.url.path.endsWith('/amenities')) {
          return _json({'data': []});
        }
        return _json({'data': _listing()});
      });
      tester.view.physicalSize = const Size(800, 3000);
      addTearDown(tester.view.resetPhysicalSize);
      await tester.pumpWidget(
        MaterialApp(
          home: ListingEditorScreen(repository: repo, id: 'L1'),
        ),
      );
      await tester.pumpAndSettle();
      await tester.enterText(
        find.widgetWithText(TextField, 'Titre'),
        'F3 à Yoff',
      );
      await tester.enterText(
        find.widgetWithText(TextField, 'Loyer mensuel (FCFA)'),
        '450 000',
      );
      await tester.enterText(
        find.widgetWithText(TextField, 'Caution (mois de loyer)'),
        'abc',
      );
      await tester.tap(find.text('Enregistrer').first);
      await tester.pumpAndSettle();
      expect(sent?['title'], 'F3 à Yoff');
      expect(sent?['price_xof'], 450000);
      expect(sent?.containsKey('deposit_months'), isFalse);
      expect(sent?.keys.any((k) => k.contains('total')), isFalse);
    },
  );
}
