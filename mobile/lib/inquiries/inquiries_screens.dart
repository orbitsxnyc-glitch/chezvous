import 'package:flutter/material.dart';

import '../core/format.dart';
import '../ui/theme.dart';
import '../ui/widgets.dart';
import 'inquiries_repository.dart';

String formatDateTime(DateTime d) {
  final l = d.toLocal();
  String two(int n) => n.toString().padLeft(2, '0');
  return '${two(l.day)}/${two(l.month)}/${l.year} à ${two(l.hour)}h${two(l.minute)}';
}

/// Liste des conversations : demandes envoyées et reçues réunies, recherche et filtres Tous / Non lus / Visites.
class MessagesScreen extends StatefulWidget {
  const MessagesScreen({super.key, required this.repository});

  final InquiriesRepository repository;

  @override
  State<MessagesScreen> createState() => _MessagesScreenState();
}

class _MessagesScreenState extends State<MessagesScreen> {
  late Future<List<InquirySummary>> _future = _load();
  final _search = TextEditingController();
  int _filter = 0; // 0 tous, 1 non lus, 2 visites

  Future<List<InquirySummary>> _load() async {
    final results = await Future.wait([
      widget.repository.sent(),
      // Un compte sans annonce n'a pas de boîte de réception : son échec ne doit pas masquer les demandes envoyées.
      widget.repository.inbox().catchError((_) => <InquirySummary>[]),
    ]);
    final all = [...results[0], ...results[1]];
    all.sort(
      (a, b) => (b.lastActivity ?? DateTime(2000)).compareTo(
        a.lastActivity ?? DateTime(2000),
      ),
    );
    return all;
  }

