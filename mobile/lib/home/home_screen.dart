import 'package:flutter/material.dart';

import '../inquiries/inquiries_repository.dart';
import '../listings/filters_screen.dart';
import '../listings/listing_detail_screen.dart';
import '../listings/listing_widgets.dart';
import '../listings/listings_repository.dart';
import '../listings/models.dart';
import '../ui/theme.dart';
import '../ui/widgets.dart';

/// Accueil : salutation, recherche rapide (transaction, mot-clé, filtres), quartiers populaires et annonces mises en avant.
class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    required this.repository,
    required this.signedIn,
    required this.onSearch,
    this.onStays,
    this.inquiries,
    this.userName,
    this.onNotifications,
  });

  final ListingsRepository repository;
  final bool signedIn;
  final InquiriesRepository? inquiries;
  final ValueChanged<SearchFilters> onSearch;

  /// Ouvre la recherche de séjours meublés.
  final VoidCallback? onStays;
  final String? userName;
  final VoidCallback? onNotifications;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  SearchFilters _filters = const SearchFilters();
  final _query = TextEditingController();
  List<Locality> _localities = const [];
  List<ListingSummary> _featured = const [];
  List<PropertyKind> _types = const [];

  @override
  void initState() {
    super.initState();
    widget.repository
        .neighborhoods(withCounts: true)
        .then((v) {
          if (mounted) {
            // Les quartiers les plus fournis d'abord.
            setState(
              () =>
                  _localities = [...v]
                    ..sort((a, b) => (b.count ?? 0).compareTo(a.count ?? 0)),
            );
          }
        })
        .catchError((_) {
          // Confort : l'accueil reste utilisable sans les quartiers.
        });
    widget.repository
        .propertyTypes()
        .then((v) {
          if (mounted) setState(() => _types = v.take(6).toList());
        })
        .catchError((_) {
          // Les puces de type sont un raccourci : sans elles, les filtres restent accessibles.
        });
    widget.repository
        .featured()
        .then((v) {
          if (mounted) setState(() => _featured = v);
        })
        .catchError((_) {
          // Aucune annonce mise en avant est un cas normal.
        });
  }

  @override
  void dispose() {
    _query.dispose();
    super.dispose();
  }

  void _open(ListingSummary l) => Navigator.of(context).push(
    MaterialPageRoute<void>(
      builder: (_) => ListingDetailScreen(
        repository: widget.repository,
        reference: l.reference,
        signedIn: widget.signedIn,
        inquiries: widget.inquiries,
      ),
    ),
  );

  SearchFilters get _withQuery {
    final q = _query.text.trim();
    return _filters.copyWith(q: () => q.isEmpty ? null : q);
  }

  Future<void> _openFilters() async {
    final result = await Navigator.of(context).push<SearchFilters>(
      MaterialPageRoute(
        builder: (_) =>
            FiltersScreen(repository: widget.repository, initial: _withQuery),
      ),
    );
    if (result != null) widget.onSearch(result);
  }

  static const _typeIcons = {
    'apartment': Icons.apartment_outlined,
    'house': Icons.home_outlined,
    'studio': Icons.single_bed_outlined,
    'room': Icons.bed_outlined,
    'land': Icons.landscape_outlined,
    'office': Icons.business_center_outlined,
    'shop': Icons.storefront_outlined,
    'warehouse': Icons.warehouse_outlined,
    'building': Icons.domain_outlined,
  };

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final name = widget.userName?.trim().split(' ').first;
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
          children: [
            Row(
              children: [
                const BrandLogo(size: 15),
                const Spacer(),
                const Chip(
                  avatar: Icon(Icons.location_on_outlined, size: 18),
                  label: Text('Dakar'),
                ),
                IconButton(
                  tooltip: 'Notifications',
                  icon: const Icon(Icons.notifications_none),
                  onPressed: widget.onNotifications,
                ),
              ],
            ),
            const SizedBox(height: 8),
            ClipRRect(
              borderRadius: BorderRadius.circular(AppRadius.lg),
              child: SizedBox(
                height: 230,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    Image.asset(
                      'assets/images/dakar-crepuscule.jpg',
                      fit: BoxFit.cover,
                      alignment: const Alignment(0.6, 0),
                      excludeFromSemantics: true,
                    ),
                    const DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.centerLeft,
                          end: Alignment.centerRight,
                          colors: [Color(0xCC000000), Color(0x33000000)],
                        ),
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisAlignment: MainAxisAlignment.end,
                        children: [
                          Text(
                            name == null || name.isEmpty
                                ? 'Bonjour 👋'
                                : 'Bonjour $name 👋',
                            style: const TextStyle(color: Colors.white70),
                          ),
                          const SizedBox(height: 6),
                          const AccentHeadline(
                            'Trouvez le bien ',
                            'qui vous ressemble',
                            size: 30,
                            light: true,
                          ),
                          const SizedBox(height: 8),
                          const Text(
                            'Appartements, maisons, studios, bureaux et terrains à Dakar.',
                            style: TextStyle(color: Colors.white, fontSize: 13),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            _TransactionTabs(
              value: _filters.transaction,
              onChanged: (v) =>
                  setState(() => _filters = _filters.copyWith(transaction: v)),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _query,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => widget.onSearch(_withQuery),
              decoration: InputDecoration(
                hintText: 'Quartier, ville, résidence…',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: Padding(
                  padding: const EdgeInsets.all(6),
                  child: IconButton.filled(
                    style: IconButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: Colors.white,
                    ),
                    tooltip: 'Rechercher',
                    icon: const Icon(Icons.arrow_forward),
                    onPressed: () => widget.onSearch(_withQuery),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              height: 48,
              child: ListView(
                scrollDirection: Axis.horizontal,
                children: [
                  for (final t in _types)
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ActionChip(
                        avatar: Icon(
                          _typeIcons[t.code] ?? Icons.home_outlined,
                          size: 18,
                          color: AppColors.primary,
                        ),
                        label: Text(t.label),
                        onPressed: () => widget.onSearch(
                          _withQuery.copyWith(type: () => t.code),
                        ),
                      ),
                    ),
                  ActionChip(
                    avatar: const Icon(
                      Icons.tune,
                      size: 18,
                      color: AppColors.primary,
                    ),
                    label: const Text('Filtres'),
                    onPressed: _openFilters,
                  ),
                ],
              ),
            ),
            if (widget.onStays != null) ...[
              const SizedBox(height: 16),
              InkWell(
                onTap: widget.onStays,
                borderRadius: BorderRadius.circular(AppRadius.md),
                child: Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppColors.primarySoft,
                    borderRadius: BorderRadius.circular(AppRadius.md),
                    border: Border.all(color: AppColors.sandDeep),
                  ),
                  child: const Row(
                    children: [
                      Icon(
                        Icons.luggage_outlined,
                        color: AppColors.primary,
                        size: 28,
                      ),
                      SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Séjours meublés',
                              style: TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 16,
                              ),
                            ),
                            Text(
                              'Prix total avant toute demande, dates contrôlées.',
                              style: TextStyle(fontSize: 12),
                            ),
                          ],
                        ),
                      ),
                      Icon(Icons.chevron_right),
                    ],
                  ),
                ),
              ),
            ],
            if (_localities.isNotEmpty) ...[
              const SizedBox(height: 20),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Quartiers populaires', style: text.titleLarge),
                  TextButton(
                    onPressed: () => widget.onSearch(_withQuery),
                    child: const Text('Voir tout'),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              SizedBox(
                height: 132,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: _localities.length.clamp(0, 8),
                  separatorBuilder: (_, _) => const SizedBox(width: 12),
                  itemBuilder: (_, i) => _DistrictCard(
                    locality: _localities[i],
                    index: i,
                    onTap: () => widget.onSearch(
                      _filters.copyWith(locality: () => _localities[i].slug),
                    ),
                  ),
                ),
              ),
            ],
            if (_featured.isNotEmpty) ...[
              const SizedBox(height: 24),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Biens à la une', style: text.titleLarge),
                  TextButton(
                    onPressed: () => widget.onSearch(_withQuery),
                    child: const Text('Voir tout'),
                  ),
                ],
              ),
              Text('Mis en avant par leurs annonceurs', style: text.bodySmall),
              const SizedBox(height: 12),
              // Deux colonnes dont la hauteur suit le contenu (polices agrandies comprises), jamais un rapport fixe.
              for (var i = 0; i < _featured.length; i += 2)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: IntrinsicHeight(
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        for (var j = i; j < i + 2; j++) ...[
                          if (j > i) const SizedBox(width: 12),
                          Expanded(
                            child: j < _featured.length
                                ? ListingTile(
                                    listing: _featured[j],
                                    repository: widget.repository,
                                    signedIn: widget.signedIn,
                                    onNeedSignIn: widget.onNotifications,
                                    onTap: () => _open(_featured[j]),
                                  )
                                : const SizedBox.shrink(),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
            ],
            const SizedBox(height: 20),
            const _TrustNote(),
          ],
        ),
      ),
    );
  }
}

