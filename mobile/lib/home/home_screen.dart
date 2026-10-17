import 'package:flutter/material.dart';

import '../core/format.dart';
import '../inquiries/inquiries_repository.dart';
import '../listings/listing_detail_screen.dart';
import '../listings/listing_widgets.dart';
import '../listings/listings_repository.dart';
import '../listings/models.dart';
import '../ui/theme.dart';

/// Accueil du design : recherche rapide, annonces « À la une », quartiers, pourquoi ChezVous.
class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    required this.repository,
    required this.signedIn,
    required this.onSearch,
    this.inquiries,
  });

  final ListingsRepository repository;
  final bool signedIn;
  final InquiriesRepository? inquiries;
  final ValueChanged<SearchFilters> onSearch;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  SearchFilters _filters = const SearchFilters();
  List<Locality> _localities = const [];
  List<ListingSummary> _featured = const [];

  @override
  void initState() {
    super.initState();
    widget.repository
        .neighborhoods()
        .then((v) {
          if (mounted) setState(() => _localities = v);
        })
        .catchError((_) {
          // Confort : l'accueil reste utilisable sans les quartiers.
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

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      titleSpacing: 16,
      title: Row(
        children: [
          Image.asset(
            'assets/brand/mark.png',
            height: 32,
            semanticLabel: 'ChezVous',
          ),
          const SizedBox(width: 8),
          const Text.rich(
            TextSpan(
              text: 'Chez',
              children: [
                TextSpan(
                  text: 'Vous',
                  style: TextStyle(color: AppColors.brandRed),
                ),
              ],
            ),
            style: TextStyle(fontWeight: FontWeight.w800, letterSpacing: -.5),
          ),
        ],
      ),
    ),
    body: ListView(
      children: [
        _hero(context),
        if (_featured.isNotEmpty) _featuredSection(context),
        if (_localities.isNotEmpty) _districts(context),
        const _Trust(),
      ],
    ),
  );

  Widget _hero(BuildContext context) => Container(
    color: AppColors.sand,
    padding: const EdgeInsets.fromLTRB(16, 28, 16, 28),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'LOCATION RÉSIDENTIELLE À DAKAR',
          style: TextStyle(
            color: AppColors.primary,
            fontWeight: FontWeight.w800,
            fontSize: 11,
            letterSpacing: 1.6,
          ),
        ),
        const SizedBox(height: 10),
        const Text.rich(
          TextSpan(
            text: 'Trouvez un logement ',
            children: [
              TextSpan(
                text: 'réellement disponible',
                style: TextStyle(color: AppColors.primary),
              ),
            ],
          ),
          style: TextStyle(
            fontSize: 30,
            fontWeight: FontWeight.w800,
            height: 1.1,
            letterSpacing: -1,
          ),
        ),
        const SizedBox(height: 12),
        const Text(
          'Des annonces confirmées par leurs annonceurs, avec un Passeport du Bien et une date de dernière confirmation.',
          style: TextStyle(color: AppColors.muted, height: 1.6),
        ),
        const SizedBox(height: 20),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(AppRadius.lg),
            border: Border.all(color: const Color(0x14C2410C)),
            boxShadow: const [
              BoxShadow(
                color: Color(0x1C182C25),
                blurRadius: 30,
                offset: Offset(0, 12),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SegmentedButton<String>(
                showSelectedIcon: false,
                segments: const [
                  ButtonSegment(value: 'rent_long', label: Text('Location')),
                  ButtonSegment(value: 'sale', label: Text('Vente')),
                ],
                selected: {_filters.transaction},
                onSelectionChanged: (v) => setState(
                  () => _filters = _filters.copyWith(transaction: v.first),
                ),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String?>(
                isExpanded: true,
                initialValue: _filters.locality,
                decoration: const InputDecoration(
                  labelText: 'Quartier',
                  prefixIcon: Icon(Icons.place_outlined),
                ),
                items: [
                  const DropdownMenuItem<String?>(
                    value: null,
                    child: Text('Tous les quartiers'),
                  ),
                  for (final l in _localities)
                    DropdownMenuItem<String?>(
                      value: l.slug,
                      child: Text(l.name),
                    ),
                ],
                onChanged: (v) => setState(
                  () => _filters = _filters.copyWith(locality: () => v),
                ),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<int?>(
                isExpanded: true,
                initialValue: _filters.priceMax,
                decoration: const InputDecoration(
                  labelText: 'Budget maximum',
                  prefixIcon: Icon(Icons.payments_outlined),
                ),
                items: [
                  const DropdownMenuItem<int?>(
                    value: null,
                    child: Text('Tous les budgets'),
                  ),
                  for (final p in const [
                    100000,
                    200000,
                    300000,
                    500000,
                    1000000,
                  ])
                    DropdownMenuItem<int?>(
                      value: p,
                      child: Text('≤ ${groupThousands(p)} FCFA'),
                    ),
                ],
                onChanged: (v) => setState(
                  () => _filters = _filters.copyWith(priceMax: () => v),
                ),
              ),
              const SizedBox(height: 14),
              FilledButton.icon(
                icon: const Icon(Icons.search),
                label: const Text('Rechercher'),
                onPressed: () => widget.onSearch(_filters),
              ),
            ],
          ),
        ),
      ],
    ),
  );

  Widget _featuredSection(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 28),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Text(
            'À la une',
            style: Theme.of(context).textTheme.titleLarge,
          ),
        ),
        const SizedBox(height: 12),
        SizedBox(
          height: 312,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            itemCount: _featured.length,
            separatorBuilder: (_, _) => const SizedBox(width: 14),
            itemBuilder: (_, i) => _FeaturedCard(
              listing: _featured[i],
              onTap: () => _open(_featured[i]),
            ),
          ),
        ),
      ],
    ),
  );

  Widget _districts(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 28),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Text(
            'Quartiers de Dakar',
            style: Theme.of(context).textTheme.titleLarge,
          ),
        ),
        const SizedBox(height: 12),
        SizedBox(
          height: 44,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            itemCount: _localities.length,
            separatorBuilder: (_, _) => const SizedBox(width: 8),
            itemBuilder: (_, i) => ActionChip(
              avatar: const Icon(
                Icons.place_outlined,
                size: 16,
                color: AppColors.primary,
              ),
              label: Text(_localities[i].name),
              onPressed: () => widget.onSearch(
                _filters.copyWith(locality: () => _localities[i].slug),
              ),
            ),
          ),
        ),
      ],
    ),
  );
}

