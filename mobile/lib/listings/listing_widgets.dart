import 'package:flutter/material.dart';

import '../core/format.dart';
import 'models.dart';

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
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: 110,
              height: 110,
              child: CoverImage(url: l.coverUrl),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      formatPrice(l.priceXof, l.transaction) +
                          (l.negotiable ? ' · négociable' : ''),
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    Text(
                      l.title ?? l.reference,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (l.locality != null)
                      Text(
                        l.locality!,
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    if (facts.isNotEmpty)
                      Text(facts, style: Theme.of(context).textTheme.bodySmall),
                    const SizedBox(height: 4),
                    FreshnessBadge(availability: l.availability),
                  ],
                ),
              ),
            ),
            ?trailing,
          ],
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
    final placeholder = ColoredBox(
      color: Colors.grey.shade300,
      child: const Icon(Icons.home_outlined),
    );
    if (url == null) return placeholder;
    return Image.network(
      url!,
      fit: fit,
      errorBuilder: (_, _, _) => placeholder,
    );
  }
}

class FreshnessBadge extends StatelessWidget {
  const FreshnessBadge({super.key, required this.availability});

  final Availability availability;

  @override
  Widget build(BuildContext context) {
    final good = availability.state == 'available';
    return Text(
      freshnessLabel(availability.state, availability.confirmedAt),
      style: TextStyle(
        fontSize: 12,
        color: good ? Colors.green.shade800 : Colors.orange.shade900,
      ),
    );
  }
}
