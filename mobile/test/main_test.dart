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
  testWidgets('sans jeton, affiche la connexion', (tester) async {
    final auth = _auth(MockClient((_) async => http.Response('{}', 200)));
    await auth.restore();
    await tester.pumpWidget(MainApp(auth: auth));
    expect(find.text('Se connecter'), findsOneWidget);
    expect(find.text('Créer un compte'), findsOneWidget);
  });
}
