import 'package:flutter/material.dart';

import '../listings/listing_detail_screen.dart';
import '../listings/listing_widgets.dart';
import '../listings/listings_repository.dart';
import '../listings/models.dart';
import '../ui/widgets.dart';

class FavoritesScreen extends StatefulWidget {
  const FavoritesScreen({super.key, required this.repository});

  final ListingsRepository repository;

  @override
  State<FavoritesScreen> createState() => _FavoritesScreenState();
}

class _FavoritesScreenState extends State<FavoritesScreen> {
  late Future<List<ListingSummary>> _future = widget.repository.favorites();

  Future<void> _remove(ListingSummary l) async {
    try {
      await widget.repository.removeFavorite(l.id);
      setState(() => _future = widget.repository.favorites());
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(errorText(e))));
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Favoris')),
    body: FutureBuilder<List<ListingSummary>>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) return Center(child: Text(errorText(snap.error!)));
        final items = snap.data!;
        if (items.isEmpty) {
          return const Center(child: Text('Aucun favori pour le moment.'));
        }
        return ListView(
          padding: const EdgeInsets.all(12),
          children: [
            for (final l in items)
              ListingCard(
                listing: l,
                trailing: IconButton(
                  tooltip: 'Retirer',
                  icon: const Icon(Icons.favorite),
                  onPressed: () => _remove(l),
                ),
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => ListingDetailScreen(
                      repository: widget.repository,
                      reference: l.reference,
                      signedIn: true,
                    ),
                  ),
                ),
              ),
          ],
        );
      },
    ),
  );
}
