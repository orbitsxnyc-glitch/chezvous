import 'package:flutter/material.dart';

import '../core/format.dart';
import '../inquiries/inquiries_repository.dart';
import '../ui/theme.dart';
import '../ui/widgets.dart';
import 'filters_screen.dart';
import 'listing_detail_screen.dart';
import 'listing_widgets.dart';
import 'listings_repository.dart';
import 'map_view.dart';
import 'models.dart';

class SearchScreen extends StatefulWidget {
  const SearchScreen({
    super.key,
    required this.repository,
    required this.signedIn,
    this.inquiries,
    this.initialFilters = const SearchFilters(),
  });

  final ListingsRepository repository;
  final bool signedIn;
  final InquiriesRepository? inquiries;

  /// Critères de départ (venant de l'accueil) ; l'écran est reconstruit quand ils changent.
  final SearchFilters initialFilters;

  @override
  State<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends State<SearchScreen> {
  late SearchFilters _filters = widget.initialFilters;
  final List<ListingSummary> _items = [];
  List<Locality> _localities = const [];
  String? _cursor;
  bool _loading = true;
  String? _error;
  int _generation = 0;
  bool _showMap = false;
  int? _total;

  @override
  void initState() {
    super.initState();
    _load();
    widget.repository
        .neighborhoods()
        .then((l) {
          if (mounted) setState(() => _localities = l);
        })
        .catchError((_) {
          // Les quartiers sont un confort : la recherche fonctionne sans eux.
        });
  }

  Future<void> _load({bool more = false}) async {
    // Une réponse tardive d'un filtre précédent ne doit jamais écraser le résultat courant.
    final generation = more ? _generation : ++_generation;
    setState(() {
      _loading = true;
      _error = null;
      if (!more) {
        _items.clear();
        _cursor = null;
      }
    });
    try {
      final page = await widget.repository.search(
        _filters,
        cursor: more ? _cursor : null,
      );
      if (!mounted || generation != _generation) return;
      setState(() {
        _items.addAll(page.items);
        _cursor = page.nextCursor;
        if (!more) _total = page.total;
      });
    } catch (e) {
      if (mounted && generation == _generation) {
        setState(() => _error = errorText(e));
      }
    }
    if (mounted && generation == _generation) setState(() => _loading = false);
  }

  void _apply(SearchFilters f) {
    _filters = f;
    _load();
  }

  Future<void> _openFilters() async {
    final result = await Navigator.of(context).push<SearchFilters>(
      MaterialPageRoute(
        builder: (_) =>
            FiltersScreen(repository: widget.repository, initial: _filters),
      ),
    );
    if (result != null) _apply(result);
  }

  static const _sorts = [
    ('relevance', 'Pertinence'),
    ('newest', 'Plus récentes'),
    ('price_asc', 'Prix croissant'),
    ('price_desc', 'Prix décroissant'),
  ];

  Future<void> _openSort() async {
    final chosen = await showModalBottomSheet<String>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'Trier par',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17),
              ),
            ),
            for (final (code, label) in _sorts)
              ListTile(
                title: Text(label),
                trailing: (_filters.sort ?? 'relevance') == code
                    ? const Icon(Icons.check, color: AppColors.primary)
                    : null,
                onTap: () => Navigator.pop(ctx, code),
              ),
          ],
        ),
      ),
    );
    if (chosen != null) {
      _apply(
        _filters.copyWith(sort: () => chosen == 'relevance' ? null : chosen),
      );
    }
  }

  Future<void> _saveSearch() async {
    if (!widget.signedIn) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Connectez-vous (onglet Compte) pour créer une alerte.',
          ),
        ),
      );
      return;
    }
    final name = TextEditingController(
      text: _filters.locality == null
          ? 'Ma recherche'
          : 'Recherche ${_filters.locality}',
    );
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Créer une alerte'),
        content: TextField(
          controller: name,
          decoration: const InputDecoration(labelText: "Nom de l'alerte"),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Créer'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    try {
      await widget.repository.saveSearch(name.text.trim(), _filters);
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Alerte créée.')));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(errorText(e))));
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Recherche'),
      actions: [
        IconButton(
          tooltip: _showMap ? 'Afficher la liste' : 'Afficher la carte',
          icon: Icon(_showMap ? Icons.view_list_outlined : Icons.map_outlined),
          onPressed: () => setState(() => _showMap = !_showMap),
        ),
        IconButton(
          tooltip: 'Créer une alerte',
          icon: const Icon(Icons.notifications_none),
          onPressed: _saveSearch,
        ),
      ],
    ),
    body: Column(
      children: [
        _FilterBar(
          filters: _filters,
          localities: _localities,
          onChanged: _apply,
          onMore: _openFilters,
        ),
        if (!_showMap)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 8, 0),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    _loading && _items.isEmpty
                        ? 'Recherche…'
                        : _total != null
                        ? '$_total bien${_total! > 1 ? 's' : ''} trouvé${_total! > 1 ? 's' : ''}'
                        : '',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
                TextButton.icon(
                  onPressed: _openSort,
                  icon: const Icon(Icons.swap_vert, size: 20),
                  label: const Text('Trier'),
                ),
              ],
            ),
          ),
        Expanded(
          child: _showMap
              ? ListingsMapView(
                  repository: widget.repository,
                  filters: _filters,
                  onOpen: _openFromMap,
                )
              : _results(),
        ),
      ],
    ),
  );

  void _openFromMap(MapPoint p) => Navigator.of(context).push(
    MaterialPageRoute<void>(
      builder: (_) => ListingDetailScreen(
        repository: widget.repository,
        reference: p.reference,
        signedIn: widget.signedIn,
        inquiries: widget.inquiries,
      ),
    ),
  );

  Widget _results() {
    if (_items.isEmpty) {
      if (_loading) return const Center(child: CircularProgressIndicator());
      if (_error != null) {
        return ErrorRetry(message: _error!, onRetry: _load);
      }
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(24),
          child: Text(
            'Aucune annonce ne correspond. Essayez d\'élargir vos critères.',
          ),
        ),
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.all(12),
      itemCount: _items.length + 1,
      itemBuilder: (context, i) {
        if (i == _items.length) {
          if (_error != null) {
            return Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: ErrorRetry(
                message: _error!,
                onRetry: () => _load(more: true),
              ),
            );
          }
          if (_loading) {
            return const Padding(
              padding: EdgeInsets.all(16),
              child: Center(child: CircularProgressIndicator()),
            );
          }
          return _cursor == null
              ? const SizedBox.shrink()
              : TextButton(
                  onPressed: () => _load(more: true),
                  child: const Text('Voir plus'),
                );
        }
        final l = _items[i];
        return ListingCard(
          listing: l,
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => ListingDetailScreen(
                repository: widget.repository,
                reference: l.reference,
                signedIn: widget.signedIn,
                inquiries: widget.inquiries,
              ),
            ),
          ),
        );
      },
    );
  }
}

