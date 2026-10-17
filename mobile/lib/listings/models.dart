Map<String, dynamic> _map(Object? v) =>
    v is Map<String, dynamic> ? v : const {};

DateTime? _date(Object? v) => v is String ? DateTime.tryParse(v) : null;

class Availability {
  const Availability(this.state, this.confirmedAt);

  factory Availability.fromJson(Object? json) {
    final m = _map(json);
    return Availability('${m['state'] ?? 'stale'}', _date(m['confirmed_at']));
  }

  final String state;
  final DateTime? confirmedAt;
}

class ListingSummary {
  const ListingSummary({
    required this.id,
    required this.reference,
    required this.transaction,
    required this.negotiable,
    required this.furnished,
    required this.availability,
    this.title,
    this.priceXof,
    this.propertyType,
    this.locality,
    this.surfaceM2,
    this.bedrooms,
    this.agencyName,
    this.agencyVerified = false,
    this.coverUrl,
  });

  factory ListingSummary.fromJson(Map<String, dynamic> j) {
    final cover = _map(j['cover']);
    final agency = j['agency'] is Map ? _map(j['agency']) : null;
    return ListingSummary(
      id: '${j['id']}',
      reference: '${j['reference']}',
      transaction: '${j['transaction'] ?? 'rent_long'}',
      title: j['title'] as String?,
      priceXof: (j['price_xof'] as num?)?.toInt(),
      negotiable: j['negotiable'] == true,
      propertyType: (j['property_type_label'] ?? j['property_type']) as String?,
      locality: _map(j['locality'])['name'] as String?,
      surfaceM2: (j['surface_m2'] as num?)?.toInt(),
      bedrooms: (j['bedrooms'] as num?)?.toInt(),
      furnished: j['furnished'] == true,
      agencyName: agency?['name'] as String?,
      agencyVerified: agency?['verified'] == true,
      coverUrl:
          (cover['medium'] ?? cover['thumb'] ?? cover['large']) as String?,
      availability: Availability.fromJson(j['availability']),
    );
  }

  final String id;
  final String reference;
  final String transaction;
  final String? title;
  final int? priceXof;
  final bool negotiable;
  final String? propertyType;
  final String? locality;
  final int? surfaceM2;
  final int? bedrooms;
  final bool furnished;
  final String? agencyName;
  final bool agencyVerified;
  final String? coverUrl;
  final Availability availability;
}

class PassportCheck {
  const PassportCheck(this.type, this.status, this.performedAt);

  final String type;
  final String status;
  final DateTime? performedAt;
}

class ListingDetail {
  const ListingDetail({
    required this.summary,
    required this.imageUrls,
    required this.amenities,
    required this.checks,
    required this.passportNotice,
    required this.costs,
    required this.advertiserName,
    this.location,
    this.description,
    this.passportReference,
    this.costsDisclaimer,
  });

  factory ListingDetail.fromJson(Map<String, dynamic> j) {
    final property = _map(j['property']);
    final passport = _map(j['passport']);
    final costs = j['entry_costs'] is Map ? _map(j['entry_costs']) : null;
    final images = (property['images'] as List? ?? const [])
        .map(_map)
        .map((i) {
          final urls = _map(i['urls']);
          return (urls['large'] ?? urls['medium'] ?? urls['thumb']) as String?;
        })
        .whereType<String>()
        .toList();
    // La fiche reprend le résumé pour le prix, la fraîcheur et le type.
    final summary = ListingSummary(
      id: '${j['id']}',
      reference: '${j['reference']}',
      transaction: '${j['transaction'] ?? 'rent_long'}',
      title: j['title'] as String?,
      priceXof: (j['price_xof'] as num?)?.toInt(),
      negotiable: j['negotiable'] == true,
      propertyType: _map(property['type'])['label'] as String?,
      locality: _map(property['locality'])['name'] as String?,
      surfaceM2: (property['surface_m2'] as num?)?.toInt(),
      bedrooms: (property['bedrooms'] as num?)?.toInt(),
      furnished: property['furnished'] == true,
      agencyName: null,
      availability: Availability.fromJson(_map(passport['availability'])),
    );
    return ListingDetail(
      summary: summary,
      description: j['description'] as String?,
      imageUrls: images,
      amenities: (property['amenities'] as List? ?? const [])
          .map((a) => '${_map(a)['label']}')
          .toList(),
      passportReference: passport['reference'] as String?,
      checks: (passport['checks'] as List? ?? const [])
          .map(_map)
          .map(
            (c) => PassportCheck(
              '${c['type']}',
              '${c['status']}',
              _date(c['performed_at']),
            ),
          )
          .toList(),
      passportNotice:
          '${passport['notice'] ?? 'Un contrôle de document ne garantit pas la propriété juridique du bien.'}',
      costs: costs == null
          ? const {}
          : {
              'Loyer': (costs['rent_xof'] as num?)?.toInt(),
              'Caution': (costs['deposit_xof'] as num?)?.toInt(),
              'Avance': (costs['advance_xof'] as num?)?.toInt(),
              "Frais d'agence": (costs['agency_fee_xof'] as num?)?.toInt(),
              'Autres frais': (costs['other_fees_xof'] as num?)?.toInt(),
            },
      costsDisclaimer: costs?['disclaimer'] as String?,
      advertiserName: '${_map(j['advertiser'])['name'] ?? ''}',
      location: property['location'] is Map
          ? (
              lat: (_map(property['location'])['lat'] as num).toDouble(),
              lon: (_map(property['location'])['lon'] as num).toDouble(),
              exact: _map(property['location'])['precision'] == 'exact',
            )
          : null,
    );
  }