  void _reload() => setState(() {
    _future = _load();
  });

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  String _time(DateTime? d) {
    if (d == null) return '';
    final l = d.toLocal();
    final now = DateTime.now();
    String two(int n) => n.toString().padLeft(2, '0');
    if (l.year == now.year && l.month == now.month && l.day == now.day) {
      return '${two(l.hour)}:${two(l.minute)}';
    }
    final yesterday = now.subtract(const Duration(days: 1));
    if (l.year == yesterday.year &&
        l.month == yesterday.month &&
        l.day == yesterday.day) {
      return 'Hier';
    }
    return '${two(l.day)}/${two(l.month)}';
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      bottom: false,
      child: FutureBuilder<List<InquirySummary>>(
        future: _future,
        builder: (context, snap) {
          final all = snap.data ?? const <InquirySummary>[];
          final unread = all.where((i) => i.unread > 0).length;
          final q = _search.text.trim().toLowerCase();
          final items = all.where((i) {
            if (_filter == 1 && i.unread == 0) return false;
            if (_filter == 2 && i.status != 'visit_scheduled') return false;
            if (q.isEmpty) return true;
            return '${i.counterpart} ${i.listingTitle} ${i.listingRef}'
                .toLowerCase()
                .contains(q);
          }).toList();
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
            children: [
              const AccentHeadline('Vos conversations', '.', size: 32),
              const SizedBox(height: 6),
              Text(
                'Discutez sans partager votre numéro.',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _search,
                onChanged: (_) => setState(() {}),
                decoration: const InputDecoration(
                  hintText: 'Rechercher une conversation…',
                  prefixIcon: Icon(Icons.search),
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  for (final (i, label) in [
                    'Tous ${all.length}',
                    'Non lus $unread',
                    'Visites',
                  ].indexed)
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.only(right: 6),
                        child: ChoiceChip(
                          showCheckmark: false,
                          label: SizedBox(
                            width: double.infinity,
                            child: Text(label, textAlign: TextAlign.center),
                          ),
                          selected: _filter == i,
                          selectedColor: AppColors.primary,
                          labelStyle: TextStyle(
                            fontWeight: FontWeight.w700,
                            color: _filter == i
                                ? Colors.white
                                : AppColors.muted,
                          ),
                          onSelected: (_) => setState(() => _filter = i),
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 8),
              if (snap.connectionState != ConnectionState.done)
                const Padding(
                  padding: EdgeInsets.all(32),
                  child: Center(child: CircularProgressIndicator()),
                )
              else if (snap.hasError)
                SizedBox(
                  height: 240,
                  child: ErrorRetry(
                    message: errorText(snap.error!),
                    onRetry: _reload,
                  ),
                )
              else if (items.isEmpty)
                const Padding(
                  padding: EdgeInsets.all(32),
                  child: Text(
                    'Aucune conversation. Contactez un annonceur depuis une annonce pour démarrer.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: AppColors.muted),
                  ),
                )
              else
                for (final i in items) _tile(context, i),
              const SizedBox(height: 16),
              const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.lock_outline, size: 16, color: AppColors.muted),
                  SizedBox(width: 6),
                  Text(
                    'Messages privés et sécurisés dans ChezVous',
                    style: TextStyle(color: AppColors.muted, fontSize: 12),
                  ),
                ],
              ),
            ],
          );
        },
      ),
    ),
  );

  static const _avatarColors = [
    Color(0xFFD8E4D6),
    Color(0xFFDDE0FA),
    Color(0xFFF8D9C8),
    Color(0xFFD1ECE4),
  ];

  Widget _tile(BuildContext context, InquirySummary i) {
    final name = i.counterpart ?? i.listingTitle ?? i.listingRef;
    final initials = name
        .trim()
        .split(RegExp(r'\s+'))
        .take(2)
        .map((w) => w.characters.first.toUpperCase())
        .join();
    return InkWell(
      onTap: () async {
        await Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) =>
                ThreadScreen(repository: widget.repository, id: i.id),
          ),
        );
        if (mounted) _reload();
      },
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: const BoxDecoration(
          border: Border(bottom: BorderSide(color: AppColors.line)),
        ),
        child: Row(
          children: [
            CircleAvatar(
              radius: 26,
              backgroundColor:
                  _avatarColors[name.hashCode.abs() % _avatarColors.length],
              child: Text(
                initials,
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  color: AppColors.ink,
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                          ),
                        ),
                      ),
                      Text(
                        _time(i.lastActivity),
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                  Text(
                    '${inquiryStatusLabels[i.status] ?? i.status} · ${i.listingTitle ?? i.listingRef}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                    ),
                  ),
                  Text(
                    i.lastMessage != null
                        ? '${i.lastMine ? 'Vous : ' : ''}${i.lastMessage}'
                        : 'Aucun message',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
            if (i.unread > 0)
              Container(
                margin: const EdgeInsets.only(left: 8),
                padding: const EdgeInsets.all(7),
                decoration: const BoxDecoration(
                  color: AppColors.primary,
                  shape: BoxShape.circle,
                ),
                child: Text(
                  '${i.unread}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class ThreadScreen extends StatefulWidget {
  const ThreadScreen({super.key, required this.repository, required this.id});

  final InquiriesRepository repository;
  final String id;

  @override
  State<ThreadScreen> createState() => _ThreadScreenState();
}

class _ThreadScreenState extends State<ThreadScreen> {
  late Future<InquiryDetail> _future = widget.repository.detail(widget.id);
  final _input = TextEditingController();
  String? _error;
  bool _sending = false;

  @override
  void dispose() {
    _input.dispose();
    super.dispose();
  }

  void _reload() => setState(() {
    _future = widget.repository.detail(widget.id);
  });

  Future<void> _run(Future<void> Function() action) async {
    try {
      await action();
      if (mounted) setState(() => _error = null);
      _reload();
    } catch (e) {
      if (mounted) setState(() => _error = errorText(e));
    }
  }

  Future<void> _send() async {
    final text = _input.text.trim();
    if (text.isEmpty || _sending) return;
    setState(() => _sending = true);
    await _run(() => widget.repository.send(widget.id, text));
    if (mounted) {
      _input.clear();
      setState(() => _sending = false);
    }
  }

  Future<void> _propose() async {
    final day = await showDatePicker(
      context: context,
      initialDate: DateTime.now().add(const Duration(days: 1)),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 60)),
    );
    if (day == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: const TimeOfDay(hour: 10, minute: 0),
    );
    if (time == null) return;
    final slot = DateTime(day.year, day.month, day.day, time.hour, time.minute);
    await _run(() => widget.repository.proposeVisit(widget.id, [slot]));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFFFBF7F0),
    appBar: AppBar(title: const Text('Conversation')),
    body: FutureBuilder<InquiryDetail>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          return ErrorRetry(message: errorText(snap.error!), onRetry: _reload);
        }
        final d = snap.data!;
        return Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(12),
                children: [
                  Text(
                    '${d.summary.listingTitle ?? d.summary.listingRef} · ${inquiryStatusLabels[d.summary.status] ?? d.summary.status}',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  for (final v in d.visits)
                    _VisitCard(
                      visit: v,
                      onConfirm: (s) =>
                          _run(() => widget.repository.confirmVisit(v.id, s)),
                      onCancel: () => _run(
                        () => widget.repository.cancelVisit(
                          v.id,
                          'Annulée depuis l\'application',
                        ),
                      ),
                    ),
                  const SizedBox(height: 8),
                  for (final m in d.messages)
                    Align(
                      alignment: m.mine
                          ? Alignment.centerRight
                          : Alignment.centerLeft,
                      child: Container(
                        constraints: BoxConstraints(
                          maxWidth: MediaQuery.of(context).size.width * 0.78,
                        ),
                        margin: const EdgeInsets.only(bottom: 10),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: m.mine ? AppColors.primary : Colors.white,
                          borderRadius: BorderRadius.only(
                            topLeft: Radius.circular(m.mine ? 14 : 4),
                            topRight: Radius.circular(m.mine ? 4 : 14),
                            bottomLeft: const Radius.circular(14),
                            bottomRight: const Radius.circular(14),
                          ),
                          boxShadow: const [
                            BoxShadow(
                              color: Color(0x0F182C25),
                              blurRadius: 8,
                              offset: Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              m.body,
                              style: TextStyle(
                                color: m.mine ? Colors.white : AppColors.ink,
                                height: 1.5,
                              ),
                            ),
                            if (m.createdAt != null)
                              Padding(
                                padding: const EdgeInsets.only(top: 4),
                                child: Text(
                                  formatDateTime(m.createdAt!),
                                  style: TextStyle(
                                    fontSize: 11,
                                    color: m.mine
                                        ? const Color(0xB3FFFFFF)
                                        : AppColors.muted,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.all(8),
                child: Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            if (d.canRespond)
              SafeArea(
                child: Padding(
                  padding: const EdgeInsets.all(8),
                  child: Row(
                    children: [
                      IconButton(
                        tooltip: 'Proposer une visite',
                        icon: const Icon(Icons.event_outlined),
                        onPressed: _propose,
                      ),
                      Expanded(
                        child: TextField(
                          controller: _input,
                          minLines: 1,
                          maxLines: 4,
                          decoration: const InputDecoration(
                            hintText: 'Votre message',
                            border: OutlineInputBorder(),
                          ),
                        ),
                      ),
                      IconButton(
                        tooltip: 'Envoyer',
                        icon: const Icon(Icons.send),
                        onPressed: _send,
                      ),
                    ],
                  ),
                ),
              ),
          ],
        );
      },
    ),
  );
}

const _visitLabels = {
  'proposed': 'Visite proposée',
  'confirmed': 'Visite confirmée',
  'rescheduled': 'Visite reprogrammée',
  'cancelled': 'Visite annulée',
  'done': 'Visite effectuée',
};

class _VisitCard extends StatelessWidget {
  const _VisitCard({
    required this.visit,
    required this.onConfirm,
    required this.onCancel,
  });

  final Visit visit;
  final ValueChanged<DateTime> onConfirm;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    final open =
        visit.status == 'proposed' ||
        visit.status == 'rescheduled' ||
        visit.status == 'confirmed';
    return Card(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadius.sm),
        side: const BorderSide(color: Color(0xFFF2C9A8)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const CircleAvatar(
                  radius: 18,
                  backgroundColor: AppColors.primarySoft,
                  child: Icon(
                    Icons.event_outlined,
                    size: 18,
                    color: AppColors.primary,
                  ),
                ),
                const SizedBox(width: 10),
                Text(
                  _visitLabels[visit.status] ?? visit.status,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ],
            ),
            if (visit.status == 'confirmed' && visit.scheduledAt != null)
              Text(formatDateTime(visit.scheduledAt!)),
            if (visit.status != 'confirmed')
              for (final s in visit.slots)
                Row(
                  children: [
                    Expanded(child: Text(formatDateTime(s))),
                    if (visit.awaitingMe && open)
                      TextButton(
                        onPressed: () => onConfirm(s),
                        child: const Text('Accepter'),
                      ),
                  ],
                ),
            if (open)
              TextButton(
                onPressed: onCancel,
                child: const Text('Annuler la visite'),
              ),
          ],
        ),
      ),
    );
  }
}

