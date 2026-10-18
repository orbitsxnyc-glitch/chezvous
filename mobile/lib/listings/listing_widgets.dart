import 'package:flutter/material.dart';

import '../core/format.dart';
import '../ui/theme.dart';
import 'listings_repository.dart';
import 'models.dart';

/// Cœur favori : réservé aux comptes connectés ; l'état vient du cache de session du dépôt.
class FavoriteHeart extends StatefulWidget {
  const FavoriteHeart({
    super.key,
    required this.repository,
    required this.listingId,
    required this.signedIn,
    this.onNeedSignIn,
  });

  final ListingsRepository repository;
  final String listingId;
  final bool signedIn;
  final VoidCallback? onNeedSignIn;

  @override
  State<FavoriteHeart> createState() => _FavoriteHeartState();
}

class _FavoriteHeartState extends State<FavoriteHeart> {
  bool get _on => widget.repository.favoriteIds.contains(widget.listingId);

  @override
  void initState() {
    super.initState();
    if (widget.signedIn) {
      widget.repository
          .ensureFavoriteIds()
          .then((_) {
            if (mounted) setState(() {});
          })
          .catchError((_) {
            // Sans cache, le cœur reste vide : le détail de l'annonce donne l'état réel.
          });
    }
  }

  Future<void> _toggle() async {
    if (!widget.signedIn) {
      widget.onNeedSignIn?.call();
      return;
    }
    try {
      _on
          ? await widget.repository.removeFavorite(widget.listingId)
          : await widget.repository.addFavorite(widget.listingId);
      if (mounted) setState(() {});
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text("Impossible de modifier le favori pour l'instant."),
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) => IconButton(
    tooltip: _on ? 'Retirer des favoris' : 'Ajouter aux favoris',
    onPressed: _toggle,
    icon: Icon(
      _on ? Icons.favorite : Icons.favorite_border,
      color: _on ? AppColors.primary : AppColors.ink,
    ),
  );
}

/// Pastille « 12 photos » posée sur l'image.
class PhotoCount extends StatelessWidget {
  const PhotoCount(this.count, {super.key});

  final int count;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: Colors.black.withValues(alpha: 0.62),
      borderRadius: BorderRadius.circular(10),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.photo_camera_outlined, size: 13, color: Colors.white),
        const SizedBox(width: 4),
        Text(
          '$count photo${count > 1 ? 's' : ''}',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 11,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    ),
  );
}

/// Ligne de caractéristiques : chambres, salles de bain, surface (uniquement celles qui sont renseignées).
class ListingStats extends StatelessWidget {
  const ListingStats(this.listing, {super.key, this.compact = false});

  final ListingSummary listing;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final l = listing;
    final items = <(IconData, String)>[
      if (l.bedrooms != null)
        (Icons.bed_outlined, '${l.bedrooms}${compact ? '' : ' ch.'}'),
      if (l.bathrooms != null)
        (Icons.bathtub_outlined, '${l.bathrooms}${compact ? '' : ' sdb'}'),
      if (l.surfaceM2 != null) (Icons.square_foot, '${l.surfaceM2} m²'),
    ];
    if (items.isEmpty) return const SizedBox.shrink();
    return Wrap(
      spacing: compact ? 10 : 14,
      runSpacing: 4,
      children: [
        for (final (icon, label) in items)
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 16, color: AppColors.muted),
              const SizedBox(width: 4),
              Text(
                label,
                style: const TextStyle(fontSize: 12, color: AppColors.muted),
              ),
            ],
          ),
      ],
    );
  }
}

/// Prix du design : montant en orange gras, périodicité en gris (« / mois »).
class PriceText extends StatelessWidget {
  const PriceText(this.listing, {super.key, this.size = 18});

  final ListingSummary listing;
  final double size;

