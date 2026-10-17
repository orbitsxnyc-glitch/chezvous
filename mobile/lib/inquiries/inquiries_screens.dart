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

class MessagesScreen extends StatelessWidget {
  const MessagesScreen({super.key, required this.repository});

  final InquiriesRepository repository;

  @override
  Widget build(BuildContext context) => DefaultTabController(
    length: 2,
    child: Scaffold(
      appBar: AppBar(
        title: const Text('Messages'),
        bottom: const TabBar(
          tabs: [
            Tab(text: 'Envoyées'),
            Tab(text: 'Reçues'),
          ],
        ),
      ),
      body: TabBarView(
        children: [
          _InquiryList(
            load: repository.sent,
            repository: repository,
            empty: "Vous n'avez encore contacté aucun annonceur.",
          ),
          _InquiryList(
            load: repository.inbox,
            repository: repository,
            empty: "Aucune demande reçue. Cet onglet concerne les annonceurs.",
          ),
        ],
      ),
    ),
  );
}

class _InquiryList extends StatefulWidget {
  const _InquiryList({
    required this.load,
    required this.repository,
    required this.empty,
  });

  final Future<List<InquirySummary>> Function() load;
  final InquiriesRepository repository;
  final String empty;

  @override
  State<_InquiryList> createState() => _InquiryListState();
}

class _InquiryListState extends State<_InquiryList> {
  late Future<List<InquirySummary>> _future = widget.load();

  @override
  Widget build(BuildContext context) => FutureBuilder<List<InquirySummary>>(
    future: _future,
    builder: (context, snap) {
      if (snap.connectionState != ConnectionState.done) {
        return const Center(child: CircularProgressIndicator());
      }
      if (snap.hasError) {
        return Center(child: Text(errorText(snap.error!)));
      }
      final items = snap.data!;
      if (items.isEmpty) {
        return Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Text(widget.empty, textAlign: TextAlign.center),
          ),
        );
      }
      return RefreshIndicator(
        onRefresh: () async {
          setState(() => _future = widget.load());
          await _future;
        },
        child: ListView(
          children: [
            for (final i in items)
              ListTile(
                title: Text(i.listingTitle ?? i.listingRef),
                subtitle: Text(
                  '${inquiryStatusLabels[i.status] ?? i.status} · ${i.listingRef}',
                ),
                trailing: i.unread > 0
                    ? Badge(label: Text('${i.unread}'))
                    : null,
                onTap: () async {
                  await Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) =>
                          ThreadScreen(repository: widget.repository, id: i.id),
                    ),
                  );
                  if (mounted) setState(() => _future = widget.load());
                },
              ),
          ],
        ),
      );
    },
  );
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

  void _reload() =>
      setState(() => _future = widget.repository.detail(widget.id));

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
          return Center(child: Text(errorText(snap.error!)));
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
              setState(() => _future = widget.repository.notifications());
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
          return Center(child: Text(errorText(snap.error!)));
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
