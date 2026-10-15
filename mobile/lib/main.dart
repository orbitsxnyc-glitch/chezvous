import 'package:flutter/material.dart';

import 'auth/auth_controller.dart';
import 'core/api_client.dart';
import 'core/token_store.dart';
import 'shell/app_shell.dart';
import 'ui/theme.dart';

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
    theme: buildTheme(),
    // La recherche est publique : on n'attend que la restauration de la session, jamais une connexion.
    home: ListenableBuilder(
      listenable: auth,
      builder: (context, _) => auth.status == AuthStatus.unknown
          ? const Scaffold(body: Center(child: CircularProgressIndicator()))
          : AppShell(auth: auth),
    ),
  );
}
