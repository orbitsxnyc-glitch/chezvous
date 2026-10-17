import 'package:flutter/material.dart';

import '../core/format.dart';
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
    this.inquiries,
    this.userName,
    this.onNotifications,
  });

  final ListingsRepository repository;
  final bool signedIn;
  final InquiriesRepository? inquiries;
  final ValueChanged<SearchFilters> onSearch;
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

  String? _chipLabel(String empty, String? value) => value ?? empty;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final name = widget.userName?.trim().split(' ').first;
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 16,
        title: const BrandLogo(),
        actions: [
          IconButton(
            tooltip: 'Notifications',
            icon: const Icon(Icons.notifications_none),
            onPressed: widget.onNotifications,
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 24),
        children: [
          Text(
            name == null || name.isEmpty ? 'Bonjour 👋' : 'Bonjour $name 👋',
            style: text.bodyMedium?.copyWith(color: AppColors.muted),
          ),
          const SizedBox(height: 4),
          Text(
            'Où souhaitez-vous vivre ?',
            style: text.headlineSmall?.copyWith(fontSize: 28, height: 1.1),
          ),
          const SizedBox(height: 18),
          _TransactionTabs(
            value: _filters.transaction,
            onChanged: (v) =>
                setState(() => _filters = _filters.copyWith(transaction: v)),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _query,
            textInputAction: TextInputAction.search,
            onSubmitted: (_) => widget.onSearch(_withQuery),
            decoration: InputDecoration(
              hintText: 'Quartier, ville, résidence…',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: IconButton(
                tooltip: 'Rechercher',
                icon: const Icon(Icons.arrow_forward),
                onPressed: () => widget.onSearch(_withQuery),
              ),
            ),
          ),
          const SizedBox(height: 12),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                _FilterChipButton(
                  icon: Icons.payments_outlined,
                  label: _filters.priceMax == null
                      ? 'Budget'
                      : '≤ ${groupThousands(_filters.priceMax!)}',
                  onTap: _openFilters,
                ),
                const SizedBox(width: 8),
                _FilterChipButton(
                  icon: Icons.bed_outlined,
                  label: _filters.bedroomsMin == null
                      ? 'Chambres'
                      : '${_filters.bedroomsMin}+ ch.',
                  onTap: _openFilters,
                ),
                const SizedBox(width: 8),
                _FilterChipButton(
                  icon: Icons.apartment_outlined,
                  label: _chipLabel('Type', _filters.type)!,
                  onTap: _openFilters,
                ),
                const SizedBox(width: 8),
                _FilterChipButton(
                  icon: Icons.tune,
                  label: 'Filtres',
                  onTap: _openFilters,
                ),
              ],
            ),
          ),
          if (_localities.isNotEmpty) ...[
            const SizedBox(height: 28),
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
            const SizedBox(height: 8),
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
            const SizedBox(height: 28),
            Text('À la une', style: text.titleLarge),
            const SizedBox(height: 4),
            Text('Mises en avant par leurs annonceurs', style: text.bodySmall),
            const SizedBox(height: 12),
            for (final l in _featured)
              ListingCard(listing: l, onTap: () => _open(l)),
          ],
          const SizedBox(height: 16),
          const _TrustNote(),
        ],
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

class _FilterChipButton extends StatelessWidget {
  const _FilterChipButton({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => ActionChip(
    avatar: Icon(icon, size: 18, color: AppColors.primary),
    label: Text(label),
    onPressed: onTap,
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
    [Color(0xFFFF6A00), Color(0xFFE74B00)],
    [Color(0xFFFFB020), Color(0xFFFF6A00)],
    [Color(0xFFE74B00), Color(0xFF8A2D00)],
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
