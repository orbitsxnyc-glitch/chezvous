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
    "la recherche est publique : l'application s'ouvre dessus, la connexion est dans Compte",
    (tester) async {
      final auth = _auth(MockClient((_) async => http.Response('{}', 200)));
      await auth.restore();
      await tester.pumpWidget(MainApp(auth: auth));
      await tester.pumpAndSettle();
      expect(find.text('Recherche'), findsOneWidget);
      expect(find.textContaining('Aucune annonce'), findsOneWidget);

      await tester.tap(find.text('Compte'));
      await tester.pumpAndSettle();
      expect(find.text('Se connecter'), findsOneWidget);
      expect(find.text('Créer un compte'), findsOneWidget);
    },
  );
}
