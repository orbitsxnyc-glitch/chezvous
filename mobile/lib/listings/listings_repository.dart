import '../core/api_client.dart';
import 'models.dart';

class ListingsRepository {
  const ListingsRepository(this.api);

  final ApiClient api;

  List<Map<String, dynamic>> _list(Object? raw) =>
      (raw as List? ?? const []).whereType<Map<String, dynamic>>().toList();

  Future<SearchPage> search(SearchFilters filters, {String? cursor}) async {
    final res = await api.get(
      '/listings',
      query: {...filters.toParams(), 'cursor': ?cursor},
    );
    final meta = res['meta'];
    return SearchPage(
      _list(res['data']).map(ListingSummary.fromJson).toList(),
      meta is Map ? meta['next_cursor'] as String? : null,
      meta is Map ? (meta['total'] as num?)?.toInt() : null,
    );
  }

  /// Points de la zone visible (« ouest,sud,est,nord ») avec les mêmes filtres que la liste.
  Future<MapResult> mapPoints(
    String bbox,
    SearchFilters filters, {
    int? zoom,
  }) async {
    final res = await api.get(
      '/listings/map',
      query: {
        ...filters.toParams(),
        'bbox': bbox,
        if (zoom != null) 'zoom': '$zoom',
      },
    );
    final meta = res['meta'];
    final rows = _list(res['data']);
    // Zoom faible : l'API renvoie des groupes ; zoom élevé : des annonces une à une.
    return MapResult(
      rows.where((r) => r['cluster'] != true).map(MapPoint.fromJson).toList(),
      rows.where((r) => r['cluster'] == true).map(MapCluster.fromJson).toList(),
      meta is Map && meta['truncated'] == true,
    );
  }

  /// Motifs de signalement (publics).
  Future<List<({String code, String label})>> reportReasons() async =>
      _list((await api.get('/report-reasons'))['data'])
          .map((r) => (code: '${r['code']}', label: '${r['label']}'))
          .toList();

  /// Signale une annonce publiée (compte connecté requis) ; traité par la modération.
  Future<void> reportListing(
    String listingId,
    String reasonCode, {
    String? comment,
  }) => api.post('/listings/${Uri.encodeComponent(listingId)}/reports', {
    'reason_code': reasonCode,
    if (comment != null && comment.trim().isNotEmpty) 'comment': comment.trim(),
  });

  Future<ListingDetail> detail(String reference) async {
    final res = await api.get('/listings/${Uri.encodeComponent(reference)}');
    return ListingDetail.fromJson(res['data'] as Map<String, dynamic>);
  }

  /// Annonces « À la une » : mises en avant par leurs annonceurs (à étiqueter comme telles).
  Future<List<ListingSummary>> featured() async {
    final res = await api.get('/featured');
    return _list(res['data']).map(ListingSummary.fromJson).toList();
  }

  Future<List<Locality>> neighborhoods({bool withCounts = false}) async {
    final res = await api.get(
      '/localities',
      query: {'level': 'neighborhood', if (withCounts) 'with_counts': '1'},
    );
    return _list(res['data'])
        .map(
          (l) => Locality(
            '${l['slug']}',
            '${l['name']}',
            (l['listings_count'] as num?)?.toInt(),
          ),
        )
        .toList();
  }

  Future<List<PropertyKind>> propertyTypes() async {
    final res = await api.get('/property-types');
    return _list(res['data'])
        .map(
          (t) => PropertyKind(
            (t['id'] as num).toInt(),
            '${t['code']}',
            '${t['label']}',
          ),
        )
        .toList();
  }

  // Favoris et recherches enregistrées : compte connecté requis.
  Future<List<ListingSummary>> favorites() async {
    final res = await api.get('/my/favorites');
    return _list(res['data'])
        .map(
          (f) => ListingSummary.fromJson(f['listing'] as Map<String, dynamic>),
        )
        .toList();
  }

  Future<void> addFavorite(String listingId) =>
      api.post('/my/favorites', {'listing': listingId});

  Future<void> removeFavorite(String listingId) =>
      api.delete('/my/favorites/${Uri.encodeComponent(listingId)}');

  Future<List<SavedSearch>> savedSearches() async {
    final res = await api.get('/my/saved-searches');
    return _list(res['data']).map(SavedSearch.fromJson).toList();
  }

  Future<void> saveSearch(
    String name,
    SearchFilters filters, {
    String frequency = 'daily',
  }) => api.post('/my/saved-searches', {
    'name': name,
    'criteria': filters.toParams(),
    'frequency': frequency,
  });

  Future<void> deleteSavedSearch(String id) =>
      api.delete('/my/saved-searches/$id');
}

class SavedSearch {
  const SavedSearch(this.id, this.name, this.frequency, this.active);

  factory SavedSearch.fromJson(Map<String, dynamic> j) => SavedSearch(
    '${j['id']}',
    '${j['name']}',
    '${j['frequency']}',
    j['is_active'] == true,
  );

  final String id;
  final String name;
  final String frequency;
  final bool active;
}
