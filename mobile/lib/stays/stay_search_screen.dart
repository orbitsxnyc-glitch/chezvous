import 'package:flutter/material.dart';

import '../core/format.dart';
import '../listings/listing_widgets.dart';
import '../ui/theme.dart';
import '../ui/widgets.dart';
import 'stay_detail_screen.dart';
import 'stays_repository.dart';

String dayLabel(DateTime d) =>
    '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')}/${d.year}';

/// Séjours meublés : dates, voyageurs, résultats avec prix total. La disponibilité est indicative jusqu'à l'acceptation de l'hôte.
class StaySearchScreen extends StatefulWidget {
  const StaySearchScreen({
    super.key,
    required this.repository,
    required this.signedIn,
    this.onNeedSignIn,
    this.onOpenBooking,
  });

  final StaysRepository repository;
  final bool signedIn;
  final VoidCallback? onNeedSignIn;
  final ValueChanged<String>? onOpenBooking;

  @override
  State<StaySearchScreen> createState() => _StaySearchScreenState();
}

class _StaySearchScreenState extends State<StaySearchScreen> {
  DateTimeRange? _range;
  int _guests = 2;
  late Future<List<StayUnit>> _future = _load();

  Future<List<StayUnit>> _load() => widget.repository.search(
    checkIn: _range?.start,
    checkOut: _range?.end,
    guests: _guests,
  );

  void _reload() => setState(() {
    _future = _load();
  });

  Future<void> _pickDates() async {
    final now = DateTime.now();
    final picked = await showDateRangePicker(
      context: context,
      firstDate: DateTime(now.year, now.month, now.day),
      lastDate: now.add(const Duration(days: 548)),
      initialDateRange: _range,
      helpText: 'Dates du séjour',
      saveText: 'Valider',
    );
    if (picked != null) {
      _range = picked;
      _reload();
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Séjours meublés')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const AccentHeadline('Un séjour ', 'en toute clarté', size: 28),
        const SizedBox(height: 6),
        Text(
          'Le prix total est affiché avant toute demande.',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: 16),
        OutlinedButton.icon(
          onPressed: _pickDates,
          icon: const Icon(Icons.calendar_month_outlined),
          label: Text(
            _range == null
                ? 'Choisir les dates'
                : '${dayLabel(_range!.start)} → ${dayLabel(_range!.end)} · ${nightsBetween(_range!.start, _range!.end)} nuit(s)',
          ),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            const Icon(Icons.group_outlined, color: AppColors.muted),
            const SizedBox(width: 8),
            const Expanded(
              child: Text(
                'Voyageurs',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
            IconButton(
              tooltip: 'Un voyageur de moins',
              onPressed: _guests > 1
                  ? () {
                      _guests--;
                      _reload();
                    }
                  : null,
              icon: const Icon(Icons.remove_circle_outline),
            ),
            Text(
              '$_guests',
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
            ),
            IconButton(
              tooltip: 'Un voyageur de plus',
              onPressed: _guests < 20
                  ? () {
                      _guests++;
                      _reload();
                    }
                  : null,
              icon: const Icon(Icons.add_circle_outline),
            ),
          ],
        ),
        const SizedBox(height: 12),
        FutureBuilder<List<StayUnit>>(
          future: _future,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const Padding(
                padding: EdgeInsets.all(32),
                child: Center(child: CircularProgressIndicator()),
              );
            }
            if (snap.hasError) {
              return ErrorRetry(
                message: errorText(snap.error!),
                onRetry: _reload,
              );
            }
            final items = snap.data!;
            if (items.isEmpty) {
              return const Padding(
                padding: EdgeInsets.all(24),
                child: Text(
                  'Aucun logement ne correspond. Essayez d\'autres dates.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: AppColors.muted),
                ),
              );
            }
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${items.length} logement${items.length > 1 ? 's' : ''}',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 8),
                for (final u in items)
                  _StayCard(unit: u, onTap: () => _open(u)),
              ],
            );
          },
        ),
      ],
    ),
  );

  void _open(StayUnit u) => Navigator.of(context).push(
    MaterialPageRoute<void>(
      builder: (_) => StayDetailScreen(
        repository: widget.repository,
        unitId: u.id,
        signedIn: widget.signedIn,
        initialRange: _range,
        initialGuests: _guests,
        onNeedSignIn: widget.onNeedSignIn,
        onOpenBooking: widget.onOpenBooking,
      ),
    ),
  );
}

class _StayCard extends StatelessWidget {
  const _StayCard({required this.unit, required this.onTap});

  final StayUnit unit;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final u = unit;
    final facts = [
      ?u.type,
      if (u.bedrooms != null) '${u.bedrooms} ch.',
      if (u.bathrooms != null) '${u.bathrooms} sdb',
      '${u.capacity} voyageur${u.capacity > 1 ? 's' : ''}',
    ].join(' · ');
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AspectRatio(
              aspectRatio: 16 / 9,
              child: CoverImage(url: u.photos.isEmpty ? null : u.photos.first),
            ),
            Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    u.name,
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                    ),
                  ),
                  if (u.locality != null)
                    Text(
                      '${u.locality}, Dakar',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  const SizedBox(height: 4),
                  Text(facts, style: Theme.of(context).textTheme.bodySmall),
                  const SizedBox(height: 8),
                  Text(
                    u.fromNightlyXof == null
                        ? 'Tarif à venir'
                        : formatPrice(u.fromNightlyXof, 'rent_furnished_short'),
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                    ),
                  ),
                  if (u.pricing?.totalXof != null)
                    Text(
                      'Total ${groupThousands(u.pricing!.totalXof!)} FCFA',
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
