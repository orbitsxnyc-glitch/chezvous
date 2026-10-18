import 'dart:math';

import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/format.dart';
import '../listings/listing_widgets.dart';
import '../ui/theme.dart';
import '../ui/widgets.dart';
import 'stay_search_screen.dart' show dayLabel;
import 'stays_repository.dart';

/// Fiche d'un logement : photos, équipements, dates, prix détaillé calculé par le serveur, demande sans débit.
class StayDetailScreen extends StatefulWidget {
  const StayDetailScreen({
    super.key,
    required this.repository,
    required this.unitId,
    required this.signedIn,
    this.initialRange,
    this.initialGuests = 2,
    this.onNeedSignIn,
    this.onOpenBooking,
  });

  final StaysRepository repository;
  final String unitId;
  final bool signedIn;
  final DateTimeRange? initialRange;
  final int initialGuests;
  final VoidCallback? onNeedSignIn;
  final ValueChanged<String>? onOpenBooking;

  @override
  State<StayDetailScreen> createState() => _StayDetailScreenState();
}

class _StayDetailScreenState extends State<StayDetailScreen> {
  late Future<StayUnit> _future = widget.repository.unit(widget.unitId);
  late DateTimeRange? _range = widget.initialRange;
  late int _guests = widget.initialGuests;
  StayPricing? _pricing;
  String? _error;
  bool _busy = false;

