import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'auth/auth_controller.dart';
import 'core/api_client.dart';
import 'core/token_store.dart';
import 'shell/app_shell.dart';
import 'shell/welcome_screen.dart';
import 'ui/theme.dart';

const _welcomeKey = 'chezvous_welcome_seen';

Future<void> main() async {
  // Le trousseau (stockage du jeton) passe par un canal de plateforme : le moteur doit être prêt avant restore().
  WidgetsFlutterBinding.ensureInitialized();
  // Bienvenue une seule fois ; un trousseau illisible ne doit jamais bloquer le démarrage.
  var welcome = false;
  try {
    welcome = await const FlutterSecureStorage().read(key: _welcomeKey) == null;
  } catch (_) {}
  final tokens = SecureTokenStore();
  final api = ApiClient(tokens: tokens);
  runApp(
    MainApp(
      auth: AuthController(api: api, tokens: tokens)..restore(),
      showWelcome: welcome,
      onWelcomeSeen: () async {
        try {
          await const FlutterSecureStorage().write(
            key: _welcomeKey,
            value: '1',
          );
        } catch (_) {}
      },
    ),
  );
}

class MainApp extends StatefulWidget {
  const MainApp({
    super.key,
    required this.auth,
    this.showWelcome = false,
    this.onWelcomeSeen,
  });

  final AuthController auth;
  final bool showWelcome;
  final Future<void> Function()? onWelcomeSeen;

  @override
  State<MainApp> createState() => _MainAppState();
}

class _MainAppState extends State<MainApp> {
  late bool _welcome = widget.showWelcome;
  int _initialTab = 0;

  void _enter(int tab) {
    widget.onWelcomeSeen?.call();
    setState(() {
      _welcome = false;
      _initialTab = tab;
    });
  }

  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'ChezVous',
    debugShowCheckedModeBanner: false,
    theme: buildTheme(),
    // La recherche est publique : on n'attend que la restauration de la session, jamais une connexion.
    home: ListenableBuilder(
      listenable: widget.auth,
      builder: (context, _) => widget.auth.status == AuthStatus.unknown
          ? const Scaffold(body: Center(child: CircularProgressIndicator()))
          : _welcome
          ? WelcomeScreen(onStart: () => _enter(0), onSignIn: () => _enter(4))
          : AppShell(auth: widget.auth, initialTab: _initialTab),
    ),
  );
}
