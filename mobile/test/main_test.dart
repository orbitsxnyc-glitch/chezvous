import 'package:chezvous/auth/auth_controller.dart';
import 'package:chezvous/core/api_client.dart';
import 'package:chezvous/core/token_store.dart';
import 'package:chezvous/main.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

AuthController _auth(http.Client client, [TokenStore? tokens]) {
  final store = tokens ?? MemoryTokenStore();
  return AuthController(
    api: ApiClient(tokens: store, client: client, baseUrl: 'http://api.test'),
    tokens: store,
  );
}

void main() {
  testWidgets(
    "l'accueil et la recherche sont publics, la connexion est dans Compte",
    (tester) async {
      final auth = _auth(MockClient((_) async => http.Response('{}', 200)));
      await auth.restore();
      await tester.pumpWidget(MainApp(auth: auth));
      await tester.pumpAndSettle();
      // Accueil : promesse du produit, sans connexion.
      expect(find.textContaining('Où souhaitez-vous vivre'), findsOneWidget);

      await tester.tap(find.text('Recherche'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Aucune annonce'), findsOneWidget);

      await tester.tap(find.text('Compte'));
      await tester.pumpAndSettle();
      expect(find.text('Se connecter'), findsOneWidget);
      expect(find.text('Créer un compte'), findsOneWidget);
    },
  );

  testWidgets('favoris et messages demandent une connexion', (tester) async {
    final auth = _auth(MockClient((_) async => http.Response('{}', 200)));
    await auth.restore();
    await tester.pumpWidget(MainApp(auth: auth));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Favoris'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Connectez-vous'), findsOneWidget);

    await tester.tap(find.text('Messages'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Connectez-vous'), findsOneWidget);
  });
}