/// Premier contact : passe par la plateforme, le téléphone de l'annonceur n'est jamais affiché.
Future<bool> showContactSheet(
  BuildContext context,
  InquiriesRepository repository,
  String listingId,
) async {
  final controller = TextEditingController();
  final sent = await showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    builder: (ctx) {
      String? error;
      var pending = false;
      return StatefulBuilder(
        builder: (ctx, setSheet) => Padding(
          padding: EdgeInsets.fromLTRB(
            16,
            16,
            16,
            16 + MediaQuery.of(ctx).viewInsets.bottom,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                "Contacter l'annonceur",
                style: Theme.of(ctx).textTheme.titleMedium,
              ),
              const SizedBox(height: 12),
              TextField(
                controller: controller,
                minLines: 3,
                maxLines: 6,
                decoration: const InputDecoration(
                  labelText: 'Votre message',
                  border: OutlineInputBorder(),
                ),
              ),
              if (error != null)
                Text(
                  error!,
                  style: TextStyle(color: Theme.of(ctx).colorScheme.error),
                ),
              const SizedBox(height: 12),
              PrimaryButton(
                label: 'Envoyer',
                pending: pending,
                onPressed: () async {
                  final text = controller.text.trim();
                  if (text.isEmpty) {
                    setSheet(() => error = 'Écrivez un message.');
                    return;
                  }
                  setSheet(() {
                    pending = true;
                    error = null;
                  });
                  try {
                    await repository.open(listingId, text);
                    if (ctx.mounted) Navigator.pop(ctx, true);
                  } catch (e) {
                    setSheet(() {
                      pending = false;
                      error = errorText(e);
                    });
                  }
                },
              ),
            ],
          ),
        ),
      );
    },
  );
  controller.dispose();
  return sent == true;
}

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key, required this.repository});

  final InquiriesRepository repository;

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  late Future<({List<AppNotification> items, int unread})> _future = widget
      .repository
      .notifications();

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Notifications'),
      actions: [
        TextButton(
          onPressed: () async {
            await widget.repository.markAllRead();
            if (mounted) {
              setState(() {
                _future = widget.repository.notifications();
              });
            }
          },
          child: const Text('Tout lire'),
        ),
      ],
    ),
    body: FutureBuilder(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          return ErrorRetry(
            message: errorText(snap.error!),
            onRetry: () => setState(() {
              _future = widget.repository.notifications();
            }),
          );
        }
        final items = snap.data!.items;
        if (items.isEmpty) {
          return const Center(child: Text('Aucune notification.'));
        }
        return ListView(
          children: [
            for (final n in items)
              ListTile(
                leading: Icon(
                  n.read
                      ? Icons.notifications_none
                      : Icons.notifications_active,
                ),
                title: Text(
                  n.text,
                  style: TextStyle(
                    fontWeight: n.read ? FontWeight.normal : FontWeight.bold,
                  ),
                ),
                subtitle: n.createdAt != null
                    ? Text(relativeSince(n.createdAt!, DateTime.now()))
                    : null,
              ),
          ],
        );
      },
    ),
  );
}
