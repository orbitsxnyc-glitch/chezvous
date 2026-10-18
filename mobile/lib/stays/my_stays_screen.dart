import 'package:flutter/material.dart';

import '../core/format.dart';
import '../ui/theme.dart';
import '../ui/widgets.dart';
import 'stay_search_screen.dart' show dayLabel;
import 'stays_repository.dart';

Color _tone(String state) => switch (state) {
  'confirmed' || 'checked_in' => AppColors.success,
  'requested' => AppColors.warning,
  'accepted' || 'awaiting_payment' => AppColors.info,
  'declined' || 'payment_failed' || 'disputed' => AppColors.danger,
  _ => AppColors.muted,
};

/// État en toutes lettres : la couleur n'est jamais la seule information.
class StateChip extends StatelessWidget {
  const StateChip(this.booking, {super.key});

  final StayBooking booking;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
    decoration: BoxDecoration(
      color: _tone(booking.state).withValues(alpha: 0.12),
      borderRadius: BorderRadius.circular(8),
    ),
    child: Text(
      booking.label,
      style: TextStyle(
        color: _tone(booking.state),
        fontWeight: FontWeight.w800,
        fontSize: 12,
      ),
    ),
  );
}

String _when(DateTime d) {
  final l = d.toLocal();
  String two(int n) => n.toString().padLeft(2, '0');
  return '${dayLabel(l)} à ${two(l.hour)}h${two(l.minute)}';
}

class MyStaysScreen extends StatefulWidget {
  const MyStaysScreen({super.key, required this.repository, this.openId});

  final StaysRepository repository;

  /// Ouvre directement ce séjour (après une demande).
  final String? openId;

  @override
  State<MyStaysScreen> createState() => _MyStaysScreenState();
}

class _MyStaysScreenState extends State<MyStaysScreen> {
  late Future<List<StayBooking>> _future = widget.repository.myBookings();

  void _reload() => setState(() {
    _future = widget.repository.myBookings();
  });

  @override
  void initState() {
    super.initState();
    if (widget.openId != null) {
      WidgetsBinding.instance.addPostFrameCallback(
        (_) => _open(widget.openId!),
      );
    }
  }

  Future<void> _open(String id) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) =>
            MyStayDetailScreen(repository: widget.repository, id: id),
      ),
    );
    if (mounted) _reload();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Mes séjours')),
    body: FutureBuilder<List<StayBooking>>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          return ErrorRetry(message: errorText(snap.error!), onRetry: _reload);
        }
        final items = snap.data!;
        if (items.isEmpty) {
          return const Center(
            child: Padding(
              padding: EdgeInsets.all(24),
              child: Text('Aucun séjour pour le moment.'),
            ),
          );
        }
        return RefreshIndicator(
          onRefresh: () async {
            _reload();
            await _future;
          },
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              for (final b in items)
                Card(
                  child: ListTile(
                    onTap: () => _open(b.id),
                    title: Text(
                      b.unitName,
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    subtitle: Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${dayLabel(b.checkIn)} → ${dayLabel(b.checkOut)} · ${b.guests} voyageur(s)',
                          ),
                          const SizedBox(height: 6),
                          StateChip(b),
                        ],
                      ),
                    ),
                    trailing: Text(
                      '${groupThousands(b.totalXof)} FCFA',
                      style: const TextStyle(
                        color: AppColors.primary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    ),
  );
}

/// Un séjour du voyageur : prix, échéances, règlement (référence et consignes, « J'ai payé » qui ne confirme rien), annulation.
class MyStayDetailScreen extends StatefulWidget {
  const MyStayDetailScreen({
    super.key,
    required this.repository,
    required this.id,
  });

  final StaysRepository repository;
  final String id;

  @override
  State<MyStayDetailScreen> createState() => _MyStayDetailScreenState();
}

class _MyStayDetailScreenState extends State<MyStayDetailScreen> {
  late Future<StayBooking> _future = widget.repository.myBooking(widget.id);
  PaymentInfo? _payment;
  String _method = 'wave';
  String? _error;
  String? _info;
  bool _busy = false;
  final _reference = TextEditingController();

  static const _methods = {
    'wave': 'Wave',
    'orange_money': 'Orange Money',
    'transfer': 'Virement',
    'cash': 'Espèces',
  };

  @override
  void dispose() {
    _reference.dispose();
    super.dispose();
  }

  void _reload() => setState(() {
    _future = widget.repository.myBooking(widget.id);
  });

  Future<void> _run(Future<void> Function() action, {String? done}) async {
    setState(() {
      _busy = true;
      _error = null;
      _info = null;
    });
    try {
      await action();
      if (done != null) _info = done;
      _reload();
    } catch (e) {
      _error = errorText(e);
    }
    if (mounted) setState(() => _busy = false);
  }

