import 'package:flutter/material.dart';

import 'auth/auth_controller.dart';
import 'auth/auth_screens.dart';
import 'core/api_client.dart';
import 'core/token_store.dart';

void main() {
  final tokens = SecureTokenStore();
  final api = ApiClient(tokens: tokens);
  runApp(
    MainApp(
      auth: AuthController(api: api, tokens: tokens)..restore(),
    ),
  );
}

class MainApp extends StatelessWidget {
  const MainApp({super.key, required this.auth});

  final AuthController auth;

  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'ChezVous',
    debugShowCheckedModeBanner: false,
    theme: ThemeData(
      colorSchemeSeed: const Color(0xFF1B5E20),
      useMaterial3: true,
    ),
    home: ListenableBuilder(
      listenable: auth,
      builder: (context, _) => switch (auth.status) {
        AuthStatus.unknown => const Scaffold(
          body: Center(child: CircularProgressIndicator()),
        ),
        AuthStatus.signedOut => LoginScreen(auth: auth),
        AuthStatus.signedIn => HomeScreen(auth: auth),
      },
    ),
  );
}

/// Accueil provisoire : la recherche arrive au lot B.
class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key, required this.auth});

  final AuthController auth;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('ChezVous'),
      actions: [
        TextButton(onPressed: auth.logout, child: const Text('Se déconnecter')),
      ],
    ),
    body: Center(child: Text('Bonjour ${auth.user?.name ?? ''}')),
  );
}
