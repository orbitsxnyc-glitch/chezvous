import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../core/format.dart';
import '../ui/theme.dart';
import 'listing_widgets.dart';
import 'listings_repository.dart';
import 'models.dart';

/// Fonds de carte : OpenStreetMap par défaut (gratuit, sans compte). À remplacer par un fournisseur dédié au lancement public :
/// `--dart-define=MAP_TILES_URL=https://.../{z}/{x}/{y}.png`.
const mapTilesUrl = String.fromEnvironment(
  'MAP_TILES_URL',
  defaultValue: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
);
const mapUserAgent = 'sn.chezvous.chezvous';

/// Dakar : centre de départ.
const dakar = LatLng(14.7167, -17.4677);

/// Carte des annonces : charge les points visibles à chaque déplacement ; toucher une pastille ouvre un aperçu.
class ListingsMapView extends StatefulWidget {
  const ListingsMapView({
    super.key,
    required this.repository,
    required this.filters,
    required this.onOpen,
    this.showTiles = true,
  });

  final ListingsRepository repository;
  final SearchFilters filters;
  final ValueChanged<MapPoint> onOpen;

  /// Faux en test : aucun appel réseau pour les tuiles.
  final bool showTiles;

  @override
  State<ListingsMapView> createState() => _ListingsMapViewState();
}

class _ListingsMapViewState extends State<ListingsMapView> {
  final _controller = MapController();
  List<MapPoint> _points = const [];
  List<MapCluster> _clusters = const [];
  MapPoint? _selected;
  bool _truncated = false;
  bool _loading = true;
  String? _error;
  Timer? _debounce;
  int _generation = 0;

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  void didUpdateWidget(covariant ListingsMapView old) {
    super.didUpdateWidget(old);
    if (old.filters.toParams().toString() !=
        widget.filters.toParams().toString()) {
      _load();
    }
  }