  Future<void> _cancel() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Annuler ce séjour ?'),
        content: const Text(
          'Le remboursement éventuel suit la politique d\'annulation de ce séjour.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Non'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Annuler le séjour'),
          ),
        ],
      ),
    );
    if (ok == true) {
      await _run(
        () => widget.repository.cancel(widget.id),
        done: 'Séjour annulé.',
      );
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Mon séjour')),
    body: FutureBuilder<StayBooking>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          return ErrorRetry(message: errorText(snap.error!), onRetry: _reload);
        }
        final b = snap.data!;
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(b.unitName, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 6),
            StateChip(b),
            const SizedBox(height: 8),
            Text(
              '${dayLabel(b.checkIn)} → ${dayLabel(b.checkOut)} · ${b.guests} voyageur(s)',
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Text(
                  _error!,
                  style: const TextStyle(color: AppColors.danger),
                ),
              ),
            if (_info != null)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Text(
                  _info!,
                  style: const TextStyle(
                    color: AppColors.success,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            const SizedBox(height: 16),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Prix',
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: 8),
                    for (final l in b.lines)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 2),
                        child: Row(
                          children: [
                            Expanded(child: Text(l.label)),
                            Text('${groupThousands(l.amountXof)} FCFA'),
                          ],
                        ),
                      ),
                    const Divider(),
                    Row(
                      children: [
                        const Expanded(
                          child: Text(
                            'Total',
                            style: TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ),
                        Text(
                          '${groupThousands(b.totalXof)} FCFA',
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                      ],
                    ),
                    if (b.policyCode != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text(
                          'Politique d\'annulation : ${b.policyCode}.',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                  ],
                ),
              ),
            ),
            if (b.state == 'requested' && b.respondBy != null)
              _note(
                'L\'hôte a jusqu\'au ${_when(b.respondBy!)} pour répondre. Vos dates ne sont pas encore réservées et rien n\'est débité.',
              ),
            if (b.reason != null) _note('Motif : ${b.reason}'),
            if (b.needsPayment) ..._paymentBlock(b),
            if (b.state == 'confirmed')
              _note(
                'Séjour confirmé. L\'adresse exacte et les consignes d\'arrivée vous sont communiquées par l\'hôte via ChezVous.',
              ),
            if (b.cancellable)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: TextButton(
                  onPressed: _busy ? null : _cancel,
                  style: TextButton.styleFrom(
                    foregroundColor: AppColors.danger,
                  ),
                  child: const Text('Annuler ce séjour'),
                ),
              ),
          ],
        );
      },
    ),
  );

  Widget _note(String text) => Container(
    margin: const EdgeInsets.only(top: 12),
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: AppColors.sand,
      borderRadius: BorderRadius.circular(AppRadius.md),
    ),
    child: Text(text, style: const TextStyle(fontSize: 13, height: 1.4)),
  );

  List<Widget> _paymentBlock(StayBooking b) => [
    const SizedBox(height: 16),
    Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.primarySoft,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.sandDeep),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Règlement',
            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
          ),
          if (b.paymentDueAt != null)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                'Vos dates sont réservées jusqu\'au ${_when(b.paymentDueAt!)}. Passé ce délai, la demande expire.',
              ),
            ),
          const SizedBox(height: 10),
          if (_payment == null) ...[
            DropdownButtonFormField<String>(
              initialValue: _method,
              decoration: const InputDecoration(labelText: 'Moyen de paiement'),
              items: [
                for (final e in _methods.entries)
                  DropdownMenuItem(value: e.key, child: Text(e.value)),
              ],
              onChanged: (v) => setState(() => _method = v ?? 'wave'),
            ),
            const SizedBox(height: 10),
            PrimaryButton(
              label: 'Voir comment payer',
              pending: _busy,
              onPressed: () => _run(
                () async => _payment = await widget.repository.prepayment(
                  b.id,
                  _method,
                ),
              ),
            ),
          ] else ...[
            Text(
              'Montant à régler : ${groupThousands(_payment!.amountXof)} FCFA',
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 4),
            SelectableText(
              'Référence à indiquer : ${_payment!.reference}',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            for (final m in _payment!.methods)
              Padding(padding: const EdgeInsets.only(top: 6), child: Text(m)),
            if (_payment!.note != null)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(
                  _payment!.note!,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ),
            const SizedBox(height: 10),
            if (_payment!.status == 'pending')
              const Text(
                'Règlement déclaré : en cours de vérification par l\'équipe.',
                style: TextStyle(
                  color: AppColors.info,
                  fontWeight: FontWeight.w700,
                ),
              )
            else ...[
              TextField(
                controller: _reference,
                decoration: const InputDecoration(
                  labelText: 'Référence de votre transaction',
                ),
              ),
              const SizedBox(height: 10),
              OutlinedButton(
                onPressed: _busy
                    ? null
                    : () => _run(
                        () async {
                          _payment = await widget.repository.declare(
                            _payment!.id,
                            _reference.text.trim(),
                          );
                        },
                        done: 'Merci : nous vérifions la réception. Votre séjour n\'est confirmé qu\'après cette vérification.',
                      ),
                child: const Text('J\'ai payé'),
              ),
            ],
            const SizedBox(height: 6),
            Text(
              'Déclarer un paiement ne confirme pas le séjour : seule la vérification de la réception par ChezVous le confirme.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ],
      ),
    ),
  ];
}
