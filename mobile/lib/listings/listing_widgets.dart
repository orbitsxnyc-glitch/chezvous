import 'package:flutter/material.dart';

import '../core/format.dart';
import '../ui/theme.dart';
import 'models.dart';

/// Carte d'annonce horizontale du design : photo à gauche, prix en vert, badge de fraîcheur daté.
class ListingCard extends StatelessWidget {
  const ListingCard({
    super.key,
    required this.listing,
    required this.onTap,
    this.trailing,
  });

  final ListingSummary listing;
  final VoidCallback onTap;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final l = listing;
    final text = Theme.of(context).textTheme;
    final facts = [
      if (l.propertyType != null) l.propertyType!,
      if (l.bedrooms != null) '${l.bedrooms} ch.',
      if (l.surfaceM2 != null) '${l.surfaceM2} m²',
      if (l.furnished) 'Meublé',
    ].join(' · ');
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SizedBox(width: 118, child: CoverImage(url: l.coverUrl)),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (l.locality != null)
                        Text(
                          l.locality!,
                          style: text.bodySmall?.copyWith(
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      const SizedBox(height: 3),
                      Text(
                        l.title ?? l.reference,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: text.titleMedium?.copyWith(fontSize: 15),
                      ),
                      if (facts.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(facts, style: text.bodySmall),
                      ],
                      const SizedBox(height: 8),
                      Text(
                        formatPrice(l.priceXof, l.transaction) +
                            (l.negotiable ? ' · négociable' : ''),
                        style: const TextStyle(
                          color: AppColors.primary,
                          fontWeight: FontWeight.w800,
                          fontSize: 15,
                        ),
                      ),
                      const SizedBox(height: 8),
                      FreshnessBadge(availability: l.availability),
                    ],
                  ),
                ),
              ),
              ?trailing,
            ],
          ),
        ),
      ),
    );
  }
}

class CoverImage extends StatelessWidget {
  const CoverImage({super.key, required this.url, this.fit = BoxFit.cover});

  final String? url;
  final BoxFit fit;

  @override
  Widget build(BuildContext context) {
    final placeholder = const ColoredBox(
      color: AppColors.sandDeep,
      child: Center(child: Icon(Icons.home_outlined, color: AppColors.muted)),
    );
    if (url == null) return placeholder;
    return Image.network(
      url!,
      fit: fit,
      errorBuilder: (_, _, _) => placeholder,
    );
  }
}

/// Fraîcheur toujours datée : vert (disponible), ambre (réservé, à reconfirmer), gris (information ancienne).
class FreshnessBadge extends StatelessWidget {
  const FreshnessBadge({super.key, required this.availability});

  final Availability availability;

  @override
  Widget build(BuildContext context) {
    final (tone, icon) = switch (availability.state) {
      'available' => (BadgeTone.success, Icons.check_circle_outline),
      'reserved' => (BadgeTone.warning, Icons.bookmark_border),
      'to_reconfirm' => (BadgeTone.warning, Icons.schedule),
      _ => (BadgeTone.neutral, Icons.history),
    };
    return AppBadge(
      freshnessLabel(availability.state, availability.confirmedAt),
      tone: tone,
      icon: icon,
    );
  }
}
