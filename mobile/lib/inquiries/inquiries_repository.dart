import '../core/api_client.dart';

Map<String, dynamic> _map(Object? v) =>
    v is Map<String, dynamic> ? v : const {};
List<Map<String, dynamic>> _list(Object? v) =>
    (v as List? ?? const []).whereType<Map<String, dynamic>>().toList();

const inquiryStatusLabels = {
  'new': 'Nouvelle',
  'in_progress': 'En cours',
  'visit_scheduled': 'Visite planifiée',
  'concluded': 'Conclue',
  'lost': 'Perdue',
  'closed': 'Fermée',
};

class InquirySummary {
  const InquirySummary({
    required this.id,
    required this.status,
    required this.listingRef,
    required this.listingTitle,
    required this.unread,
    required this.role,
    required this.lastActivity,
    this.counterpart,
    this.lastMessage,
    this.lastMine = false,
  });

  factory InquirySummary.fromJson(Map<String, dynamic> j) {
    final listing = _map(j['listing']);
    return InquirySummary(
      id: '${j['id']}',
      status: '${j['status']}',
      listingRef: '${listing['reference'] ?? ''}',
      listingTitle: listing['title'] as String?,
      unread: (j['unread'] as num?)?.toInt() ?? 0,
      role: '${j['role'] ?? 'requester'}',
      lastActivity: DateTime.tryParse('${j['last_activity_at']}'),
      counterpart: j['counterpart'] as String?,
      lastMessage: _map(j['last_message'])['body'] as String?,
      lastMine: _map(j['last_message'])['mine'] == true,
    );
  }

  final String id;
  final String status;
  final String listingRef;
  final String? listingTitle;
  final int unread;
  final String role;
  final DateTime? lastActivity;

  /// Interlocuteur affiché (annonceur ou demandeur) et aperçu du dernier message.
  final String? counterpart;
  final String? lastMessage;
  final bool lastMine;
}

class ChatMessage {
  const ChatMessage(this.id, this.mine, this.body, this.createdAt, this.sender);

  factory ChatMessage.fromJson(Map<String, dynamic> j) => ChatMessage(
    (j['id'] as num).toInt(),
    j['mine'] == true,
    '${j['body']}',
    DateTime.tryParse('${j['created_at']}'),
    j['sender_name'] as String?,
  );

  final int id;
  final bool mine;
  final String body;
  final DateTime? createdAt;
  final String? sender;
}

class Visit {
  const Visit({
    required this.id,
    required this.status,
    required this.slots,
    required this.scheduledAt,
    required this.awaitingMe,
    required this.proposedByMe,
  });

  factory Visit.fromJson(Map<String, dynamic> j) => Visit(
    id: '${j['id']}',
    status: '${j['status']}',
    slots: (j['proposed_slots'] as List? ?? const [])
        .map((e) => DateTime.tryParse('$e'))
        .whereType<DateTime>()
        .toList(),
    scheduledAt: DateTime.tryParse('${j['scheduled_at']}'),
    awaitingMe: j['awaiting_me'] == true,
    proposedByMe: j['proposed_by_me'] == true,
  );

  final String id;
  final String status;
  final List<DateTime> slots;
  final DateTime? scheduledAt;
  final bool awaitingMe;
  final bool proposedByMe;
}

class InquiryDetail {
  const InquiryDetail({
    required this.summary,
    required this.messages,
    required this.visits,
    required this.canRespond,
  });

  factory InquiryDetail.fromJson(Map<String, dynamic> j) => InquiryDetail(
    summary: InquirySummary.fromJson(j),
    messages: _list(j['messages']).map(ChatMessage.fromJson).toList(),
    visits: _list(j['visits']).map(Visit.fromJson).toList(),
    canRespond: j['can_respond'] != false,
  );

  final InquirySummary summary;
  final List<ChatMessage> messages;
  final List<Visit> visits;
  final bool canRespond;
}

class AppNotification {
  const AppNotification(
    this.id,
    this.type,
    this.listing,
    this.read,
    this.createdAt,
  );

  factory AppNotification.fromJson(Map<String, dynamic> j) => AppNotification(
    '${j['id']}',
    '${j['type']}',
    _map(j['data'])['listing'] as String?,
    j['read'] == true,
    DateTime.tryParse('${j['created_at']}'),
  );

  final String id;
  final String type;
  final String? listing;
  final bool read;
  final DateTime? createdAt;

  /// Phrase lisible ; les données d'une notification ne contiennent jamais de coordonnées personnelles.
  String get text {
    final ref = listing != null ? ' ($listing)' : '';
    return switch (type) {
      'inquiry.new' => 'Nouvelle demande de contact$ref',
      'inquiry.message' => 'Nouveau message$ref',
      'inquiry.status' => 'Une demande a changé de statut$ref',
      'visit.proposed' => 'Nouvelle proposition de visite$ref',
      'visit.confirmed' => 'Visite confirmée$ref',
      'visit.cancelled' => 'Visite annulée$ref',
      _ => 'Nouvelle notification$ref',
    };
  }
}

class InquiriesRepository {
  const InquiriesRepository(this.api);

  final ApiClient api;

  Future<List<InquirySummary>> sent() async =>
      _list((await api.get('/my/inquiries'))['data'])
          .map(InquirySummary.fromJson)
          .toList();

  Future<List<InquirySummary>> inbox() async =>
      _list((await api.get('/inbox'))['data'])
          .map(InquirySummary.fromJson)
          .toList();

  Future<InquiryDetail> detail(String id) async =>
      InquiryDetail.fromJson(_map((await api.get('/inquiries/$id'))['data']));

  Future<void> open(
    String listingId,
    String message, {
    String? phone,
    String channel = 'message',
  }) => api.post('/listings/${Uri.encodeComponent(listingId)}/inquiries', {
    'message': message,
    'preferred_channel': channel,
    if (phone != null && phone.isNotEmpty) 'phone': phone,
  });

  Future<void> send(String id, String body) =>
      api.post('/inquiries/$id/messages', {'body': body});

  Future<void> proposeVisit(String id, List<DateTime> slots) => api.post(
    '/inquiries/$id/visits',
    {'slots': slots.map((s) => s.toUtc().toIso8601String()).toList()},
  );

  Future<void> confirmVisit(String visitId, DateTime slot) => api.patch(
    '/visits/$visitId',
    {'action': 'confirm', 'slot': slot.toUtc().toIso8601String()},
  );

  Future<void> cancelVisit(String visitId, String reason) =>
      api.patch('/visits/$visitId', {'action': 'cancel', 'reason': reason});

  Future<({List<AppNotification> items, int unread})> notifications() async {
    final res = await api.get('/my/notifications');
    return (
      items: _list(res['data']).map(AppNotification.fromJson).toList(),
      unread: (_map(res['meta'])['unread'] as num?)?.toInt() ?? 0,
    );
  }

  Future<void> markAllRead() => api.patch('/my/notifications/read', {});
}