  @override
  Widget build(BuildContext context) {
    final full = formatPrice(listing.priceXof, listing.transaction);
    final i = full.indexOf(' / ');
    final amount = i < 0 ? full : full.substring(0, i);
    final period = i < 0 ? '' : full.substring(i);
    return Text.rich(
      TextSpan(
        text: amount,
        style: TextStyle(
          color: AppColors.primary,
          fontWeight: FontWeight.w800,
          fontSize: size,
        ),
        children: [
          TextSpan(
            text: period,
            style: TextStyle(
              color: AppColors.muted,
              fontWeight: FontWeight.w500,
              fontSize: size * 0.75,
            ),
          ),
        ],
      ),
    );
  }
}

/// Carte d'annonce horizontale du design : photo à gauche, badge de fraîcheur daté, prix, caractéristiques.
class ListingCard extends StatelessWidget {
  const ListingCard({
    super.key,
    required this.listing,
    required this.onTap,
    this.repository,
    this.signedIn = false,
    this.onNeedSignIn,
    this.trailing,
  });

  final ListingSummary listing;
  final VoidCallback onTap;
  final ListingsRepository? repository;
  final bool signedIn;
  final VoidCallback? onNeedSignIn;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final l = listing;
    final text = Theme.of(context).textTheme;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SizedBox(
                width: 132,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    CoverImage(url: l.coverUrl),
                    if ((l.photosCount ?? 0) > 0)
                      Positioned(
                        left: 8,
                        bottom: 8,
                        child: PhotoCount(l.photosCount!),
                      ),
                  ],
                ),
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(12, 12, 6, 12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: FreshnessBadge(availability: l.availability),
                          ),
                          if (repository != null)
                            SizedBox(
                              width: 36,
                              height: 24,
                              child: FittedBox(
                                child: FavoriteHeart(
                                  repository: repository!,
                                  listingId: l.id,
                                  signedIn: signedIn,
                                  onNeedSignIn: onNeedSignIn,
                                ),
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(
                        l.title ?? l.reference,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: text.titleMedium?.copyWith(fontSize: 15),
                      ),
                      if (l.locality != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 3),
                          child: Row(
                            children: [
                              const Icon(
                                Icons.location_on,
                                size: 14,
                                color: AppColors.primary,
                              ),
                              const SizedBox(width: 3),
                              Expanded(
                                child: Text(
                                  '${l.locality}, Dakar',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: text.bodySmall,
                                ),
                              ),
                            ],
                          ),
                        ),
                      const SizedBox(height: 8),
                      PriceText(l),
                      if (l.negotiable)
                        Text('Prix négociable', style: text.bodySmall),
                      const SizedBox(height: 8),
                      ListingStats(l),
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

/// Vignette verticale compacte (« Biens à la une » de l'accueil).
class ListingTile extends StatelessWidget {
  const ListingTile({
    super.key,
    required this.listing,
    required this.onTap,
    required this.repository,
    this.signedIn = false,
    this.onNeedSignIn,
  });

  final ListingSummary listing;
  final VoidCallback onTap;
  final ListingsRepository repository;
  final bool signedIn;
  final VoidCallback? onNeedSignIn;

  @override
  Widget build(BuildContext context) {
    final l = listing;
    return Card(
      clipBehavior: Clip.antiAlias,
      margin: EdgeInsets.zero,
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AspectRatio(
              aspectRatio: 1.5,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  CoverImage(url: l.coverUrl),
                  Positioned(
                    top: 4,
                    right: 4,
                    child: CircleAvatar(
                      radius: 17,
                      backgroundColor: Colors.white,
                      child: FittedBox(
                        child: FavoriteHeart(
                          repository: repository,
                          listingId: l.id,
                          signedIn: signedIn,
                          onNeedSignIn: onNeedSignIn,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    l.title ?? l.reference,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 14,
                    ),
                  ),
                  if (l.locality != null)
                    Text(
                      '${l.locality}, Dakar',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  const SizedBox(height: 6),
                  ListingStats(l, compact: true),
                  const SizedBox(height: 6),
                  PriceText(l, size: 14),
                  const SizedBox(height: 6),
                  FreshnessBadge(availability: l.availability),
                ],
              ),
            ),
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
