import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/format.dart';
import '../ui/widgets.dart';
import '../inquiries/inquiries_repository.dart';
import '../inquiries/inquiries_screens.dart';
import '../ui/theme.dart';
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
        bottomNavigationBar: SafeArea(
          child: Container(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
            decoration: const BoxDecoration(
              color: Colors.white,
              border: Border(top: BorderSide(color: AppColors.line)),
            ),
            child: FilledButton.icon(
              icon: const Icon(Icons.chat_bubble_outline),
              label: const Text("Contacter l'annonceur"),
              onPressed: () => _contact(d),
            ),
          ),
        ),
        body: ListView(
          children: [
            if (d.imageUrls.isNotEmpty)
              SizedBox(
                height: 270,
                child: PageView(
                  children: [for (final u in d.imageUrls) CoverImage(url: u)],
                ),
              ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (s.locality != null)
                    Text(
                      s.locality!,
                      style: Theme.of(context).textTheme.bodySmall
                          ?.copyWith(fontWeight: FontWeight.w700),
                    ),
                  const SizedBox(height: 4),
                  Text(
                    s.title ?? s.reference,
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    formatPrice(s.priceXof, s.transaction) +
                        (s.negotiable ? ' · négociable' : ''),
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontWeight: FontWeight.w800,
                      fontSize: 22,
                    ),
                  ),
                  const SizedBox(height: 10),
                  FreshnessBadge(availability: s.availability),
                  _KeyFacts(summary: s),
                  if (d.description != null) ...[
                    const SizedBox(height: 20),
                    Text(
                      'Description',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      d.description!,
                      style: const TextStyle(
                        color: AppColors.muted,
                        height: 1.7,
                      ),
                    ),
                  ],
                  if (d.amenities.isNotEmpty) ...[
                    const SizedBox(height: 20),
                    Text(
                      'Équipements',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 14,
                      runSpacing: 10,
                      children: [
                        for (final a in d.amenities)
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(
                                Icons.check,
                                size: 16,
                                color: AppColors.success,
                              ),
                              const SizedBox(width: 6),
                              Text(a, style: const TextStyle(fontSize: 13)),
                            ],
                          ),
                      ],
                    ),
                  ],
                  if (d.costs.values.any((v) => v != null)) _Costs(detail: d),
                  _Passport(detail: d),
                  const SizedBox(height: 20),
                  Row(
                    children: [
                      CircleAvatar(
                        backgroundColor: AppColors.primarySoft,
                        foregroundColor: AppColors.primary,
                        child: Text(
                          d.advertiserName.isEmpty
                              ? '?'
                              : d.advertiserName[0].toUpperCase(),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          'Annonceur : ${d.advertiserName}',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                ],
              ),
            ),
          ],
        ),
      );
    },
  );
}

class _KeyFacts extends StatelessWidget {
  const _KeyFacts({required this.summary});

  final ListingSummary summary;

  @override
  Widget build(BuildContext context) {
    final items = <(String, String)>[
      if (summary.propertyType != null) (summary.propertyType!, 'Type'),
      if (summary.bedrooms != null) ('${summary.bedrooms}', 'Chambres'),
      if (summary.surfaceM2 != null) ('${summary.surfaceM2} m²', 'Surface'),
      (summary.furnished ? 'Oui' : 'Non', 'Meublé'),
    ];
    return Container(
      margin: const EdgeInsets.only(top: 20),
      padding: const EdgeInsets.symmetric(vertical: 14),
      decoration: const BoxDecoration(
        border: Border.symmetric(horizontal: BorderSide(color: AppColors.line)),
      ),
      child: Row(
        children: [
          for (final (value, label) in items)
            Expanded(
              child: Column(
                children: [
                  Text(
                    value,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 14,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(label, style: Theme.of(context).textTheme.bodySmall),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// Frais à l'entrée : montants saisis par l'annonceur, jamais de total calculé.
class _Costs extends StatelessWidget {
  const _Costs({required this.detail});

  final ListingDetail detail;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(top: 24),
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: AppColors.sand,
      borderRadius: BorderRadius.circular(AppRadius.md),
      border: Border.all(color: AppColors.sandDeep),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          "Frais à l'entrée",
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 8),
        for (final e in detail.costs.entries)
          if (e.value != null)
            Container(
              padding: const EdgeInsets.symmetric(vertical: 10),
              decoration: const BoxDecoration(
                border: Border(bottom: BorderSide(color: Color(0xFFE2D8C8))),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(e.key, style: const TextStyle(fontSize: 13)),
                  Text(
                    '${groupThousands(e.value!)} FCFA',
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: AppColors.warningSoft,
            borderRadius: BorderRadius.circular(7),
          ),
          child: Text(
            detail.costsDisclaimer ?? "Montants renseignés par l'annonceur, à confirmer avant tout paiement.",
            style: const TextStyle(
              color: Color(0xFF6F4A16),
              fontSize: 12,
              height: 1.5,
            ),
          ),
        ),
      ],
    ),
  );
}

/// Passeport du Bien : contrôles explicites et datés, jamais de note globale.
class _Passport extends StatelessWidget {
  const _Passport({required this.detail});

  final ListingDetail detail;

  String _date(DateTime d) =>
      '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')}/${d.year}';

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(top: 24),
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [Color(0xFFF3F9F6), Colors.white],
      ),
      borderRadius: BorderRadius.circular(AppRadius.md),
      border: Border.all(color: const Color(0xFFB9D1C8)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const CircleAvatar(
              radius: 22,
              backgroundColor: AppColors.primary,
              child: Icon(
                Icons.verified_user_outlined,
                color: Colors.white,
                size: 22,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'PASSEPORT DU BIEN',
                    style: TextStyle(
                      color: AppColors.primary,
                      fontWeight: FontWeight.w800,
                      fontSize: 11,
                      letterSpacing: 1.4,
                    ),
                  ),
                  if (detail.passportReference != null)
                    Text(
                      detail.passportReference!,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        if (detail.checks.isEmpty)
          const Text(
            'Aucun contrôle effectué pour le moment.',
            style: TextStyle(color: AppColors.muted),
          ),
        for (final c in detail.checks)
          Container(
            margin: const EdgeInsets.only(bottom: 8),
            padding: const EdgeInsets.all(11),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(AppRadius.sm),
              border: Border.all(color: const Color(0xFFDBE7E2)),
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 14,
                  backgroundColor: c.status == 'passed'
                      ? AppColors.successSoft
                      : const Color(0xFFEDF0EE),
                  child: Icon(
                    c.status == 'passed' ? Icons.check : Icons.remove,
                    size: 16,
                    color: c.status == 'passed'
                        ? AppColors.success
                        : AppColors.muted,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        checkLabels[c.type] ?? c.type,
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 13,
                        ),
                      ),
                      if (c.performedAt != null)
                        Text(
                          'Le ${_date(c.performedAt!)}',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        const SizedBox(height: 6),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: AppColors.infoSoft,
            borderRadius: BorderRadius.circular(AppRadius.sm),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(
                Icons.shield_outlined,
                size: 18,
                color: Color(0xFF194B82),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  detail.passportNotice,
                  style: const TextStyle(
                    color: Color(0xFF194B82),
                    fontSize: 12,
                    height: 1.5,
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}