  final ListingSummary summary;
  final String? description;
  final List<String> imageUrls;
  final List<String> amenities;
  final String? passportReference;
  final List<PassportCheck> checks;
  final String passportNotice;

  /// Montants saisis par l'annonceur, sans total calculé (règles légales à valider).
  final Map<String, int?> costs;
  final String? costsDisclaimer;
  final String advertiserName;

  /// Position publique : zone approximative sauf choix contraire de l'annonceur.
  final ({double lat, double lon, bool exact})? location;
}

class Locality {
  const Locality(this.slug, this.name, [this.count]);

  final String slug;
  final String name;

  /// Nombre d'annonces publiques du quartier (si demandé à l'API).
  final int? count;
}

class SearchPage {
  const SearchPage(this.items, this.nextCursor, [this.total]);

  final List<ListingSummary> items;
  final String? nextCursor;

  /// Nombre total de résultats (donné à la première page seulement).
  final int? total;
}

/// Type de bien (filtre de recherche et création d'annonce).
class PropertyKind {
  const PropertyKind(this.id, this.code, this.label);

  final int id;
  final String code;
  final String label;
}

class SearchFilters {
  const SearchFilters({
    this.transaction = 'rent_long',
    this.locality,
    this.bedroomsMin,
    this.priceMin,
    this.priceMax,
    this.type,
    this.q,
    this.sort,
  });

  final String transaction;
  final String? locality;
  final int? bedroomsMin;
  final int? priceMin;
  final int? priceMax;

  /// Code du type de bien (ex. `apartment`).
  final String? type;

  /// Recherche libre (quartier, repère, résidence…).
  final String? q;
  final String? sort;

  /// Nombre de critères actifs hors transaction et tri (pastille du bouton « Filtres »).
  int get activeCount => [
    locality,
    bedroomsMin,
    priceMin,
    priceMax,
    type,
    q,
  ].where((v) => v != null).length;

  SearchFilters copyWith({
    String? transaction,
    String? Function()? locality,
    int? Function()? bedroomsMin,
    int? Function()? priceMin,
    int? Function()? priceMax,
    String? Function()? type,
    String? Function()? q,
    String? Function()? sort,
  }) => SearchFilters(
    transaction: transaction ?? this.transaction,
    locality: locality != null ? locality() : this.locality,
    bedroomsMin: bedroomsMin != null ? bedroomsMin() : this.bedroomsMin,
    priceMin: priceMin != null ? priceMin() : this.priceMin,
    priceMax: priceMax != null ? priceMax() : this.priceMax,
    type: type != null ? type() : this.type,
    q: q != null ? q() : this.q,
    sort: sort != null ? sort() : this.sort,
  );

  /// Paramètres d'API et critères d'une recherche enregistrée (mêmes clés).
  Map<String, String> toParams() => {
    'transaction': transaction,
    'locality': ?locality,
    if (bedroomsMin != null) 'bedrooms_min': '$bedroomsMin',
    if (priceMin != null) 'price_min': '$priceMin',
    if (priceMax != null) 'price_max': '$priceMax',
    'type': ?type,
    'q': ?q,
    'sort': ?sort,
  };
}

/// Point de carte : position publique approximative d'une annonce (jamais l'adresse exacte).
class MapPoint {
  const MapPoint({
    required this.id,
    required this.reference,
    required this.transaction,
    required this.lat,
    required this.lon,
    this.title,
    this.priceXof,
    this.cover,
    this.locality,
    this.typeLabel,
  });

  factory MapPoint.fromJson(Map<String, dynamic> j) => MapPoint(
    id: '${j['id']}',
    reference: '${j['reference']}',
    transaction: '${j['transaction'] ?? 'rent_long'}',
    title: j['title'] as String?,
    priceXof: (j['price_xof'] as num?)?.toInt(),
    lat: (j['lat'] as num).toDouble(),
    lon: (j['lon'] as num).toDouble(),
    cover: j['cover'] as String?,
    locality: j['locality'] as String?,
    typeLabel: j['property_type_label'] as String?,
  );

  final String id;
  final String reference;
  final String transaction;
  final String? title;
  final int? priceXof;
  final double lat;
  final double lon;
  final String? cover;
  final String? locality;
  final String? typeLabel;
}

/// Groupe d'annonces (carte dézoomée) : toucher zoome dessus.
class MapCluster {
  const MapCluster({
    required this.count,
    required this.lat,
    required this.lon,
    this.minPriceXof,
  });

  factory MapCluster.fromJson(Map<String, dynamic> j) => MapCluster(
    count: (j['count'] as num).toInt(),
    lat: (j['lat'] as num).toDouble(),
    lon: (j['lon'] as num).toDouble(),
    minPriceXof: (j['min_price_xof'] as num?)?.toInt(),
  );

  final int count;
  final double lat;
  final double lon;
  final int? minPriceXof;
}

class MapResult {
  const MapResult(this.points, this.clusters, this.truncated);

  final List<MapPoint> points;
  final List<MapCluster> clusters;
  final bool truncated;
}
