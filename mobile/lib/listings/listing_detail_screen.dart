import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/format.dart';
import '../ui/widgets.dart';
import '../inquiries/inquiries_repository.dart';
import '../inquiries/inquiries_screens.dart';
import 'listing_widgets.dart';
import 'listings_repository.dart';
import 'models.dart';

class ListingDetailScreen extends StatefulWidget {
  const ListingDetailScreen({
    super.key,
    required this.repository,
    required this.reference,
    this.signedIn = false,
    this.inquiries,
  });

  final ListingsRepository repository;
  final String reference;
  final bool signedIn;

  /// Absent hors connexion : le bouton de contact demande alors de se connecter.
  final InquiriesRepository? inquiries;

  @override
  State<ListingDetailScreen> createState() => _ListingDetailScreenState();
}

class _ListingDetailScreenState extends State<ListingDetailScreen> {
  late final Future<ListingDetail> _future = widget.repository.detail(
    widget.reference,
  );
  bool? _favorite;

  Future<void> _contact(ListingDetail d) async {
    final repo = widget.inquiries;
    if (!widget.signedIn || repo == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Connectez-vous (onglet Compte) pour contacter l\'annonceur.',
          ),
        ),
      );
      return;
    }
    if (await showContactSheet(context, repo, d.summary.id) && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Message envoyé. Retrouvez la conversation dans Messages.',
          ),
        ),
      );
    }
  }

  Future<void> _toggleFavorite(ListingDetail d) async {
    if (!widget.signedIn) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            "Connectez-vous (onglet Compte) pour enregistrer des favoris.",
          ),
        ),
      );
      return;
    }
    final add = !(_favorite ?? false);
    try {
      add
          ? await widget.repository.addFavorite(d.summary.id)
          : await widget.repository.removeFavorite(d.summary.id);
      if (mounted) setState(() => _favorite = add);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(errorText(e))));
      }
    }
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<ListingDetail>(
    future: _future,
    builder: (context, snap) {
      if (snap.connectionState != ConnectionState.done) {
        return Scaffold(
          appBar: AppBar(),
          body: const Center(child: CircularProgressIndicator()),
        );
      }
      if (snap.hasError) {
        final notFound =
            snap.error is ApiException &&
            (snap.error as ApiException).status == 404;
        return Scaffold(
          appBar: AppBar(),
          body: Center(
            child: Text(
              notFound
                  ? "Cette annonce n'est plus disponible."
                  : errorText(snap.error!),
            ),
          ),
        );
      }
      final d = snap.data!;
      final s = d.summary;
      return Scaffold(
        appBar: AppBar(
          title: Text(s.reference),
          actions: [
            IconButton(
              tooltip: 'Favori',
              icon: Icon(
                (_favorite ?? false) ? Icons.favorite : Icons.favorite_border,
              ),
              onPressed: () => _toggleFavorite(d),
            ),
          ],
        ),
        body: ListView(
          children: [
            if (d.imageUrls.isNotEmpty)
              SizedBox(
                height: 240,
                child: PageView(
                  children: [for (final u in d.imageUrls) CoverImage(url: u)],
                ),
              ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    formatPrice(s.priceXof, s.transaction) +
                        (s.negotiable ? ' · négociable' : ''),
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  Text(
                    s.title ?? s.reference,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  if (s.locality != null) Text(s.locality!),
                  const SizedBox(height: 8),
                  FreshnessBadge(availability: s.availability),
                  if (d.description != null) ...[
                    const SizedBox(height: 16),
                    Text(d.description!),
                  ],
                  if (d.amenities.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    Wrap(
                      spacing: 8,
                      children: [
                        for (final a in d.amenities) Chip(label: Text(a)),
                      ],
                    ),
                  ],
                  if (d.costs.values.any((v) => v != null)) _Costs(detail: d),
                  _Passport(detail: d),
                  const SizedBox(height: 16),
                  Text('Annonceur : ${d.advertiserName}'),
                  const SizedBox(height: 16),
                  FilledButton.icon(
                    icon: const Icon(Icons.chat_bubble_outline),
                    label: const Text("Contacter l'annonceur"),
                    onPressed: () => _contact(d),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    },
  );
}

class _Costs extends StatelessWidget {
  const _Costs({required this.detail});

  final ListingDetail detail;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 16),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          "Frais à l'entrée",
          style: Theme.of(context).textTheme.titleMedium,
        ),
        for (final e in detail.costs.entries)
          if (e.value != null)
            Text('${e.key} : ${groupThousands(e.value!)} FCFA'),
        if (detail.costsDisclaimer != null)
          Text(
            detail.costsDisclaimer!,
            style: Theme.of(context).textTheme.bodySmall,
          ),
      ],
    ),
  );
}

class _Passport extends StatelessWidget {
  const _Passport({required this.detail});

  final ListingDetail detail;

  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(top: 16),
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Passeport du Bien${detail.passportReference != null ? ' · ${detail.passportReference}' : ''}',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          // Contrôles explicites et datés, jamais une note globale.
          for (final c in detail.checks)
            Text(
              '${c.status == 'passed' ? '✓' : '·'} ${checkLabels[c.type] ?? c.type}'
              '${c.performedAt != null ? ' (le ${c.performedAt!.day.toString().padLeft(2, '0')}/${c.performedAt!.month.toString().padLeft(2, '0')}/${c.performedAt!.year})' : ''}',
            ),
          if (detail.checks.isEmpty)
            const Text('Aucun contrôle effectué pour le moment.'),
          const SizedBox(height: 8),
          Text(
            detail.passportNotice,
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    ),
  );
}
