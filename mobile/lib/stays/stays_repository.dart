import '../core/api_client.dart';

Map<String, dynamic> _map(Object? v) =>
    v is Map<String, dynamic> ? v : const {};
List<Map<String, dynamic>> _list(Object? v) =>
    (v as List? ?? const []).whereType<Map<String, dynamic>>().toList();

/// Un jour « AAAA-MM-JJ » pour l'API.
String isoDay(DateTime d) =>
    '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

/// Nombre de nuits sur [arrivée, départ) ; 0 si les dates sont incohérentes.
int nightsBetween(DateTime checkIn, DateTime checkOut) {
  final a = DateTime.utc(checkIn.year, checkIn.month, checkIn.day);
  final b = DateTime.utc(checkOut.year, checkOut.month, checkOut.day);
  final n = b.difference(a).inDays;
  return n > 0 ? n : 0;
}

const bookingLabels = {
  'requested': 'Demande envoyée',
  'accepted': 'Acceptée : à régler',
  'awaiting_payment': 'En attente du règlement',
  'confirmed': 'Séjour confirmé',
  'checked_in': 'Séjour en cours',
  'completed': 'Séjour terminé',
  'declined': 'Refusée par l\'hôte',
  'expired': 'Expirée',
  'payment_failed': 'Paiement échoué',
  'cancelled_by_guest': 'Annulée par le voyageur',
  'cancelled_by_host': 'Annulée par l\'hôte',
  'disputed': 'Litige en cours',
};

class PriceLine {
  const PriceLine(this.code, this.label, this.amountXof);
  factory PriceLine.fromJson(Map<String, dynamic> j) => PriceLine(
    '${j['code']}',
    '${j['label']}',
    (j['amount_xof'] as num).toInt(),
  );

  final String code;
  final String label;
  final int amountXof;
}

class StayPricing {
  const StayPricing({
    required this.available,
    this.lines = const [],
    this.totalXof,
    this.message,
    this.notice,
  });

  factory StayPricing.fromJson(Map<String, dynamic> j) => StayPricing(
    available: j['available'] != false,
    lines: _list(j['lines']).map(PriceLine.fromJson).toList(),
    totalXof: (j['total_xof'] as num?)?.toInt(),
    message: j['message'] as String?,
    notice: _map(j['rules'])['taxes_notice'] as String?,
  );

  final bool available;
  final List<PriceLine> lines;
  final int? totalXof;
  final String? message;
  final String? notice;
}

class StayUnit {
  const StayUnit({
    required this.id,
    required this.name,
    required this.capacity,
    this.type,
    this.locality,
    this.bedrooms,
    this.bathrooms,
    this.fromNightlyXof,
    this.minNights = 1,
    this.amenities = const [],
    this.photos = const [],
    this.pricing,
  });

  factory StayUnit.fromJson(Map<String, dynamic> j) {
    final p = _map(j['property']);
    final rate = _map(j['rate']);
    return StayUnit(
      id: '${j['id']}',
      name: '${j['name']}',
      capacity: (j['capacity_guests'] as num).toInt(),
      type: p['type'] as String?,
      locality: _map(p['locality'])['name'] as String?,
      bedrooms: (p['bedrooms'] as num?)?.toInt(),
      bathrooms: (p['bathrooms'] as num?)?.toInt(),
      fromNightlyXof: (rate['from_nightly_xof'] as num?)?.toInt(),
      minNights: (rate['min_nights'] as num?)?.toInt() ?? 1,
      amenities: (p['amenities'] as List? ?? const [])
          .map((a) => '$a')
          .toList(),
      photos: (p['photos'] as List? ?? const [])
          .map((ph) => _map(ph))
          .map((ph) => (ph['large'] ?? ph['medium'] ?? ph['thumb']) as String?)
          .whereType<String>()
          .toList(),
      pricing: j['pricing'] is Map<String, dynamic>
          ? StayPricing.fromJson(j['pricing'] as Map<String, dynamic>)
          : null,
    );
  }

  final String id;
  final String name;
  final int capacity;
  final String? type;
  final String? locality;
  final int? bedrooms;
  final int? bathrooms;
  final int? fromNightlyXof;
  final int minNights;
  final List<String> amenities;
  final List<String> photos;
  final StayPricing? pricing;
}

class StayBooking {
  const StayBooking({
    required this.id,
    required this.state,
    required this.unitName,
    required this.checkIn,
    required this.checkOut,
    required this.guests,
    required this.totalXof,
    required this.guestFirstName,
    this.locality,
    this.lines = const [],
    this.respondBy,
    this.paymentDueAt,
    this.reason,
    this.paymentStatus,
    this.policyCode,
  });