  Future<void> _load() async {
    final b = _controller.camera.visibleBounds;
    final generation = ++_generation;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final r = await widget.repository.mapPoints(
        bboxParam(b.west, b.south, b.east, b.north),
        widget.filters,
      );
      // Une réponse tardive d'une zone précédente ne doit jamais écraser la zone affichée.
      if (!mounted || generation != _generation) return;
      setState(() {
        _points = r.points;
        _clusters = r.clusters;
        _truncated = r.truncated;
        _selected = _selected == null
            ? null
            : r.points.where((p) => p.id == _selected!.id).firstOrNull;
      });
    } catch (_) {
      if (mounted && generation == _generation) {
        setState(
          () =>
              _error = 'Carte indisponible. Déplacez la carte pour réessayer.',
        );
      }
    }
    if (mounted && generation == _generation) setState(() => _loading = false);
  }

  String _summary() {
    final total =
        _points.length + _clusters.fold<int>(0, (sum, c) => sum + c.count);
    return '$total bien${total > 1 ? 's' : ''} dans cette zone${_truncated ? ' (zoomez pour voir les autres)' : ''}';
  }

  void _scheduleLoad() {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), _load);
  }

  @override
  Widget build(BuildContext context) {
    final status = _loading ? 'Chargement…' : _error ?? _summary();
    return Stack(
      children: [
        FlutterMap(
          mapController: _controller,
          options: MapOptions(
            initialCenter: dakar,
            initialZoom: 12,
            minZoom: 5,
            maxZoom: 18,
            onMapReady: _load,
            onMapEvent: (e) {
              if (e is MapEventMoveEnd ||
                  e is MapEventFlingAnimationEnd ||
                  e is MapEventDoubleTapZoomEnd ||
                  e is MapEventScrollWheelZoom) {
                _scheduleLoad();
              }
            },
            onTap: (_, _) => setState(() => _selected = null),
          ),
          children: [
            if (widget.showTiles)
              TileLayer(
                urlTemplate: mapTilesUrl,
                userAgentPackageName: mapUserAgent,
                maxZoom: 18,
              ),
            MarkerLayer(
              markers: [
                for (final c in _clusters)
                  Marker(
                    point: LatLng(c.lat, c.lon),
                    width: 44,
                    height: 44,
                    child: Semantics(
                      button: true,
                      label: '${c.count} biens, zoomer pour les voir',
                      child: GestureDetector(
                        onTap: () => _controller.move(
                          LatLng(c.lat, c.lon),
                          (_controller.camera.zoom + 2).clamp(5, 17),
                        ),
                        child: _ClusterBubble(count: c.count),
                      ),
                    ),
                  ),
                for (final p in _points)
                  Marker(
                    point: LatLng(p.lat, p.lon),
                    width: 64,
                    height: 32,
                    child: Semantics(
                      button: true,
                      label:
                          '${p.title ?? 'Annonce'}, ${formatPrice(p.priceXof, p.transaction)}',
                      child: GestureDetector(
                        onTap: () => setState(() => _selected = p),
                        child: _Pin(
                          label: compactPrice(p.priceXof),
                          selected: _selected?.id == p.id,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
            const RichAttributionWidget(
              alignment: AttributionAlignment.bottomLeft,
              attributions: [
                TextSourceAttribution('© contributeurs OpenStreetMap'),
              ],
            ),
          ],
        ),
        Positioned(
          top: 10,
          left: 0,
          right: 0,
          child: Center(
            child: Material(
              elevation: 2,
              borderRadius: BorderRadius.circular(20),
              color: Colors.white,
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 6,
                ),
                child: Text(
                  status,
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ),
        ),
        if (_selected != null)
          Positioned(
            left: 12,
            right: 12,
            bottom: 28,
            child: _PreviewCard(
              point: _selected!,
              onOpen: () => widget.onOpen(_selected!),
            ),
          ),
      ],
    );
  }
}

class _ClusterBubble extends StatelessWidget {
  const _ClusterBubble({required this.count});

  final int count;

  @override
  Widget build(BuildContext context) => Container(
    alignment: Alignment.center,
    decoration: BoxDecoration(
      color: AppColors.primary,
      shape: BoxShape.circle,
      border: Border.all(color: Colors.white, width: 3),
      boxShadow: const [
        BoxShadow(color: Color(0x47C2410C), blurRadius: 0, spreadRadius: 4),
        BoxShadow(
          color: Color(0x59000000),
          blurRadius: 6,
          offset: Offset(0, 2),
        ),
      ],
    ),
    child: Text(
      count > 999 ? '999+' : '$count',
      style: const TextStyle(
        color: Colors.white,
        fontSize: 13,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _Pin extends StatelessWidget {
  const _Pin({required this.label, required this.selected});

  final String label;
  final bool selected;

  @override
  Widget build(BuildContext context) => Container(
    alignment: Alignment.center,
    decoration: BoxDecoration(
      color: selected ? AppColors.primaryHover : AppColors.primary,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: Colors.white, width: 2),
      boxShadow: const [
        BoxShadow(
          color: Color(0x59000000),
          blurRadius: 6,
          offset: Offset(0, 2),
        ),
      ],
    ),
    child: Text(
      label,
      style: const TextStyle(
        color: Colors.white,
        fontSize: 12,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _PreviewCard extends StatelessWidget {
  const _PreviewCard({required this.point, required this.onOpen});

  final MapPoint point;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final p = point;
    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      elevation: 4,
      child: InkWell(
        onTap: onOpen,
        child: SizedBox(
          height: 96,
          child: Row(
            children: [
              SizedBox(width: 96, height: 96, child: CoverImage(url: p.cover)),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        p.title ?? 'Annonce',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        formatPrice(p.priceXof, p.transaction),
                        style: const TextStyle(
                          color: AppColors.primary,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        [
                          p.typeLabel,
                          p.locality,
                        ].whereType<String>().join(' · '),
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
              ),
              const Padding(
                padding: EdgeInsets.only(right: 8),
                child: Icon(Icons.chevron_right),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Mini-carte d'une fiche : zone approximative (cercle) sauf position exacte choisie par l'annonceur.
class ZoneMap extends StatelessWidget {
  const ZoneMap({
    super.key,
    required this.lat,
    required this.lon,
    required this.exact,
  });

  final double lat;
  final double lon;
  final bool exact;

  @override
  Widget build(BuildContext context) {
    final point = LatLng(lat, lon);
    return ClipRRect(
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: SizedBox(
        height: 200,
        child: FlutterMap(
          options: MapOptions(
            initialCenter: point,
            initialZoom: exact ? 16 : 14,
            interactionOptions: const InteractionOptions(
              flags: InteractiveFlag.none,
            ),
          ),
          children: [
            TileLayer(
              urlTemplate: mapTilesUrl,
              userAgentPackageName: mapUserAgent,
              maxZoom: 18,
            ),
            if (exact)
              MarkerLayer(
                markers: [
                  Marker(
                    point: point,
                    width: 22,
                    height: 22,
                    child: Container(
                      decoration: BoxDecoration(
                        color: AppColors.primary,
                        shape: BoxShape.circle,
                        border: Border.all(color: Colors.white, width: 3),
                      ),
                    ),
                  ),
                ],
              )
            else
              CircleLayer(
                circles: [
                  CircleMarker(
                    point: point,
                    radius: 350,
                    useRadiusInMeter: true,
                    color: AppColors.primary.withValues(alpha: 0.18),
                    borderColor: AppColors.primary,
                    borderStrokeWidth: 2,
                  ),
                ],
              ),
            const RichAttributionWidget(
              alignment: AttributionAlignment.bottomLeft,
              attributions: [
                TextSourceAttribution('© contributeurs OpenStreetMap'),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