  /// Clé d'idempotence propre à cette intention : un double appui ne crée jamais deux demandes.
  late final String _key =
      '${DateTime.now().microsecondsSinceEpoch}-${Random().nextInt(1 << 32)}';

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
      setState(() {
        _range = picked;
        _pricing = null;
      });
    }
  }

  Future<void> _price() async {
    if (_range == null) {
      setState(() => _error = 'Choisissez d\'abord vos dates.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final u = await widget.repository.unit(
        widget.unitId,
        checkIn: _range!.start,
        checkOut: _range!.end,
        guests: _guests,
      );
      if (mounted) setState(() => _pricing = u.pricing);
    } catch (e) {
      if (mounted) setState(() => _error = errorText(e));
    }
    if (mounted) setState(() => _busy = false);
  }

  Future<void> _request() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final b = await widget.repository.request(
        widget.unitId,
        _range!.start,
        _range!.end,
        _guests,
        _key,
      );
      if (!mounted) return;
      Navigator.of(context).pop();
      widget.onOpenBooking?.call(b.id);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Demande envoyée. Retrouvez-la dans Mes séjours.'),
        ),
      );
      return;
    } on ApiException catch (e) {
      _error = e.message;
    } catch (e) {
      _error = errorText(e);
    }
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(),
    body: FutureBuilder<StayUnit>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          return ErrorRetry(
            message: errorText(snap.error!),
            onRetry: () => setState(() {
              _future = widget.repository.unit(widget.unitId)..ignore();
            }),
          );
        }
        final u = snap.data!;
        return ListView(
          children: [
            SizedBox(
              height: 240,
              child: u.photos.isEmpty
                  ? const CoverImage(url: null)
                  : PageView(
                      children: [for (final p in u.photos) CoverImage(url: p)],
                    ),
            ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(u.name, style: Theme.of(context).textTheme.titleLarge),
                  if (u.locality != null)
                    Text(
                      '${u.locality}, Dakar',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  const SizedBox(height: 8),
                  Text(
                    u.fromNightlyXof == null
                        ? 'Tarif à venir'
                        : formatPrice(u.fromNightlyXof, 'rent_furnished_short'),
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontWeight: FontWeight.w800,
                      fontSize: 22,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    [
                      ?u.type,
                      if (u.bedrooms != null) '${u.bedrooms} chambre(s)',
                      if (u.bathrooms != null) '${u.bathrooms} sdb',
                      '${u.capacity} voyageur(s) max.',
                    ].join(' · '),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  if (u.amenities.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    Wrap(
                      spacing: 14,
                      runSpacing: 8,
                      children: [
                        for (final a in u.amenities)
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(
                                Icons.check,
                                size: 16,
                                color: AppColors.success,
                              ),
                              const SizedBox(width: 4),
                              Text(a, style: const TextStyle(fontSize: 13)),
                            ],
                          ),
                      ],
                    ),
                  ],
                  const SizedBox(height: 20),
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: AppColors.sand,
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      border: Border.all(color: AppColors.sandDeep),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const Text(
                          'Votre séjour',
                          style: TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 17,
                          ),
                        ),
                        const SizedBox(height: 10),
                        OutlinedButton.icon(
                          onPressed: _pickDates,
                          icon: const Icon(Icons.calendar_month_outlined),
                          label: Text(
                            _range == null
                                ? 'Choisir les dates'
                                : '${dayLabel(_range!.start)} → ${dayLabel(_range!.end)} · ${nightsBetween(_range!.start, _range!.end)} nuit(s)',
                          ),
                        ),
                        Row(
                          children: [
                            const Expanded(
                              child: Text(
                                'Voyageurs',
                                style: TextStyle(fontWeight: FontWeight.w700),
                              ),
                            ),
                            IconButton(
                              tooltip: 'Un voyageur de moins',
                              onPressed: _guests > 1
                                  ? () => setState(() {
                                      _guests--;
                                      _pricing = null;
                                    })
                                  : null,
                              icon: const Icon(Icons.remove_circle_outline),
                            ),
                            Text(
                              '$_guests',
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            IconButton(
                              tooltip: 'Un voyageur de plus',
                              onPressed: _guests < u.capacity
                                  ? () => setState(() {
                                      _guests++;
                                      _pricing = null;
                                    })
                                  : null,
                              icon: const Icon(Icons.add_circle_outline),
                            ),
                          ],
                        ),
                        if (u.minNights > 1)
                          Text(
                            'Durée minimale : ${u.minNights} nuits.',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        const SizedBox(height: 8),
                        OutlinedButton(
                          onPressed: _busy ? null : _price,
                          child: const Text('Voir le prix et la disponibilité'),
                        ),
                        if (_error != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 10),
                            child: Text(
                              _error!,
                              style: const TextStyle(color: AppColors.danger),
                            ),
                          ),
                        if (_pricing != null && !_pricing!.available)
                          Padding(
                            padding: const EdgeInsets.only(top: 10),
                            child: Text(
                              _pricing!.message ?? 'Indisponible.',
                              style: const TextStyle(
                                color: AppColors.warning,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        if (_pricing?.available == true)
                          ..._priceBlock(_pricing!),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'L\'adresse exacte est communiquée après confirmation du séjour. Les échanges se font par ChezVous.',
                    style: TextStyle(color: AppColors.muted, fontSize: 12),
                  ),
                ],
              ),
            ),
          ],
        );
      },
    ),
  );

  List<Widget> _priceBlock(StayPricing p) => [
    const SizedBox(height: 12),
    for (final l in p.lines)
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(
          children: [
            Expanded(child: Text(l.label)),
            Text(
              '${groupThousands(l.amountXof)} FCFA',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ],
        ),
      ),
    const Divider(),
    Row(
      children: [
        const Expanded(
          child: Text(
            'Total',
            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
          ),
        ),
        Text(
          '${groupThousands(p.totalXof ?? 0)} FCFA',
          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
        ),
      ],
    ),
    if (p.notice != null)
      Padding(
        padding: const EdgeInsets.only(top: 6),
        child: Text(p.notice!, style: Theme.of(context).textTheme.bodySmall),
      ),
    const SizedBox(height: 12),
    if (widget.signedIn)
      PrimaryButton(
        label: 'Envoyer ma demande',
        pending: _busy,
        onPressed: _request,
      )
    else
      PrimaryButton(
        label: 'Se connecter pour demander',
        onPressed: () {
          Navigator.of(context).pop();
          widget.onNeedSignIn?.call();
        },
      ),
    const SizedBox(height: 6),
    Text(
      'Aucun débit à cette étape : l\'hôte doit d\'abord accepter, puis vous disposez d\'un délai pour régler.',
      style: Theme.of(context).textTheme.bodySmall,
    ),
  ];
}