class _FilterBar extends StatelessWidget {
  const _FilterBar({
    required this.filters,
    required this.localities,
    required this.onChanged,
    required this.onMore,
  });

  final SearchFilters filters;
  final List<Locality> localities;
  final ValueChanged<SearchFilters> onChanged;
  final VoidCallback onMore;

  static const _prices = [100000, 200000, 300000, 500000, 1000000];

  /// Filtre en pastille du design : contour discret, fond vert clair (ici orangé) et texte coloré quand il est actif.
  Widget _pill<T>({
    required T value,
    required bool active,
    required List<DropdownMenuItem<T>> items,
    required ValueChanged<T?> onChanged,
    required String label,
  }) => Semantics(
    label: label,
    child: Container(
      constraints: const BoxConstraints(minHeight: 44),
      padding: const EdgeInsets.only(left: 14, right: 8),
      decoration: BoxDecoration(
        color: active ? AppColors.primarySoft : Colors.white,
        borderRadius: BorderRadius.circular(AppRadius.sm),
        border: Border.all(color: active ? AppColors.primary : AppColors.line),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<T>(
          value: value,
          items: items,
          onChanged: onChanged,
          borderRadius: BorderRadius.circular(AppRadius.md),
          style: TextStyle(
            fontFamily: 'Inter',
            fontSize: 13,
            fontWeight: FontWeight.w700,
            color: active ? AppColors.primary : AppColors.ink,
          ),
          iconEnabledColor: active ? AppColors.primary : AppColors.muted,
        ),
      ),
    ),
  );

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    scrollDirection: Axis.horizontal,
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
    child: Row(
      children: [
        _pill<String>(
          label: 'Type de transaction',
          value: filters.transaction,
          active: true,
          items: const [
            DropdownMenuItem(value: 'rent_long', child: Text('Location')),
            DropdownMenuItem(value: 'sale', child: Text('Vente')),
          ],
          onChanged: (v) => onChanged(filters.copyWith(transaction: v)),
        ),
        const SizedBox(width: 8),
        _pill<String?>(
          label: 'Quartier',
          value: filters.locality,
          active: filters.locality != null,
          items: [
            const DropdownMenuItem<String?>(
              value: null,
              child: Text('Tous les quartiers'),
            ),
            for (final l in localities)
              DropdownMenuItem<String?>(value: l.slug, child: Text(l.name)),
          ],
          onChanged: (v) => onChanged(filters.copyWith(locality: () => v)),
        ),
        const SizedBox(width: 8),
        _pill<int?>(
          label: 'Nombre de chambres',
          value: filters.bedroomsMin,
          active: filters.bedroomsMin != null,
          items: const [
            DropdownMenuItem<int?>(value: null, child: Text('Chambres')),
            DropdownMenuItem<int?>(value: 1, child: Text('1+ chambre')),
            DropdownMenuItem<int?>(value: 2, child: Text('2+ chambres')),
            DropdownMenuItem<int?>(value: 3, child: Text('3+ chambres')),
            DropdownMenuItem<int?>(value: 4, child: Text('4+ chambres')),
          ],
          onChanged: (v) => onChanged(filters.copyWith(bedroomsMin: () => v)),
        ),
        const SizedBox(width: 8),
        _pill<int?>(
          label: 'Budget maximum',
          value: filters.priceMax,
          active: filters.priceMax != null,
          items: [
            const DropdownMenuItem<int?>(
              value: null,
              child: Text('Budget max'),
            ),
            for (final p in _prices)
              DropdownMenuItem<int?>(
                value: p,
                child: Text('≤ ${groupThousands(p)} FCFA'),
              ),
          ],
          onChanged: (v) => onChanged(filters.copyWith(priceMax: () => v)),
        ),
        const SizedBox(width: 8),
        ActionChip(
          avatar: const Icon(Icons.tune, size: 18, color: AppColors.primary),
          label: Text(
            filters.activeCount > 0
                ? 'Filtres (${filters.activeCount})'
                : 'Filtres',
          ),
          onPressed: onMore,
        ),
      ],
    ),
  );
}
