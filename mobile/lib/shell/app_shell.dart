import 'package:flutter/material.dart';

import '../advertiser/editor_repository.dart';
import '../advertiser/my_listings.dart';
import '../auth/auth_controller.dart';
import '../auth/auth_screens.dart';
import '../engagement/alerts_screen.dart';
import '../engagement/favorites_screen.dart';
import '../home/home_screen.dart';
import '../inquiries/inquiries_repository.dart';
import '../inquiries/inquiries_screens.dart';
import '../listings/listings_repository.dart';
import '../listings/models.dart';
import '../listings/search_screen.dart';
import '../ui/theme.dart';
import '../ui/widgets.dart';

/// Navigation du design : Accueil, Recherche, Favoris, Messages, Compte (« Mon espace », qui porte aussi les annonces à publier).
/// L'accueil et la recherche sont publics ; favoris et messages demandent une connexion.
class AppShell extends StatefulWidget {
  const AppShell({super.key, required this.auth});

  final AuthController auth;

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  static const _search = 1;

  int _tab = 0;
  SearchFilters _filters = const SearchFilters();
  int _searchSeed = 0;
  late final ListingsRepository _listings = ListingsRepository(widget.auth.api);
  late final InquiriesRepository _inquiries = InquiriesRepository(
    widget.auth.api,
  );

  @override
  void initState() {
    super.initState();
    widget.auth.addListener(_announceExpiry);
  }

  @override
  void dispose() {
    widget.auth.removeListener(_announceExpiry);
    super.dispose();
  }

