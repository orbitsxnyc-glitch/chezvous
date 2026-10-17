import 'package:flutter/material.dart';

import '../core/format.dart';
import '../ui/theme.dart';
import 'models.dart';

/// Écran du Passeport du Bien : « Contrôles effectués » (pas de note globale) et rappel de la portée juridique.
class PassportScreen extends StatelessWidget {
  const PassportScreen({super.key, required this.detail});

  final ListingDetail detail;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Passeport du Bien')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          'Contrôles effectués',
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: 4),
        Text(
          'Chaque contrôle est daté : un contrôle ancien ou échoué ne se présente jamais comme valide.',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        _PassportBody(detail: detail),
      ],
    ),
  );
}

/// Passeport du Bien : contrôles explicites et datés, jamais de note globale ni de garantie juridique.
class _PassportBody extends StatelessWidget {
  const _PassportBody({required this.detail});

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
        colors: [Color(0xFFFFF7F0), Colors.white],
      ),
      borderRadius: BorderRadius.circular(AppRadius.md),
      border: Border.all(color: const Color(0xFFF2C9A8)),
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
              border: Border.all(color: const Color(0xFFF0DED0)),
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 14,
                  backgroundColor: c.status == 'passed'
                      ? AppColors.successSoft
                      : const Color(0xFFF1ECE6),
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
