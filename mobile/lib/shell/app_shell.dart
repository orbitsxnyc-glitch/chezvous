import 'package:flutter/material.dart';

import '../auth/auth_controller.dart';
import '../auth/auth_screens.dart';
import '../engagement/alerts_screen.dart';
import '../engagement/favorites_screen.dart';
import '../listings/listings_repository.dart';
import '../listings/search_screen.dart';

/// Navigation principale. La recherche est publique ; favoris, alertes et compte demandent une connexion.
class AppShell extends StatefulWidget {
  const AppShell({super.key, required this.auth});

  final AuthController auth;

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  int _tab = 0;
  late final ListingsRepository _repository = ListingsRepository(
    widget.auth.api,
  );

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: widget.auth,
    builder: (context, _) {
      final signedIn = widget.auth.status == AuthStatus.signedIn;
      // La clé suit l'identité : à la déconnexion, aucun écran ne garde de données d'un autre compte.
      final key = ValueKey('${widget.auth.user?.id}');
      final pages = <Widget>[
        SearchScreen(key: key, repository: _repository, signedIn: signedIn),
        signedIn
            ? FavoritesScreen(key: key, repository: _repository)
            : _SignInPrompt(auth: widget.auth, what: 'vos favoris'),
        signedIn
            ? AlertsScreen(key: key, repository: _repository)
            : _SignInPrompt(auth: widget.auth, what: 'vos alertes'),
        signedIn ? _Account(auth: widget.auth) : LoginScreen(auth: widget.auth),
      ];
      return Scaffold(
        body: IndexedStack(index: _tab, children: pages),
        bottomNavigationBar: NavigationBar(
          selectedIndex: _tab,
          onDestinationSelected: (i) => setState(() => _tab = i),
          destinations: const [
            NavigationDestination(icon: Icon(Icons.search), label: 'Recherche'),
            NavigationDestination(
              icon: Icon(Icons.favorite_border),
              label: 'Favoris',
            ),
            NavigationDestination(
              icon: Icon(Icons.notifications_none),
              label: 'Alertes',
            ),
            NavigationDestination(
              icon: Icon(Icons.person_outline),
              label: 'Compte',
            ),
          ],
        ),
      );
    },
  );
}

class _SignInPrompt extends StatelessWidget {
  const _SignInPrompt({required this.auth, required this.what});

  final AuthController auth;
  final String what;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('ChezVous')),
    body: Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Text(
          'Connectez-vous (onglet Compte) pour retrouver $what.',
          textAlign: TextAlign.center,
        ),
      ),
    ),
  );
}

class _Account extends StatelessWidget {
  const _Account({required this.auth});

  final AuthController auth;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Compte')),
    body: ListView(
      padding: const EdgeInsets.all(24),
      children: [
        Text(
          auth.user?.name ?? '',
          style: Theme.of(context).textTheme.titleLarge,
        ),
        Text(auth.user?.phone ?? ''),
        const SizedBox(height: 24),
        OutlinedButton(
          onPressed: auth.logout,
          child: const Text('Se déconnecter'),
        ),
      ],
    ),
  );
}
