import 'package:http/http.dart' as http;

import '../core/api_client.dart';

Map<String, dynamic> _map(Object? v) =>
    v is Map<String, dynamic> ? v : const {};
List<Map<String, dynamic>> _list(Object? v) =>
    (v as List? ?? const []).whereType<Map<String, dynamic>>().toList();

/// Entier saisi -> nombre ; vide -> null (efface la valeur) ; invalide -> absent (champ ignoré).
({bool valid, int? value}) parseInt(String raw) {
  final v = raw.replaceAll(RegExp(r'\s'), '');
  if (v.isEmpty) return (valid: true, value: null);
  final n = RegExp(r'^\d+$').hasMatch(v) ? int.tryParse(v) : null;
  return n == null ? (valid: false, value: null) : (valid: true, value: n);
}

const missingLabels = {
  'title': 'le titre',
  'description': 'la description',
  'price_xof': 'le prix',
  'locality_id': 'le quartier',
  'surface_m2': 'la surface',
  'cover_image': 'une photo de couverture',
};

class ListingImage {
  const ListingImage(this.id, this.status, this.isCover, this.thumbUrl);

  factory ListingImage.fromJson(Map<String, dynamic> j) {
    final urls = _map(j['urls']);
    return ListingImage(
      '${j['id']}',
      '${j['status']}',
      j['is_cover'] == true,
      (urls['thumb'] ?? urls['medium']) as String?,
    );
  }

  final String id;
  final String status;
  final bool isCover;
  final String? thumbUrl;
}

class EditableListing {
  const EditableListing({
    required this.id,
    required this.reference,
    required this.status,
    required this.transaction,
    required this.negotiable,
    required this.furnished,
    required this.images,
    required this.amenityIds,
    required this.missingFields,
    this.title,
    this.description,
    this.priceXof,
    this.depositMonths,
    this.advanceMonths,
    this.agencyFeeXof,
    this.otherFeesXof,
    this.localityId,
    this.address,
    this.surfaceM2,
    this.rooms,
    this.bedrooms,
    this.bathrooms,
    this.rejectionComment,
  });

  factory EditableListing.fromJson(Map<String, dynamic> j) {
    final p = _map(j['property']);
    int? i(Object? v) => (v as num?)?.toInt();
    return EditableListing(
      id: '${j['id']}',
      reference: '${j['reference']}',
      status: '${j['status']}',
      transaction: '${j['transaction'] ?? 'rent_long'}',
      negotiable: j['negotiable'] == true,
      title: j['title'] as String?,
      description: j['description'] as String?,
      priceXof: i(j['price_xof']),
      depositMonths: i(j['deposit_months']),
      advanceMonths: i(j['advance_months']),
      agencyFeeXof: i(j['agency_fee_xof']),
      otherFeesXof: i(j['other_fees_xof']),
      localityId: i(p['locality_id']),
      address: p['address'] as String?,
      surfaceM2: i(p['surface_m2']),
      rooms: i(p['rooms']),
      bedrooms: i(p['bedrooms']),
      bathrooms: i(p['bathrooms']),
      furnished: p['furnished'] == true,
      amenityIds: (p['amenity_ids'] as List? ?? const [])
          .map((e) => (e as num).toInt())
          .toList(),
      images: _list(p['images']).map(ListingImage.fromJson).toList(),
      missingFields: (j['missing_fields'] as List? ?? const [])
          .map((e) => '$e')
          .toList(),
      rejectionComment: _map(j['rejection'])['comment'] as String?,
    );
  }

  final String id;
  final String reference;
  final String status;
  final String transaction;
  final bool negotiable;
  final bool furnished;
  final String? title;
  final String? description;
  final int? priceXof;
  final int? depositMonths;
  final int? advanceMonths;
  final int? agencyFeeXof;
  final int? otherFeesXof;
  final int? localityId;
  final String? address;
  final int? surfaceM2;
  final int? rooms;
  final int? bedrooms;
  final int? bathrooms;
  final List<int> amenityIds;
  final List<ListingImage> images;
  final List<String> missingFields;
  final String? rejectionComment;

  bool get rental => transaction != 'sale';
  bool get processing => images.any((i) => i.status == 'processing');
  bool get canSubmit => const ['draft', 'rejected', 'expired'].contains(status);
}

class Option {
  const Option(this.id, this.label);

  final int id;
  final String label;
}

class EditorRepository {
  const EditorRepository(this.api);

  final ApiClient api;

  Future<List<Option>> _options(
    String path, {
    Map<String, String>? query,
    String key = 'label',
  }) async {
    final res = await api.get(path, query: query);
    return _list(res['data'])
        .map((e) => Option((e['id'] as num).toInt(), '${e[key]}'))
        .toList();
  }

  Future<List<Option>> propertyTypes() => _options('/property-types');

  Future<List<Option>> amenities() => _options('/amenities');

  Future<List<Option>> neighborhoods() =>
      _options('/localities', query: {'level': 'neighborhood'}, key: 'name');

  Future<EditableListing> create(int typeId, String transaction) async =>
      EditableListing.fromJson(
        _map(
          (await api.post('/my/listings', {
            'property_type_id': typeId,
            'transaction': transaction,
          }))['data'],
        ),
      );

  Future<EditableListing> get(String id) async => EditableListing.fromJson(
    _map((await api.get('/my/listings/$id'))['data']),
  );

  Future<EditableListing> update(String id, Map<String, dynamic> body) async =>
      EditableListing.fromJson(
        _map((await api.patch('/my/listings/$id', body))['data']),
      );

  Future<EditableListing> submit(String id) async => EditableListing.fromJson(
    _map((await api.post('/my/listings/$id/submit'))['data']),
  );

  Future<EditableListing> uploadPhotos(
    String id,
    List<({String name, List<int> bytes})> photos,
  ) async {
    final files = [
      for (final p in photos)
        http.MultipartFile.fromBytes('images[]', p.bytes, filename: p.name),
    ];
    return EditableListing.fromJson(
      _map((await api.upload('/my/listings/$id/images', files))['data']),
    );
  }

  Future<void> setCover(String id, String imageId) =>
      api.patch('/my/listings/$id/images/$imageId/cover', {});

  Future<void> deleteImage(String id, String imageId) =>
      api.delete('/my/listings/$id/images/$imageId');
}
