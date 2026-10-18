import 'dart:async';

import 'package:flutter/material.dart';

import '../core/format.dart';
import '../ui/theme.dart';
import '../ui/widgets.dart';
import 'listings_repository.dart';
import 'models.dart';

/// Bornes du curseur de budget : les deux extrémités signifient « pas de limite ».
const budgetMin = 100000.0;
const budgetMax = 2000000.0;

/// Filtres avancés : transaction, quartier, budget, chambres, type de bien ; le bouton annonce le nombre de biens trouvés.
class FiltersScreen extends StatefulWidget {
  const FiltersScreen({
    super.key,
    required this.repository,
    required this.initial,
  });

  final ListingsRepository repository;
  final SearchFilters initial;

  @override
  State<FiltersScreen> createState() => _FiltersScreenState();
}

class _FiltersScreenState extends State<FiltersScreen> {
  late SearchFilters _filters = widget.initial;
  List<Locality> _localities = const [];
  List<PropertyKind> _types = const [];
  int? _total;
  bool _counting = false;
  Timer? _debounce;
  int _generation = 0;

  @override
  void initState() {
    super.initState();
    widget.repository
        .neighborhoods()
        .then((v) {
          if (mounted) setState(() => _localities = v);
        })
        .catchError((_) {});
    widget.repository
        .propertyTypes()
        .then((v) {
          if (mounted) setState(() => _types = v);
        })
        .catchError((_) {});
    _count();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  void _change(SearchFilters f) {
    setState(() => _filters = f);
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), _count);
  }

  /// Nombre de biens pour les critères choisis (une réponse tardive d'un critère précédent est ignorée).
  Future<void> _count() async {
    final generation = ++_generation;
    setState(() => _counting = true);
    try {
      final page = await widget.repository.search(_filters);
      if (mounted && generation == _generation) {
        setState(() => _total = page.total);
      }
    } catch (_) {
      if (mounted && generation == _generation) setState(() => _total = null);
    }
    if (mounted && generation == _generation) setState(() => _counting = false);
  }

  RangeValues get _range => RangeValues(
    (_filters.priceMin ?? budgetMin).clamp(budgetMin, budgetMax).toDouble(),
    (_filters.priceMax ?? budgetMax).clamp(budgetMin, budgetMax).toDouble(),
  );

  String get _rangeLabel {
    final r = _range;
    final noMin = r.start <= budgetMin;
    final noMax = r.end >= budgetMax;
    if (noMin && noMax) return 'Tous les budgets';
    if (noMin) return "Jusqu'à ${groupThousands(r.end.round())} FCFA";
    if (noMax) return 'À partir de ${groupThousands(r.start.round())} FCFA';
    return '${groupThousands(r.start.round())} – ${groupThousands(r.end.round())} FCFA';
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final label = _counting
        ? 'Recherche…'
        : _total == null
        ? 'Voir les biens'
        : 'Voir $_total bien${_total! > 1 ? 's' : ''}';
    return Scaffold(
      appBar: AppBar(title: const Text('Filtres avancés')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
        children: [
          SegmentedButton<String>(
            showSelectedIcon: false,
            segments: const [
              ButtonSegment(value: 'rent_long', label: Text('Location')),
              ButtonSegment(value: 'sale', label: Text('Vente')),
            ],
            selected: {_filters.transaction},
            onSelectionChanged: (v) =>
                _change(_filters.copyWith(transaction: v.first)),
          ),
          const SizedBox(height: 24),
          Text('Quartier', style: text.titleMedium),
          const SizedBox(height: 8),
          DropdownButtonFormField<String?>(
            isExpanded: true,
            initialValue: _filters.locality,
            decoration: const InputDecoration(
              prefixIcon: Icon(Icons.place_outlined),
            ),
            items: [
              const DropdownMenuItem<String?>(
                value: null,
                child: Text('Tous les quartiers'),
              ),
              for (final l in _localities)
                DropdownMenuItem<String?>(value: l.slug, child: Text(l.name)),
            ],
            onChanged: (v) => _change(_filters.copyWith(locality: () => v)),
          ),
          const SizedBox(height: 24),
          Text('Budget', style: text.titleMedium),
          const SizedBox(height: 4),
          Text(
            _rangeLabel,
            style: const TextStyle(
              color: AppColors.primary,
              fontWeight: FontWeight.w800,
            ),
          ),
          RangeSlider(
            values: _range,
            min: budgetMin,
            max: budgetMax,
            divisions: 38,
            labels: RangeLabels(
              groupThousands(_range.start.round()),
              groupThousands(_range.end.round()),
            ),
            onChanged: (r) => _change(
              _filters.copyWith(
                // Les extrémités du curseur signifient « pas de limite » : on n'envoie alors aucun critère.
                priceMin: () => r.start <= budgetMin ? null : r.start.round(),
                priceMax: () => r.end >= budgetMax ? null : r.end.round(),
              ),
            ),
          ),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('100 000', style: text.bodySmall),
              Text('2 000 000', style: text.bodySmall),
            ],
          ),
          const SizedBox(height: 24),
          Text('Chambres', style: text.titleMedium),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: [
              for (final n in const [1, 2, 3, 4])
                ChoiceChip(
                  label: Text('$n+'),
                  selected: _filters.bedroomsMin == n,
                  onSelected: (on) => _change(
                    _filters.copyWith(bedroomsMin: () => on ? n : null),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 24),
          Text('Type de bien', style: text.titleMedium),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final t in _types)
                ChoiceChip(
                  label: Text(t.label),
                  selected: _filters.type == t.code,
                  onSelected: (on) => _change(
                    _filters.copyWith(type: () => on ? t.code : null),
                  ),
                ),
            ],
          ),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(top: BorderSide(color: AppColors.line)),
          ),
          child: Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => _change(
                    SearchFilters(
                      transaction: _filters.transaction,
                      sort: _filters.sort,
                    ),
                  ),
                  child: const Text('Réinitialiser'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                flex: 2,
                child: PrimaryButton(
                  label: label,
                  onPressed: () => Navigator.of(context).pop(_filters),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
