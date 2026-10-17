import 'package:flutter/material.dart';

import '../listings/listings_repository.dart';
import '../ui/widgets.dart';

class AlertsScreen extends StatefulWidget {
  const AlertsScreen({super.key, required this.repository});

  final ListingsRepository repository;

  @override
  State<AlertsScreen> createState() => _AlertsScreenState();
}

class _AlertsScreenState extends State<AlertsScreen> {
  late Future<List<SavedSearch>> _future = widget.repository.savedSearches();

  Future<void> _delete(SavedSearch s) async {
    try {
      await widget.repository.deleteSavedSearch(s.id);
      setState(() {
        _future = widget.repository.savedSearches();
      });
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(errorText(e))));
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Mes alertes')),
    body: FutureBuilder<List<SavedSearch>>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          return ErrorRetry(
            message: errorText(snap.error!),
            onRetry: () => setState(() {
              _future = widget.repository.savedSearches();
            }),
          );
        }
        final items = snap.data!;
        if (items.isEmpty) {
          return const Center(
            child: Text(
              "Aucune alerte. Créez-en une depuis la recherche (icône cloche).",
            ),
          );
        }
        return ListView(
          children: [
            for (final s in items)
              ListTile(
                title: Text(s.name),
                subtitle: Text(
                  s.frequency == 'immediate' ? 'Immédiate' : 'Quotidienne',
                ),
                trailing: IconButton(
                  tooltip: 'Supprimer',
                  icon: const Icon(Icons.delete_outline),
                  onPressed: () => _delete(s),
                ),
              ),
          ],
        );
      },
    ),
  );
}