  factory StayBooking.fromJson(Map<String, dynamic> j) {
    final unit = _map(j['unit']);
    return StayBooking(
      id: '${j['id']}',
      state: '${j['state']}',
      unitName: '${unit['name']}',
      locality: unit['locality'] as String?,
      checkIn: DateTime.parse('${j['check_in']}'),
      checkOut: DateTime.parse('${j['check_out']}'),
      guests: (j['guests'] as num).toInt(),
      totalXof: (j['total_xof'] as num).toInt(),
      guestFirstName: '${_map(j['guest'])['first_name']}',
      lines: _list(j['lines']).map(PriceLine.fromJson).toList(),
      respondBy: DateTime.tryParse('${j['respond_by']}'),
      paymentDueAt: DateTime.tryParse('${j['payment_due_at']}'),
      reason: j['reason'] as String?,
      paymentStatus: _map(j['payment'])['status'] as String?,
      policyCode: _map(j['cancellation_policy'])['code'] as String?,
    );
  }

  final String id;
  final String state;
  final String unitName;
  final String? locality;
  final DateTime checkIn;
  final DateTime checkOut;
  final int guests;
  final int totalXof;
  final String guestFirstName;
  final List<PriceLine> lines;
  final DateTime? respondBy;
  final DateTime? paymentDueAt;
  final String? reason;
  final String? paymentStatus;
  final String? policyCode;

  String get label => bookingLabels[state] ?? state;
  bool get needsPayment => state == 'accepted' || state == 'awaiting_payment';
  bool get cancellable => const [
    'requested',
    'accepted',
    'awaiting_payment',
    'confirmed',
  ].contains(state);
}

class PaymentInfo {
  const PaymentInfo({
    required this.id,
    required this.status,
    required this.amountXof,
    required this.reference,
    this.methods = const [],
    this.note,
  });

  factory PaymentInfo.fromJson(Map<String, dynamic> j) {
    final ins = _map(j['instructions']);
    return PaymentInfo(
      id: '${j['id']}',
      status: '${j['status']}',
      amountXof: (j['amount_xof'] as num).toInt(),
      reference: '${j['reference']}',
      methods: _list(ins['methods'])
          .map((m) => '${m['label']} : ${m['details']}')
          .toList(),
      note: ins['note'] as String?,
    );
  }

  final String id;
  final String status;
  final int amountXof;
  final String reference;
  final List<String> methods;
  final String? note;
}

/// Séjours : recherche publique, devis et demandes du voyageur, demandes côté hôte. Aucune route ne confirme un paiement.
class StaysRepository {
  StaysRepository(this.api);

  final ApiClient api;

  Future<List<StayUnit>> search({
    DateTime? checkIn,
    DateTime? checkOut,
    int guests = 2,
    String? locality,
    int? priceMax,
  }) async {
    final res = await api.get(
      '/stays',
      query: {
        if (checkIn != null && checkOut != null) 'check_in': isoDay(checkIn),
        if (checkIn != null && checkOut != null) 'check_out': isoDay(checkOut),
        'guests': '$guests',
        'locality': ?locality,
        if (priceMax != null) 'price_max': '$priceMax',
      },
    );
    return _list(res['data']).map(StayUnit.fromJson).toList();
  }

  Future<StayUnit> unit(
    String id, {
    DateTime? checkIn,
    DateTime? checkOut,
    int guests = 2,
  }) async {
    final res = await api.get(
      '/stays/$id',
      query: {
        if (checkIn != null && checkOut != null) 'check_in': isoDay(checkIn),
        if (checkIn != null && checkOut != null) 'check_out': isoDay(checkOut),
        'guests': '$guests',
      },
    );
    return StayUnit.fromJson(_map(res['data']));
  }

  /// Devis puis demande : une clé d'idempotence par intention évite toute demande en double.
  Future<StayBooking> request(
    String unitId,
    DateTime checkIn,
    DateTime checkOut,
    int guests,
    String idempotencyKey,
  ) async {
    final quote = await api.post('/quotes', {
      'unit': unitId,
      'check_in': isoDay(checkIn),
      'check_out': isoDay(checkOut),
      'guests': guests,
    });
    final res = await api.post('/booking-requests', {
      'quote': _map(quote['data'])['id'],
      'idempotency_key': idempotencyKey,
    });
    return StayBooking.fromJson(_map(res['data']));
  }

  Future<List<StayBooking>> myBookings() async =>
      _list((await api.get('/my/bookings'))['data'])
          .map(StayBooking.fromJson)
          .toList();
  Future<StayBooking> myBooking(String id) async =>
      StayBooking.fromJson(_map((await api.get('/my/bookings/$id'))['data']));
  Future<void> cancel(String id, {String? reason}) =>
      api.post('/my/bookings/$id/cancel', {'reason': ?reason});
  Future<PaymentInfo> prepayment(String bookingId, String method) async =>
      PaymentInfo.fromJson(
        _map(
          (await api.post('/my/bookings/$bookingId/payment-intents', {
            'method': method,
          }))['data'],
        ),
      );
  Future<PaymentInfo> declare(String intentId, String reference) async =>
      PaymentInfo.fromJson(
        _map(
          (await api.post('/my/payment-intents/$intentId/declare', {
            'reference': reference,
          }))['data'],
        ),
      );

  Future<List<StayBooking>> hostBookings({String? state}) async =>
      _list((await api.get('/host/bookings', query: {'state': ?state}))['data'])
          .map(StayBooking.fromJson)
          .toList();
  Future<void> hostAction(String id, String action, {String? reason}) =>
      api.post('/host/bookings/$id/$action', {'reason': ?reason});
}