/// Onglets Location / Vente de l'accueil : l'onglet actif est plein (orange profond, texte blanc contrasté).
class _TransactionTabs extends StatelessWidget {
  const _TransactionTabs({required this.value, required this.onChanged});

  final String value;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      for (final (code, label) in const [
        ('rent_long', 'Location'),
        ('sale', 'Vente'),
      ])
        Padding(
          padding: const EdgeInsets.only(right: 8),
          child: ChoiceChip(
            showCheckmark: false,
            label: Text(label),
            selected: value == code,
            selectedColor: AppColors.primary,
            labelStyle: TextStyle(
              fontWeight: FontWeight.w700,
              color: value == code ? Colors.white : AppColors.ink,
            ),
            onSelected: (_) => onChanged(code),
          ),
        ),
    ],
  );
}

/// Carte de quartier : dégradé de la marque (pas de photo de stock), nom et nombre de biens.
class _DistrictCard extends StatelessWidget {
  const _DistrictCard({
    required this.locality,
    required this.index,
    required this.onTap,
  });

  final Locality locality;
  final int index;
  final VoidCallback onTap;

  static const _gradients = [
    [Color(0xFFC93F00), Color(0xFF8A2D00)],
    [Color(0xFFFFB020), Color(0xFFFF6A00)],
    [Color(0xFFB03500), Color(0xFF8A2D00)],
  ];

