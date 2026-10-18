import 'package:flutter/material.dart';

import '../core/format.dart';
import '../ui/theme.dart';
import '../ui/widgets.dart';
import 'listing_widgets.dart';
import 'models.dart';

const _checkHints = {
  'phone': 'Numéro de téléphone de l\'annonceur confirmé par code',
  'identity': 'Identité de l\'annonceur contrôlée par notre équipe',
  'professional': 'Statut professionnel de l\'annonceur contrôlé',
  'location': 'Position du bien contrôlée',
  'document': 'Document présenté par l\'annonceur examiné',
  'field_visit': 'Bien visité par notre équipe',
};

String _fullDate(DateTime d) {
  const months = [
    'janvier',
    'février',
    'mars',
    'avril',
    'mai',
    'juin',
    'juillet',
    'août',
    'septembre',
    'octobre',
    'novembre',
    'décembre',
  ];
  return '${d.day} ${months[d.month - 1]} ${d.year}';
}

/// Écran du Passeport du Bien : onglets Vérifications / Documents / Historique.
/// Aucune note globale : chaque contrôle est listé avec sa date, et un contrôle ne garantit pas la propriété juridique.
class PassportScreen extends StatefulWidget {
  const PassportScreen({super.key, required this.detail});

  final ListingDetail detail;

  @override
  State<PassportScreen> createState() => _PassportScreenState();
}

class _PassportScreenState extends State<PassportScreen> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    final d = widget.detail;
    final s = d.summary;
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
          children: [
            Row(
              children: [
                IconButton.outlined(
                  tooltip: 'Retour',
                  onPressed: () => Navigator.of(context).maybePop(),
                  icon: const Icon(Icons.arrow_back),
                ),
                const SizedBox(width: 12),
                const BrandLogo(size: 14),
              ],
            ),
            const SizedBox(height: 16),
            Text(
              'Passeport du Bien',
              style: Theme.of(context).textTheme.headlineSmall
                  ?.copyWith(fontSize: 28),
            ),
            const SizedBox(height: 4),
            Text(
              'Les contrôles effectués sur ce bien, avec leur date.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                for (final (i, label, icon) in const [
                  (0, 'Vérifications', Icons.verified_user_outlined),
                  (1, 'Documents', Icons.description_outlined),
                  (2, 'Historique', Icons.history),
                ])
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(right: 6),
                      child: ChoiceChip(
                        showCheckmark: false,
                        avatar: Icon(
                          icon,
                          size: 18,
                          color: i == _tab
                              ? AppColors.primary
                              : AppColors.muted,
                        ),
                        label: Text(label, overflow: TextOverflow.ellipsis),
                        selected: i == _tab,
                        onSelected: (_) => setState(() => _tab = i),
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 16),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Row(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(AppRadius.sm),
                      child: SizedBox(
                        width: 112,
                        height: 96,
                        child: CoverImage(
                          url: d.imageUrls.isEmpty ? null : d.imageUrls.first,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            s.title ?? s.reference,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 15,
                            ),
                          ),
                          if (s.locality != null)
                            Text(
                              '${s.locality}, Dakar',
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          const SizedBox(height: 6),
                          ListingStats(s, compact: true),
                          const SizedBox(height: 6),
                          PriceText(s, size: 16),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            if (_tab == 0) ..._verifications(d),
            if (_tab == 1) _documents(),
            if (_tab == 2) ..._history(d),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AppColors.sand,
                borderRadius: BorderRadius.circular(AppRadius.md),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(
                    Icons.shield_outlined,
                    color: AppColors.primary,
                    size: 28,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'À noter',
                          style: TextStyle(
                            color: AppColors.primary,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          d.passportNotice,
                          style: const TextStyle(fontSize: 13, height: 1.4),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  List<Widget> _verifications(ListingDetail d) {
    final passed = d.checks.where((c) => c.status == 'passed').length;
    return [
      if (d.checks.isNotEmpty)
        Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.successSoft,
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
          child: Row(
            children: [
              const CircleAvatar(
                backgroundColor: AppColors.success,
                child: Icon(Icons.check, color: Colors.white),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Contrôles effectués',
                      style: TextStyle(
                        color: Color(0xFF0B6240),
                        fontWeight: FontWeight.w800,
                        fontSize: 17,
                      ),
                    ),
                    Text(
                      '$passed contrôle${passed > 1 ? 's' : ''} réussi${passed > 1 ? 's' : ''} sur ${d.checks.length}, chacun daté.',
                      style: const TextStyle(fontSize: 13),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      if (d.checks.isEmpty)
        const Padding(
          padding: EdgeInsets.symmetric(vertical: 16),
          child: Text(
            'Aucun contrôle effectué pour le moment.',
            style: TextStyle(color: AppColors.muted),
          ),
        ),
      for (final c in d.checks) _CheckRow(check: c),
    ];
  }

  Widget _documents() => const Padding(
    padding: EdgeInsets.symmetric(vertical: 16),
    child: Text(
      'Les justificatifs (pièces d\'identité, titres, documents) restent privés et ne sont jamais affichés ici. Seuls le type de contrôle effectué et sa date sont publics.',
      style: TextStyle(color: AppColors.muted, height: 1.5),
    ),
  );

  List<Widget> _history(ListingDetail d) {
    final dated = d.checks.where((c) => c.performedAt != null).toList()
      ..sort((a, b) => b.performedAt!.compareTo(a.performedAt!));
    if (dated.isEmpty) {
      return const [
        Padding(
          padding: EdgeInsets.symmetric(vertical: 16),
          child: Text(
            'Aucun historique de contrôle.',
            style: TextStyle(color: AppColors.muted),
          ),
        ),
      ];
    }
    return [
      const SizedBox(height: 8),
      for (final c in dated)
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.history, color: AppColors.primary),
          title: Text(
            checkLabels[c.type] ?? c.type,
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
          subtitle: Text(_fullDate(c.performedAt!)),
        ),
    ];
  }
}

class _CheckRow extends StatelessWidget {
  const _CheckRow({required this.check});

  final PassportCheck check;

  @override
  Widget build(BuildContext context) {
    final ok = check.status == 'passed';
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            CircleAvatar(
              backgroundColor: ok
                  ? AppColors.successSoft
                  : const Color(0xFFF1ECE6),
              child: Icon(
                ok ? Icons.check : Icons.remove,
                color: ok ? AppColors.success : AppColors.muted,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    checkLabels[check.type] ?? check.type,
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 15,
                    ),
                  ),
                  Text(
                    _checkHints[check.type] ?? '',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
            if (check.performedAt != null)
              Text(
                _fullDate(check.performedAt!),
                style: Theme.of(context).textTheme.bodySmall,
              ),
          ],
        ),
      ),
    );
  }
}
