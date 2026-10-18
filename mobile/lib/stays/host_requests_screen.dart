import 'package:flutter/material.dart';

import '../core/format.dart';
import '../ui/theme.dart';
import '../ui/widgets.dart';
import 'my_stays_screen.dart' show StateChip;
import 'stay_search_screen.dart' show dayLabel;
import 'stays_repository.dart';

/// Demandes de séjour côté hôte : accepter, refuser ou annuler avec un motif. La gestion des logements, tarifs et photos se fait sur le site.
class HostRequestsScreen extends StatefulWidget {
  const HostRequestsScreen({super.key, required this.repository});

  final StaysRepository repository;

  @override
  State<HostRequestsScreen> createState() => _HostRequestsScreenState();
}

class _HostRequestsScreenState extends State<HostRequestsScreen> {
  String? _state = 'requested';
  late Future<List<StayBooking>> _future = widget.repository.hostBookings(
    state: _state,
  );
  String? _error;
  bool _busy = false;

  static const _filters = [
    (null, 'Toutes'),
    ('requested', 'À traiter'),
    ('accepted', 'À régler'),
    ('confirmed', 'Confirmées'),
  ];

  void _reload() => setState(() {
    _future = widget.repository.hostBookings(state: _state);
  });

  Future<String?> _ask(String title) async {
    final c = TextEditingController();
    final r = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: TextField(
          controller: c,
          maxLength: 300,
          decoration: const InputDecoration(
            labelText: 'Motif (visible par le voyageur)',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Retour'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, c.text.trim()),
            child: const Text('Confirmer'),
          ),
        ],
      ),
    );
    c.dispose();
    return (r == null || r.isEmpty) ? null : r;
  }

  Future<void> _act(StayBooking b, String action) async {
    String? reason;
    if (action != 'accept') {
      reason = await _ask(
        action == 'decline' ? 'Refuser cette demande' : 'Annuler ce séjour',
      );
      if (reason == null) return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.repository.hostAction(b.id, action, reason: reason);
      _reload();
    } catch (e) {
      _error = errorText(e);
    }
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Demandes de séjour')),
    body: Column(
      children: [
        SizedBox(
          height: 56,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.all(8),
            children: [
              for (final (v, l) in _filters)
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(l),
                    selected: _state == v,
                    onSelected: (_) {
                      _state = v;
                      _reload();
                    },
                  ),
                ),
            ],
          ),
        ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Text(
              _error!,
              style: const TextStyle(color: AppColors.danger),
            ),
          ),
        Expanded(
          child: FutureBuilder<List<StayBooking>>(
            future: _future,
            builder: (context, snap) {
              if (snap.connectionState != ConnectionState.done) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snap.hasError) {
                return ErrorRetry(
                  message: errorText(snap.error!),
                  onRetry: _reload,
                );
              }
              final items = snap.data!;
              if (items.isEmpty) {
                return const Center(
                  child: Text(
                    'Aucune demande dans cette catégorie.',
                    style: TextStyle(color: AppColors.muted),
                  ),
                );
              }
              return ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  for (final b in items)
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(14),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    b.unitName,
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                ),
                                StateChip(b),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Text(
                              '${b.guestFirstName} · ${dayLabel(b.checkIn)} → ${dayLabel(b.checkOut)} · ${b.guests} voyageur(s)',
                            ),
                            Text(
                              '${groupThousands(b.totalXof)} FCFA',
                              style: const TextStyle(
                                color: AppColors.primary,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            if (b.state == 'requested' && b.respondBy != null)
                              Text(
                                'À traiter avant le ${dayLabel(b.respondBy!.toLocal())}.',
                                style: const TextStyle(
                                  color: AppColors.warning,
                                  fontSize: 12,
                                ),
                              ),
                            const SizedBox(height: 8),
                            Wrap(
                              spacing: 8,
                              children: [
                                if (b.state == 'requested') ...[
                                  FilledButton(
                                    onPressed: _busy
                                        ? null
                                        : () => _act(b, 'accept'),
                                    child: const Text('Accepter'),
                                  ),
                                  OutlinedButton(
                                    onPressed: _busy
                                        ? null
                                        : () => _act(b, 'decline'),
                                    child: const Text('Refuser'),
                                  ),
                                ],
                                if (const [
                                  'accepted',
                                  'awaiting_payment',
                                  'confirmed',
                                ].contains(b.state))
                                  OutlinedButton(
                                    onPressed: _busy
                                        ? null
                                        : () => _act(b, 'cancel'),
                                    style: OutlinedButton.styleFrom(
                                      foregroundColor: AppColors.danger,
                                    ),
                                    child: const Text('Annuler le séjour'),
                                  ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
              );
            },
          ),
        ),
      ],
    ),
  );
}
