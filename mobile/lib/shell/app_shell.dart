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
import '../stays/host_requests_screen.dart';
import '../stays/my_stays_screen.dart';
import '../stays/stay_search_screen.dart';
import '../stays/stays_repository.dart';
import '../ui/theme.dart';
import '../ui/widgets.dart';

/// Navigation du design : Accueil, Recherche, Favoris, Messages, Compte (« Mon espace », qui porte aussi les annonces à publier).
/// L'accueil et la recherche sont publics ; favoris et messages demandent une connexion.
class AppShell extends StatefulWidget {
  const AppShell({super.key, required this.auth, this.initialTab = 0});

  final AuthController auth;
  final int initialTab;

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  static const _search = 1;

  late int _tab = widget.initialTab;
  SearchFilters _filters = const SearchFilters();
  int _searchSeed = 0;
  late final ListingsRepository _listings = ListingsRepository(widget.auth.api);
  late final StaysRepository _stays = StaysRepository(widget.auth.api);
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

  void _openStays(bool signedIn) => Navigator.of(context).push(
    MaterialPageRoute<void>(
      builder: (_) => StaySearchScreen(
        repository: _stays,
        signedIn: signedIn,
        onNeedSignIn: () => setState(() => _tab = 4),
        onOpenBooking: (id) => Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => MyStaysScreen(repository: _stays, openId: id),
          ),
        ),
      ),
    ),
  );

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
          onStays: () => _openStays(signedIn),
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
                stays: _stays,
              )
            : LoginScreen(auth: widget.auth),
      ];
      return Scaffold(
        body: IndexedStack(index: _tab, children: pages),
        bottomNavigationBar: SafeArea(
          child: Container(
            margin: const EdgeInsets.fromLTRB(12, 0, 12, 8),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(AppRadius.lg),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.08),
                  blurRadius: 24,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(AppRadius.lg),
              child: NavigationBar(
                selectedIndex: _tab,
                onDestinationSelected: (i) => setState(() => _tab = i),
                destinations: const [
                  NavigationDestination(
                    icon: Icon(Icons.home_outlined),
                    selectedIcon: Icon(Icons.home),
                    label: 'Accueil',
                  ),
                  NavigationDestination(
                    icon: Icon(Icons.search),
                    label: 'Recherche',
                  ),
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
            ),
          ),
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
    required this.stays,
  });

  final AuthController auth;
  final ListingsRepository listings;
  final InquiriesRepository inquiries;
  final StaysRepository stays;

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

  Widget _menu(
    IconData icon,
    String title,
    String subtitle,
    VoidCallback onTap,
  ) => InkWell(
    onTap: onTap,
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 14),
      child: Row(
        children: [
          Icon(icon, color: AppColors.primary, size: 26),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                  ),
                ),
                Text(
                  subtitle,
                  style: const TextStyle(color: AppColors.muted, fontSize: 13),
                ),
              ],
            ),
          ),
          const Icon(Icons.chevron_right, color: AppColors.muted),
        ],
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final name = auth.user?.name ?? '';
    final initials = name
        .trim()
        .split(RegExp(r'\s+'))
        .take(2)
        .where((w) => w.isNotEmpty)
        .map((w) => w.characters.first.toUpperCase())
        .join();
    return Scaffold(
      body: ListView(
        padding: EdgeInsets.zero,
        children: [
          Container(
            color: const Color(0xFF141F2E),
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 56),
            child: SafeArea(
              bottom: false,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 12),
                  const BrandLogo(size: 15, light: true),
                  const SizedBox(height: 22),
                  Row(
                    children: [
                      CircleAvatar(
                        radius: 40,
                        backgroundColor: const Color(0xFFF8D9C8),
                        child: Text(
                          initials.isEmpty ? '?' : initials,
                          style: const TextStyle(
                            fontWeight: FontWeight.w900,
                            fontSize: 28,
                            color: AppColors.primary,
                          ),
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Eyebrow('Mon espace', light: true),
                            const SizedBox(height: 4),
                            Text(
                              name,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                                fontSize: 26,
                                height: 1.1,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              auth.user?.phone ?? '',
                              style: const TextStyle(color: Colors.white70),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          Transform.translate(
            offset: const Offset(0, -36),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: _Stats(mine: _mine, listings: listings),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Votre espace',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 4),
                _menu(
                  Icons.favorite_border,
                  'Mes favoris',
                  'Vos biens enregistrés',
                  () => _push(
                    context,
                    FavoritesScreen(repository: listings, inquiries: inquiries),
                  ),
                ),
                const Divider(),
                _menu(
                  Icons.notifications_none,
                  'Mes notifications',
                  'Visites et réponses des annonceurs',
                  () => _push(
                    context,
                    NotificationsScreen(repository: inquiries),
                  ),
                ),
                const Divider(),
                _menu(
                  Icons.bookmark_border,
                  'Mes alertes',
                  'Disponibilités et nouveautés',
                  () => _push(context, AlertsScreen(repository: listings)),
                ),
                const Divider(),
                _menu(
                  Icons.luggage_outlined,
                  'Mes séjours',
                  'Demandes, règlements et réservations',
                  () => _push(context, MyStaysScreen(repository: stays)),
                ),
                const Divider(),
                _menu(
                  Icons.key_outlined,
                  'Demandes de séjour',
                  'Espace hôte : accepter ou refuser',
                  () => _push(context, HostRequestsScreen(repository: stays)),
                ),
                const Divider(),
                _menu(
                  Icons.apartment_outlined,
                  'Mes annonces',
                  'Publication et suivi',
                  () => _push(
                    context,
                    MyListingsScreen(
                      repository: _mine,
                      editor: EditorRepository(auth.api),
                    ),
                  ),
                ),
                const SizedBox(height: 20),
                PrimaryButton(
                  label: 'Publier une annonce',
                  arrow: true,
                  onPressed: () => _push(
                    context,
                    MyListingsScreen(
                      repository: _mine,
                      editor: EditorRepository(auth.api),
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                OutlinedButton(
                  onPressed: auth.logout,
                  child: const Text('Se déconnecter'),
                ),
                TextButton(
                  onPressed: () => _confirmDelete(context),
                  style: TextButton.styleFrom(
                    foregroundColor: AppColors.danger,
                  ),
                  child: const Text('Supprimer mon compte'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Trois compteurs du compte : favoris, annonces et alertes. Un compteur indisponible reste vide plutôt que faux.
class _Stats extends StatefulWidget {
  const _Stats({required this.mine, required this.listings});

  final MyListingsRepository mine;
  final ListingsRepository listings;

  @override
  State<_Stats> createState() => _StatsState();
}

class _StatsState extends State<_Stats> {
  int? _favorites;
  int? _mineCount;
  int? _alerts;

  @override
  void initState() {
    super.initState();
    widget.listings
        .favorites()
        .then((v) {
          if (mounted) setState(() => _favorites = v.length);
        })
        .catchError((_) {});
    widget.mine
        .list()
        .then((v) {
          if (mounted) setState(() => _mineCount = v.length);
        })
        .catchError((_) {});
    widget.listings
        .savedSearches()
        .then((v) {
          if (mounted) setState(() => _alerts = v.length);
        })
        .catchError((_) {});
  }

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 18),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      boxShadow: [
        BoxShadow(
          color: Colors.black.withValues(alpha: 0.08),
          blurRadius: 20,
          offset: const Offset(0, 6),
        ),
      ],
    ),
    child: Row(
      children: [
        _cell(_favorites, 'Favoris'),
        const SizedBox(height: 36, child: VerticalDivider()),
        _cell(_mineCount, 'Annonces'),
        const SizedBox(height: 36, child: VerticalDivider()),
        _cell(_alerts, 'Alertes'),
      ],
    ),
  );

  Widget _cell(int? value, String label) => Expanded(
    child: Column(
      children: [
        Text(
          value == null ? '–' : value.toString().padLeft(2, '0'),
          style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 26),
        ),
        Text(
          label,
          style: const TextStyle(color: AppColors.muted, fontSize: 13),
        ),
      ],
    ),
  );
}
