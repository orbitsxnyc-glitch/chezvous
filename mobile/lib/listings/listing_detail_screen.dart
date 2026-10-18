import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/format.dart';
import '../ui/widgets.dart';
import '../inquiries/inquiries_repository.dart';
import '../inquiries/inquiries_screens.dart';
import '../ui/theme.dart';
import 'listing_widgets.dart';
import 'map_view.dart';
import 'listings_repository.dart';
import 'models.dart';
import 'passport_screen.dart';

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
  late Future<ListingDetail> _future = widget.repository.detail(
    widget.reference,
  );
  bool? _favorite;
  int _page = 0;
  int _tab = 0;
  final _pages = PageController();

  /// Signalement : réservé aux comptes connectés (motifs fournis par l'API), traité par la modération.
  Future<void> _report(ListingDetail d) async {
    final messenger = ScaffoldMessenger.of(context);
    if (!widget.signedIn) {
      messenger.showSnackBar(
        const SnackBar(
          content: Text(
            'Connectez-vous (onglet Compte) pour signaler une annonce.',
          ),
        ),
      );
      return;
    }
    List<({String code, String label})> reasons;
    try {
      reasons = await widget.repository.reportReasons();
    } catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(errorText(e))));
      return;
    }
    if (!mounted) return;
    String? chosen;
    final comment = TextEditingController();
    final send = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setState) => AlertDialog(
          title: const Text('Signaler cette annonce'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                RadioGroup<String>(
                  groupValue: chosen,
                  onChanged: (v) => setState(() => chosen = v),
                  child: Column(
                    children: [
                      for (final r in reasons)
                        RadioListTile<String>(
                          contentPadding: EdgeInsets.zero,
                          title: Text(r.label),
                          value: r.code,
                        ),
                    ],
                  ),
                ),
                TextField(
                  controller: comment,
                  maxLines: 3,
                  maxLength: 1000,
                  decoration: const InputDecoration(
                    labelText: 'Précisions (facultatif)',
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Annuler'),
            ),
            FilledButton(
              onPressed: chosen == null ? null : () => Navigator.pop(ctx, true),
              child: const Text('Envoyer'),
            ),
          ],
        ),
      ),
    );
    final text = comment.text;
    comment.dispose();
    if (send != true || chosen == null) return;
    try {
      await widget.repository.reportListing(
        d.summary.id,
        chosen!,
        comment: text,
      );
      messenger.showSnackBar(
        const SnackBar(
          content: Text(
            'Merci : votre signalement a été transmis à notre équipe.',
          ),
        ),
      );
    } catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(errorText(e))));
    }
  }

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
          body: notFound
              ? const Center(
                  child: Text("Cette annonce n'est plus disponible."),
                )
              : ErrorRetry(
                  message: errorText(snap.error!),
                  onRetry: () => setState(() {
                    // `ignore` : un échec immédiat (avant la reconstruction) ne doit pas être signalé comme erreur non gérée.
                    _future = widget.repository.detail(widget.reference)
                      ..ignore();
                  }),
                ),
        );
      }
      final d = snap.data!;
      final s = d.summary;
      return Scaffold(
        bottomNavigationBar: SafeArea(
          child: Container(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
            decoration: const BoxDecoration(
              color: Colors.white,
              border: Border(top: BorderSide(color: AppColors.line)),
            ),
            child: Row(
              children: [
                Expanded(
                  flex: 2,
                  child: OutlinedButton.icon(
                    icon: const Icon(Icons.chat_bubble_outline),
                    label: const Text('Message'),
                    onPressed: () => _contact(d),
                  ),
                ),
                const SizedBox(width: 12),
                // La visite se propose dans la conversation : même point d'entrée que le message.
                Expanded(
                  flex: 3,
                  child: FilledButton.icon(
                    icon: const Icon(Icons.event_available_outlined),
                    label: const Text('Proposer une visite'),
                    onPressed: () => _contact(d),
                  ),
                ),
              ],
            ),
          ),
        ),
        body: ListView(
          children: [
            SizedBox(
              height: 300,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  if (d.imageUrls.isNotEmpty)
                    PageView(
                      controller: _pages,
                      onPageChanged: (i) => setState(() => _page = i),
                      children: [
                        for (final u in d.imageUrls) CoverImage(url: u),
                      ],
                    )
                  else
                    Container(
                      color: AppColors.sandDeep,
                      child: const Icon(
                        Icons.home_work_outlined,
                        size: 56,
                        color: AppColors.primary,
                      ),
                    ),
                  SafeArea(
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _RoundButton(
                            tooltip: 'Retour',
                            icon: Icons.arrow_back,
                            onTap: () => Navigator.of(context).maybePop(),
                          ),
                          const Spacer(),
                          _RoundButton(
                            tooltip: (_favorite ?? false)
                                ? 'Retirer des favoris'
                                : 'Ajouter aux favoris',
                            icon: (_favorite ?? false)
                                ? Icons.favorite
                                : Icons.favorite_border,
                            color: (_favorite ?? false)
                                ? AppColors.primary
                                : AppColors.ink,
                            onTap: () => _toggleFavorite(d),
                          ),
                        ],
                      ),
                    ),
                  ),
                  if (d.imageUrls.isNotEmpty)
                    Positioned(
                      left: 14,
                      bottom: 12,
                      child: PhotoCount(d.imageUrls.length),
                    ),
                  if (d.imageUrls.length > 1)
                    Positioned(
                      right: 14,
                      bottom: 12,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.black.withValues(alpha: 0.65),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text(
                          '${_page + 1} / ${d.imageUrls.length}',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            if (d.imageUrls.length > 1)
              SizedBox(
                height: 72,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
                  itemCount: d.imageUrls.length.clamp(0, 6),
                  separatorBuilder: (_, _) => const SizedBox(width: 8),
                  itemBuilder: (_, i) => GestureDetector(
                    onTap: () => _pages.animateToPage(
                      i,
                      duration: const Duration(milliseconds: 250),
                      curve: Curves.easeOut,
                    ),
                    child: Container(
                      width: 84,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(AppRadius.sm),
                        border: Border.all(
                          color: i == _page
                              ? AppColors.primary
                              : Colors.transparent,
                          width: 2,
                        ),
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(AppRadius.sm - 2),
                        child: CoverImage(url: d.imageUrls[i]),
                      ),
                    ),
                  ),
                ),
              ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    s.title ?? s.reference,
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  if (s.locality != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Row(
                        children: [
                          const Icon(
                            Icons.location_on_outlined,
                            size: 16,
                            color: AppColors.muted,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            s.locality!,
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                      ),
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
                  const SizedBox(height: 12),
                  _DetailTabs(
                    current: _tab,
                    onSelect: (i) => setState(() => _tab = i),
                  ),
                  if (_tab == 0 && d.description != null) ...[
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
                  if (_tab == 1 && d.amenities.isEmpty)
                    const Padding(
                      padding: EdgeInsets.only(top: 16),
                      child: Text(
                        'Aucun équipement renseigné.',
                        style: TextStyle(color: AppColors.muted),
                      ),
                    ),
                  if (_tab == 1 && d.amenities.isNotEmpty) ...[
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
                  if (_tab == 0 && d.costs.values.any((v) => v != null))
                    _Costs(detail: d),
                  _PassportCard(detail: d),
                  if (_tab == 2 && d.location != null) ...[
                    const SizedBox(height: 24),
                    Text(
                      'Localisation',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 10),
                    ZoneMap(
                      lat: d.location!.lat,
                      lon: d.location!.lon,
                      exact: d.location!.exact,
                    ),
                    const SizedBox(height: 6),
                    Text(
                      d.location!.exact ? "Position indiquée par l'annonceur." : "Zone approximative : l'adresse exacte est communiquée par l'annonceur lors de votre échange.",
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
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
                  Center(
                    child: TextButton(
                      onPressed: () => _report(d),
                      child: const Text(
                        'Signaler cette annonce',
                        style: TextStyle(color: AppColors.muted, fontSize: 13),
                      ),
                    ),
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

class _KeyFacts extends StatelessWidget {
  const _KeyFacts({required this.summary});

  final ListingSummary summary;

  @override
  Widget build(BuildContext context) {
    final items = <(String, String)>[
      if (summary.propertyType != null) (summary.propertyType!, 'Type'),
      if (summary.bedrooms != null) ('${summary.bedrooms}', 'Chambres'),
      if (summary.bathrooms != null) ('${summary.bathrooms}', 'Salles de bain'),
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
              flex: label == 'Type' ? 2 : 1,
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
                border: Border(bottom: BorderSide(color: Color(0xFFEBD9B8))),
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

/// Onglets de la fiche : Description, Équipements, Localisation (soulignement orange sur l'actif).
class _DetailTabs extends StatelessWidget {
  const _DetailTabs({required this.current, required this.onSelect});

  final int current;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      for (final (i, label) in const [
        'Description',
        'Équipements',
        'Localisation',
      ].indexed)
        Expanded(
          child: InkWell(
            onTap: () => onSelect(i),
            child: Container(
              padding: const EdgeInsets.symmetric(vertical: 12),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                border: Border(
                  bottom: BorderSide(
                    color: i == current ? AppColors.primary : AppColors.line,
                    width: i == current ? 3 : 1,
                  ),
                ),
              ),
              child: Text(
                label,
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  color: i == current ? AppColors.primary : AppColors.muted,
                ),
              ),
            ),
          ),
        ),
    ],
  );
}

/// Carte résumée du Passeport : trois contrôles datés et accès au détail.
class _PassportCard extends StatelessWidget {
  const _PassportCard({required this.detail});

  final ListingDetail detail;

  String _date(DateTime d) =>
      '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')}/${d.year}';

  @override
  Widget build(BuildContext context) {
    final shown = detail.checks.take(3).toList();
    return Container(
      margin: const EdgeInsets.only(top: 24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: const Color(0xFFF2C9A8)),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadius.md),
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => PassportScreen(detail: detail),
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Row(
                children: [
                  Icon(Icons.verified_user_outlined, color: AppColors.primary),
                  SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Passeport du Bien',
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 17,
                      ),
                    ),
                  ),
                  Icon(Icons.chevron_right),
                ],
              ),
              const SizedBox(height: 12),
              if (shown.isEmpty)
                const Text(
                  'Aucun contrôle effectué pour le moment.',
                  style: TextStyle(color: AppColors.muted),
                )
              else
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    for (final c in shown)
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Icon(
                                c.status == 'passed'
                                    ? Icons.check_circle_outline
                                    : Icons.remove_circle_outline,
                                size: 22,
                                color: c.status == 'passed'
                                    ? AppColors.success
                                    : AppColors.muted,
                              ),
                              const SizedBox(height: 6),
                              Text(
                                checkLabels[c.type] ?? c.type,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                  fontSize: 12,
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
                      ),
                  ],
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RoundButton extends StatelessWidget {
  const _RoundButton({
    required this.tooltip,
    required this.icon,
    required this.onTap,
    this.color = AppColors.ink,
  });

  final String tooltip;
  final IconData icon;
  final VoidCallback onTap;
  final Color color;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    shape: const CircleBorder(),
    elevation: 2,
    child: IconButton(
      tooltip: tooltip,
      icon: Icon(icon, color: color),
      onPressed: onTap,
    ),
  );
}