  /// Une session refusée par le serveur déconnecte l'application : on le dit une fois, au lieu d'un changement silencieux.
  void _announceExpiry() {
    if (!widget.auth.sessionExpired) return;
    widget.auth.sessionExpired = false;
    if (!mounted) return;
    setState(() => _tab = 4);
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Votre session a expiré : reconnectez-vous.'),
      ),
    );
  }

  void _goSearch(SearchFilters f) => setState(() {
    _filters = f;
    _searchSeed++;
    _tab = _search;
  });

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: widget.auth,
    builder: (context, _) {
      final signedIn = widget.auth.status == AuthStatus.signedIn;
      // La clé suit l'identité : à la déconnexion, aucun écran ne garde de données d'un autre compte.
      final who = '${widget.auth.user?.id}';
      final pages = <Widget>[
        HomeScreen(
          key: ValueKey('home-$who'),
          repository: _listings,
          signedIn: signedIn,
          inquiries: signedIn ? _inquiries : null,
          onSearch: _goSearch,
          userName: widget.auth.user?.name,
          onNotifications: signedIn
              ? () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => NotificationsScreen(repository: _inquiries),
                  ),
                )
              : () => setState(() => _tab = 4),
        ),
        SearchScreen(
          key: ValueKey('search-$who-$_searchSeed'),
          repository: _listings,
          signedIn: signedIn,
          inquiries: signedIn ? _inquiries : null,
          initialFilters: _filters,
        ),
        signedIn
            ? FavoritesScreen(
                key: ValueKey('fav-$who'),
                repository: _listings,
                inquiries: _inquiries,
              )
            : const _SignInPrompt(what: 'retrouver vos favoris'),
        signedIn
            ? MessagesScreen(key: ValueKey('msg-$who'), repository: _inquiries)
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
            NavigationDestination(
              icon: Icon(Icons.home_outlined),
              selectedIcon: Icon(Icons.home),
              label: 'Accueil',
            ),
            NavigationDestination(icon: Icon(Icons.search), label: 'Recherche'),
            NavigationDestination(
              icon: Icon(Icons.favorite_border),
              selectedIcon: Icon(Icons.favorite),
              label: 'Favoris',
            ),
            NavigationDestination(
              icon: Icon(Icons.chat_bubble_outline),
              selectedIcon: Icon(Icons.chat_bubble),
              label: 'Messages',
            ),
            NavigationDestination(
              icon: Icon(Icons.person_outline),
              selectedIcon: Icon(Icons.person),
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
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const CircleAvatar(
              radius: 28,
              backgroundColor: AppColors.primarySoft,
              child: Icon(Icons.lock_outline, color: AppColors.primary),
            ),
            const SizedBox(height: 16),
            Text(
              'Connectez-vous (onglet Compte) pour $what.',
              textAlign: TextAlign.center,
            ),
          ],
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

  Future<void> _confirmDelete(BuildContext context) async {
    final password = TextEditingController();
    final messenger = ScaffoldMessenger.of(context);
    var error = '';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setState) => AlertDialog(
          title: const Text('Supprimer mon compte ?'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Cette action est définitive : vos données personnelles seront anonymisées et vos annonces retirées. Saisissez votre mot de passe pour confirmer.',
              ),
              const SizedBox(height: 12),
              TextField(
                controller: password,
                obscureText: true,
                decoration: const InputDecoration(labelText: 'Mot de passe'),
              ),
              if (error.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(
                    error,
                    style: const TextStyle(color: AppColors.danger),
                  ),
                ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Annuler'),
            ),
            FilledButton(
              style: FilledButton.styleFrom(backgroundColor: AppColors.danger),
              onPressed: () async {
                try {
                  await auth.deleteAccount(password.text);
                  if (ctx.mounted) Navigator.pop(ctx, true);
                } catch (e) {
                  setState(() => error = errorText(e));
                }
              },
              child: const Text('Supprimer définitivement'),
            ),
          ],
        ),
      ),
    );
    password.dispose();
    if (confirmed == true) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Votre compte a été supprimé.')),
      );
    }
  }

  void _push(BuildContext context, Widget screen) =>
      Navigator.of(context)
          .push(MaterialPageRoute<void>(builder: (_) => screen));

  MyListingsRepository get _mine => MyListingsRepository(auth.api);

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Mon espace')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Row(
          children: [
            CircleAvatar(
              radius: 26,
              backgroundColor: AppColors.sandDeep,
              foregroundColor: AppColors.primary,
              child: Text(
                (auth.user?.name ?? '?').characters.first.toUpperCase(),
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 20,
                ),
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    auth.user?.name ?? '',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  Text(
                    auth.user?.phone ?? '',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        _Stats(mine: _mine, inquiries: inquiries),
        const SizedBox(height: 16),
        FilledButton.icon(
          onPressed: () => _push(
            context,
            MyListingsScreen(
              repository: _mine,
              editor: EditorRepository(auth.api),
            ),
          ),
          icon: const Icon(Icons.add_home_work_outlined),
          label: const Text('Publier ou gérer mes annonces'),
        ),
        const SizedBox(height: 16),
        Card(
          child: Column(
            children: [
              ListTile(
                leading: const Icon(Icons.home_work_outlined),
                title: const Text('Mes annonces'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => _push(
                  context,
                  MyListingsScreen(
                    repository: _mine,
                    editor: EditorRepository(auth.api),
                  ),
                ),
              ),
              const Divider(),
              ListTile(
                leading: const Icon(Icons.favorite_border),
                title: const Text('Mes favoris'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => _push(
                  context,
                  FavoritesScreen(repository: listings, inquiries: inquiries),
                ),
              ),
              const Divider(),
              ListTile(
                leading: const Icon(Icons.notifications_none),
                title: const Text('Notifications'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () =>
                    _push(context, NotificationsScreen(repository: inquiries)),
              ),
              const Divider(),
              ListTile(
                leading: const Icon(Icons.bookmark_border),
                title: const Text('Mes alertes de recherche'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => _push(context, AlertsScreen(repository: listings)),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        OutlinedButton(
          onPressed: auth.logout,
          child: const Text('Se déconnecter'),
        ),
        const SizedBox(height: 4),
        TextButton(
          onPressed: () => _confirmDelete(context),
          style: TextButton.styleFrom(foregroundColor: AppColors.danger),
          child: const Text('Supprimer mon compte'),
        ),
      ],
    ),
  );
}

/// Trois compteurs du compte : annonces, messages reçus et envoyés. Un compteur indisponible reste vide plutôt que faux.
class _Stats extends StatefulWidget {
  const _Stats({required this.mine, required this.inquiries});

  final MyListingsRepository mine;
  final InquiriesRepository inquiries;

  @override
  State<_Stats> createState() => _StatsState();
}

class _StatsState extends State<_Stats> {
  int? _listings;
  int? _received;
  int? _sent;

  @override
  void initState() {
    super.initState();
    widget.mine.list().then((v) {
      if (mounted) setState(() => _listings = v.length);
    }).catchError((_) {});
    widget.inquiries.inbox().then((v) {
      if (mounted) setState(() => _received = v.length);
    }).catchError((_) {});
    widget.inquiries.sent().then((v) {
      if (mounted) setState(() => _sent = v.length);
    }).catchError((_) {});
  }

  @override
  Widget build(BuildContext context) => Row(
    children: [
      _tile(Icons.home_work_outlined, _listings, 'Annonces'),
      const SizedBox(width: 8),
      _tile(Icons.mark_email_unread_outlined, _received, 'Reçus'),
      const SizedBox(width: 8),
      _tile(Icons.send_outlined, _sent, 'Envoyés'),
    ],
  );

  Widget _tile(IconData icon, int? value, String label) => Expanded(
    child: Container(
      padding: const EdgeInsets.symmetric(vertical: 14),
      decoration: BoxDecoration(
        color: AppColors.primarySoft,
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Column(
        children: [
          Icon(icon, color: AppColors.primary),
          const SizedBox(height: 6),
          Text(
            value?.toString() ?? '–',
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 20),
          ),
          Text(label, style: const TextStyle(fontSize: 12)),
        ],
      ),
    ),
  );
}
