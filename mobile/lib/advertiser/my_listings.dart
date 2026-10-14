import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/format.dart';
import '../inquiries/inquiries_screens.dart' show formatDateTime;
import '../ui/widgets.dart';

Map<String, dynamic> _map(Object? v) =>
    v is Map<String, dynamic> ? v : const {};

const listingStatusLabels = {
  'draft': 'Brouillon',
  'pending_moderation': 'En modération',
  'published': 'Publiée',
  'rejected': 'Refusée',
  'paused': 'En pause',
  'closed': 'Clôturée',
  'expired': 'Expirée',
  'hidden': 'Masquée',
  'archived': 'Archivée',
};

const availabilityChoices = {
  'available': 'Toujours disponible',
  'reserved': 'Réservé',
  'rented': 'Loué / vendu',
  'withdrawn': 'Retiré du marché',
};

class MyListing {
  const MyListing({
    required this.id,
    required this.reference,
    required this.status,
    required this.transaction,
    required this.negotiable,
    this.title,
    this.priceXof,
    this.expiresAt,
    this.rejectionComment,
    this.missingFields = const [],
  });

  factory MyListing.fromJson(Map<String, dynamic> j) => MyListing(
    id: '${j['id']}',
    reference: '${j['reference']}',
    status: '${j['status']}',
    transaction: '${j['transaction'] ?? 'rent_long'}',
    negotiable: j['negotiable'] == true,
    title: j['title'] as String?,
    priceXof: (j['price_xof'] as num?)?.toInt(),
    expiresAt: DateTime.tryParse('${j['expires_at']}'),
    rejectionComment: _map(j['rejection'])['comment'] as String?,
    missingFields: (j['missing_fields'] as List? ?? const [])
        .map((e) => '$e')
        .toList(),
  );

  final String id;
  final String reference;
  final String status;
  final String transaction;
  final bool negotiable;
  final String? title;
  final int? priceXof;
  final DateTime? expiresAt;
  final String? rejectionComment;
  final List<String> missingFields;
}

class MyListingsRepository {
  const MyListingsRepository(this.api);

  final ApiClient api;

  Future<List<MyListing>> list() async {
    final res = await api.get('/my/listings');
    return (res['data'] as List? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(MyListing.fromJson)
        .toList();
  }

  Future<void> pause(String id) => api.post('/my/listings/$id/pause');

  Future<void> resume(String id) => api.post('/my/listings/$id/resume');

  Future<void> close(String id) => api.post('/my/listings/$id/close');

  Future<void> confirmAvailability(String id, String response) =>
      api.post('/my/listings/$id/availability', {'response': response});
}

class MyListingsScreen extends StatefulWidget {
  const MyListingsScreen({super.key, required this.repository});

  final MyListingsRepository repository;

  @override
  State<MyListingsScreen> createState() => _MyListingsScreenState();
}

class _MyListingsScreenState extends State<MyListingsScreen> {
  late Future<List<MyListing>> _future = widget.repository.list();

  void _reload() => setState(() => _future = widget.repository.list());

  Future<void> _run(Future<void> Function() action, String done) async {
    try {
      await action();
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(done)));
      }
      _reload();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(errorText(e))));
      }
    }
  }

  Future<void> _availability(MyListing l) async {
    final choice = await showDialog<String>(
      context: context,
      builder: (ctx) => SimpleDialog(
        title: const Text("Où en est ce bien ?"),
        children: [
          for (final e in availabilityChoices.entries)
            SimpleDialogOption(
              onPressed: () => Navigator.pop(ctx, e.key),
              child: Text(e.value),
            ),
        ],
      ),
    );
    if (choice != null) {
      await _run(
        () => widget.repository.confirmAvailability(l.id, choice),
        'Disponibilité enregistrée.',
      );
    }
  }

  Future<void> _close(MyListing l) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Clôturer cette annonce ?'),
        content: const Text(
          "Elle ne sera plus visible. Cette action est définitive.",
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Clôturer'),
          ),
        ],
      ),
    );
    if (ok == true) {
      await _run(() => widget.repository.close(l.id), 'Annonce clôturée.');
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Mes annonces')),
    body: FutureBuilder<List<MyListing>>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          final e = snap.error;
          final denied = e is ApiException && e.status == 403;
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Text(
                denied
                    ? "Votre compte ne publie pas d'annonces."
                    : errorText(e!),
                textAlign: TextAlign.center,
              ),
            ),
          );
        }
        final items = snap.data!;
        if (items.isEmpty) {
          return const Center(
            child: Padding(
              padding: EdgeInsets.all(24),
              child: Text(
                "Aucune annonce. La création et l'édition se font pour l'instant sur le site web.",
                textAlign: TextAlign.center,
              ),
            ),
          );
        }
        return RefreshIndicator(
          onRefresh: () async {
            _reload();
            await _future;
          },
          child: ListView(
            padding: const EdgeInsets.all(12),
            children: [for (final l in items) _tile(l)],
          ),
        );
      },
    ),
  );

  Widget _tile(MyListing l) => Card(
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l.title ?? l.reference,
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          Text('${l.reference} · ${listingStatusLabels[l.status] ?? l.status}'),
          Text(formatPrice(l.priceXof, l.transaction)),
          if (l.status == 'published' && l.expiresAt != null)
            Text('Expire le ${formatDateTime(l.expiresAt!)}'),
          if (l.status == 'rejected' && l.rejectionComment != null)
            Text('Motif : ${l.rejectionComment}'),
          if (l.missingFields.isNotEmpty)
            Text('À compléter sur le site : ${l.missingFields.join(', ')}'),
          Wrap(
            spacing: 8,
            children: [
              if (l.status == 'published')
                TextButton(
                  onPressed: () => _availability(l),
                  child: const Text('Confirmer la disponibilité'),
                ),
              if (l.status == 'published')
                TextButton(
                  onPressed: () => _run(
                    () => widget.repository.pause(l.id),
                    'Annonce mise en pause.',
                  ),
                  child: const Text('Mettre en pause'),
                ),
              if (l.status == 'paused')
                TextButton(
                  onPressed: () => _run(
                    () => widget.repository.resume(l.id),
                    'Annonce reprise.',
                  ),
                  child: const Text('Reprendre'),
                ),
              if (l.status == 'published' || l.status == 'paused')
                TextButton(
                  onPressed: () => _close(l),
                  child: const Text('Clôturer'),
                ),
            ],
          ),
        ],
      ),
    ),
  );
}