class _FeaturedCard extends StatelessWidget {
  const _FeaturedCard({required this.listing, required this.onTap});

  final ListingSummary listing;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = listing;
    return SizedBox(
      width: 260,
      child: Card(
        clipBehavior: Clip.antiAlias,
        margin: EdgeInsets.zero,
        child: InkWell(
          onTap: onTap,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                height: 130,
                width: double.infinity,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    CoverImage(url: l.coverUrl),
                    // Mise en avant payante : toujours étiquetée comme telle.
                    Positioned(
                      left: 10,
                      bottom: 10,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 9,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xDB1F1B16),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: const Text(
                          'Mise en avant par son annonceur',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (l.locality != null)
                      Text(
                        l.locality!,
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    const SizedBox(height: 3),
                    Text(
                      l.title ?? l.reference,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium
                          ?.copyWith(fontSize: 15),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      formatPrice(l.priceXof, l.transaction),
                      style: const TextStyle(
                        color: AppColors.primary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 8),
                    FreshnessBadge(availability: l.availability),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Trust extends StatelessWidget {
  const _Trust();

  @override
  Widget build(BuildContext context) {
    const items = [
      (
        Icons.schedule,
        'Une disponibilité datée',
        "Chaque annonce indique quand son annonceur l'a confirmée pour la dernière fois.",
      ),
      (
        Icons.verified_user_outlined,
        'Le Passeport du Bien',
        'Des contrôles explicites et datés, jamais une note globale. Un contrôle ne garantit pas la propriété juridique.',
      ),
      (
        Icons.chat_bubble_outline,
        'Contact par la plateforme',
        "Votre numéro et celui de l'annonceur restent privés tant que vous ne les partagez pas.",
      ),
    ];
    return Container(
      margin: const EdgeInsets.only(top: 32),
      color: AppColors.primaryHover,
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'POURQUOI CHEZVOUS',
            style: TextStyle(
              color: Color(0xFFFFD9BD),
              fontWeight: FontWeight.w800,
              fontSize: 11,
              letterSpacing: 1.6,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            "Mieux vaut peu d'annonces, mais fiables.",
            style: TextStyle(
              color: Colors.white,
              fontSize: 22,
              fontWeight: FontWeight.w800,
              height: 1.2,
            ),
          ),
          const SizedBox(height: 16),
          for (final (icon, title, body) in items)
            Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: const Color(0x1A000000),
                borderRadius: BorderRadius.circular(AppRadius.md),
                border: Border.all(color: const Color(0x33FFFFFF)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CircleAvatar(
                    backgroundColor: AppColors.primarySoft,
                    foregroundColor: AppColors.primary,
                    child: Icon(icon),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    title,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    body,
                    style: const TextStyle(
                      color: Color(0xFFFFE9DA),
                      height: 1.6,
                      fontSize: 13,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
