import 'package:flutter/material.dart';

import '../auth/auth_controller.dart';
import '../advertiser/editor_repository.dart';
import '../advertiser/my_listings.dart';
import '../auth/auth_screens.dart';
import '../engagement/alerts_screen.dart';
import '../engagement/favorites_screen.dart';
import '../inquiries/inquiries_repository.dart';
import '../inquiries/inquiries_screens.dart';
import '../listings/listings_repository.dart';
import '../listings/search_screen.dart';

/// Navigation principale. La recherche est publique ; favoris, messages et compte demandent une connexion.
class AppShell extends StatefulWidget {
  const AppShell({super.key, required this.auth});

  final AuthController auth;

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  int _tab = 0;
  late final ListingsRepository _listings = ListingsRepository(widget.auth.api);
  late final InquiriesRepository _inquiries = InquiriesRepository(
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
        SearchScreen(
          key: key,
          repository: _listings,
          signedIn: signedIn,
          inquiries: signedIn ? _inquiries : null,
        ),
        signedIn
            ? FavoritesScreen(
                key: key,
                repository: _listings,
                inquiries: _inquiries,
              )
            : const _SignInPrompt(what: 'vos favoris'),
        signedIn
            ? MessagesScreen(key: key, repository: _inquiries)
            : const _SignInPrompt(what: 'vos messages'),
        signedIn
            ? _Account(
                auth: widget.auth,
                listings: _listings,
                inquiries: _inquiries,
              )
            : LoginScreen(auth: widget.auth),
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
              icon: Icon(Icons.chat_bubble_outline),
              label: 'Messages',
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
  const _SignInPrompt({required this.what});

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
  const _Account({
    required this.auth,
    required this.listings,
    required this.inquiries,
  });

  final AuthController auth;
  final ListingsRepository listings;
  final InquiriesRepository inquiries;

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
        const SizedBox(height: 16),
        ListTile(
          leading: const Icon(Icons.notifications_none),
          title: const Text('Notifications'),
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => NotificationsScreen(repository: inquiries),
            ),
          ),
        ),
        ListTile(
          leading: const Icon(Icons.home_work_outlined),
          title: const Text('Mes annonces'),
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => MyListingsScreen(
                repository: MyListingsRepository(auth.api),
                editor: EditorRepository(auth.api),
              ),
            ),
          ),
        ),
        ListTile(
          leading: const Icon(Icons.bookmark_border),
          title: const Text('Mes alertes de recherche'),
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => AlertsScreen(repository: listings),
            ),
          ),
        ),
        const SizedBox(height: 16),
        OutlinedButton(
          onPressed: auth.logout,
          child: const Text('Se déconnecter'),
        ),
      ],
    ),
  );
}