  @override
  Widget build(BuildContext context) {
    final colors = _gradients[index % _gradients.length];
    final n = locality.count;
    return Semantics(
      button: true,
      label: '${locality.name}${n != null ? ', $n biens' : ''}',
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: Container(
          width: 150,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.md),
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: colors,
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              const Icon(
                Icons.location_on_outlined,
                color: Colors.white,
                size: 22,
              ),
              const Spacer(),
              // Texte sombre sur les teintes claires, blanc sur les teintes profondes : contraste suffisant dans les deux cas.
              Text(
                locality.name,
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 17,
                  color: index % _gradients.length == 1
                      ? AppColors.ink
                      : Colors.white,
                ),
              ),
              if (n != null)
                Text(
                  '$n bien${n > 1 ? 's' : ''}',
                  style: TextStyle(
                    fontSize: 12,
                    color: index % _gradients.length == 1
                        ? AppColors.ink
                        : Colors.white,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TrustNote extends StatelessWidget {
  const _TrustNote();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: AppColors.primarySoft,
      borderRadius: BorderRadius.circular(AppRadius.md),
    ),
    child: const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(Icons.verified_user_outlined, color: AppColors.primary),
        SizedBox(width: 12),
        Expanded(
          child: Text(
            'Chaque annonce indique quand son annonceur l\'a confirmée, et son Passeport du Bien liste les contrôles effectués. Un contrôle ne garantit pas la propriété juridique du bien.',
            style: TextStyle(height: 1.5, fontSize: 13),
          ),
        ),
      ],
    ),
  );
}
