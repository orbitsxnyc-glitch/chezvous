import 'dart:async';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../ui/widgets.dart';
import 'editor_repository.dart';

class NewListingScreen extends StatefulWidget {
  const NewListingScreen({super.key, required this.repository});

  final EditorRepository repository;

  @override
  State<NewListingScreen> createState() => _NewListingScreenState();
}

class _NewListingScreenState extends State<NewListingScreen> {
  late final Future<List<Option>> _types = widget.repository.propertyTypes();
  String _transaction = 'rent_long';
  int? _type;
  bool _pending = false;
  String? _error;

  Future<void> _create() async {
    if (_type == null) {
      setState(() => _error = 'Choisissez un type de bien.');
      return;
    }
    setState(() {
      _pending = true;
      _error = null;
    });
    try {
      final l = await widget.repository.create(_type!, _transaction);
      if (!mounted) return;
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (_) =>
              ListingEditorScreen(repository: widget.repository, id: l.id),
        ),
      );
      return;
    } catch (e) {
      _error = errorText(e);
    }
    if (mounted) setState(() => _pending = false);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Nouvelle annonce')),
    body: FutureBuilder<List<Option>>(
      future: _types,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          return Center(child: Text(errorText(snap.error!)));
        }
        return ListView(
          padding: const EdgeInsets.all(24),
          children: [
            DropdownButtonFormField<String>(
              initialValue: _transaction,
              decoration: const InputDecoration(
                labelText: 'Je propose',
                border: OutlineInputBorder(),
              ),
              items: const [
                DropdownMenuItem(
                  value: 'rent_long',
                  child: Text('Une location'),
                ),
                DropdownMenuItem(value: 'sale', child: Text('Une vente')),
              ],
              onChanged: (v) => setState(() => _transaction = v ?? 'rent_long'),
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<int>(
              initialValue: _type,
              decoration: const InputDecoration(
                labelText: 'Type de bien',
                border: OutlineInputBorder(),
              ),
              items: [
                for (final t in snap.data!)
                  DropdownMenuItem(value: t.id, child: Text(t.label)),
              ],
              onChanged: (v) => setState(() => _type = v),
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            const SizedBox(height: 24),
            PrimaryButton(
              label: 'Créer le brouillon',
              pending: _pending,
              onPressed: _create,
            ),
          ],
        );
      },
    ),
  );
}

/// Éditeur sobre en sections, chacune enregistrée explicitement. Les droits sont vérifiés par l'API.
class ListingEditorScreen extends StatefulWidget {
  const ListingEditorScreen({
    super.key,
    required this.repository,
    required this.id,
    this.picker,
  });

  final EditorRepository repository;
  final String id;

  /// Injectable pour les tests.
  final ImagePicker? picker;

  @override
  State<ListingEditorScreen> createState() => _ListingEditorScreenState();
}

class _ListingEditorScreenState extends State<ListingEditorScreen> {
  EditableListing? _listing;
  List<Option> _neighborhoods = const [];
  List<Option> _amenities = const [];
  String? _error;
  String? _notice;
  bool _busy = false;
  Timer? _poll;

  final _title = TextEditingController();
  final _description = TextEditingController();
  final _price = TextEditingController();
  final _deposit = TextEditingController();
  final _advance = TextEditingController();
  final _agencyFee = TextEditingController();
  final _otherFees = TextEditingController();
  final _address = TextEditingController();
  final _surface = TextEditingController();
  final _rooms = TextEditingController();
  final _bedrooms = TextEditingController();
  final _bathrooms = TextEditingController();
  bool _negotiable = false;
  bool _furnished = false;
  int? _locality;
  Set<int> _amenityIds = {};

  @override
  void initState() {
    super.initState();
    _load(fillForms: true);
    widget.repository
        .neighborhoods()
        .then((v) {
          if (mounted) setState(() => _neighborhoods = v);
        })
        .catchError((_) {});
    widget.repository
        .amenities()
        .then((v) {
          if (mounted) setState(() => _amenities = v);
        })
        .catchError((_) {});
  }

  @override
  void dispose() {
    _poll?.cancel();
    for (final c in [
      _title,
      _description,
      _price,
      _deposit,
      _advance,
      _agencyFee,
      _otherFees,
      _address,
      _surface,
      _rooms,
      _bedrooms,
      _bathrooms,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  String _s(int? v) => v?.toString() ?? '';

  /// `fillForms` : on ne réécrit les champs que au chargement et après un enregistrement, jamais pendant le suivi des photos.
  Future<void> _load({bool fillForms = false}) async {
    try {
      final l = await widget.repository.get(widget.id);
      if (!mounted) return;
      _apply(l, fillForms: fillForms);
    } catch (e) {
      if (mounted) setState(() => _error = errorText(e));
    }
  }

  void _apply(EditableListing l, {required bool fillForms}) {
    setState(() {
      _listing = l;
      if (fillForms) {
        _title.text = l.title ?? '';
        _description.text = l.description ?? '';
        _price.text = _s(l.priceXof);
        _deposit.text = _s(l.depositMonths);
        _advance.text = _s(l.advanceMonths);
        _agencyFee.text = _s(l.agencyFeeXof);
        _otherFees.text = _s(l.otherFeesXof);
        _address.text = l.address ?? '';
        _surface.text = _s(l.surfaceM2);
        _rooms.text = _s(l.rooms);
        _bedrooms.text = _s(l.bedrooms);
        _bathrooms.text = _s(l.bathrooms);
        _negotiable = l.negotiable;
        _furnished = l.furnished;
        _locality = l.localityId;
        _amenityIds = l.amenityIds.toSet();
      }
    });
    // Les photos sont traitées en arrière-plan : on relit l'annonce tant que l'une d'elles est « en traitement ».
    _poll?.cancel();
    if (l.processing) {
      _poll = Timer(const Duration(seconds: 3), () => _load());
    }
  }

  Future<void> _run(
    Future<EditableListing> Function() action,
    String done, {
    bool fillForms = true,
  }) async {
    setState(() {
      _busy = true;
      _error = null;
      _notice = null;
    });
    try {
      final l = await action();
      if (!mounted) return;
      _apply(l, fillForms: fillForms);
      setState(() => _notice = done);
    } catch (e) {
      if (mounted) setState(() => _error = errorText(e));
    }
    if (mounted) setState(() => _busy = false);
  }

  void _addInt(Map<String, dynamic> body, String key, TextEditingController c) {
    final r = parseInt(c.text);
    if (r.valid) body[key] = r.value;
  }

  Future<void> _saveInfo(EditableListing l) {
    final body = <String, dynamic>{
      'title': _title.text.trim().isEmpty ? null : _title.text.trim(),
      'description': _description.text.trim().isEmpty
          ? null
          : _description.text.trim(),
      'negotiable': _negotiable,
      'draft_step': 2,
    };
    _addInt(body, 'price_xof', _price);
    if (l.rental) {
      _addInt(body, 'deposit_months', _deposit);
      _addInt(body, 'advance_months', _advance);
      _addInt(body, 'agency_fee_xof', _agencyFee);
      _addInt(body, 'other_fees_xof', _otherFees);
    }
    return _run(
      () => widget.repository.update(l.id, body),
      'Informations enregistrées.',
    );
  }

  Future<void> _saveProperty(EditableListing l) {
    final body = <String, dynamic>{
      'address': _address.text.trim().isEmpty ? null : _address.text.trim(),
      'furnished': _furnished,
      'amenity_ids': _amenityIds.toList(),
      'draft_step': 3,
      if (_locality != null) 'locality_id': _locality,
    };
    _addInt(body, 'surface_m2', _surface);
    _addInt(body, 'rooms', _rooms);
    _addInt(body, 'bedrooms', _bedrooms);
    _addInt(body, 'bathrooms', _bathrooms);
    return _run(() => widget.repository.update(l.id, body), 'Bien enregistré.');
  }

  Future<void> _pickPhotos(EditableListing l) async {
    // Réduction avant envoi : les photos de téléphone dépassent vite la limite du serveur.
    final picked = await (widget.picker ?? ImagePicker()).pickMultiImage(
      imageQuality: 85,
      maxWidth: 2000,
    );
    if (picked.isEmpty) return;
    final photos = [
      for (final f in picked) (name: f.name, bytes: await f.readAsBytes()),
    ];
    await _run(
      () => widget.repository.uploadPhotos(l.id, photos),
      'Photos envoyées : traitement en cours.',
      fillForms: false,
    );
  }

  Future<void> _photoAction(
    EditableListing l,
    Future<void> Function() action,
    String done,
  ) async {
    await _run(
      () async {
        await action();
        return widget.repository.get(l.id);
      },
      done,
      fillForms: false,
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = _listing;
    return Scaffold(
      appBar: AppBar(title: Text(l?.reference ?? 'Annonce')),
      body: l == null
          ? Center(
              child: _error != null
                  ? Text(_error!)
                  : const CircularProgressIndicator(),
            )
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (l.status == 'rejected')
                  Text(
                    'Refusée${l.rejectionComment != null ? ' : ${l.rejectionComment}' : ''}. Corrigez puis soumettez à nouveau.',
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                if (_error != null)
                  Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                if (_notice != null) Text(_notice!),
                _section('1. L\'annonce', [
                  AppField(controller: _title, label: 'Titre'),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _description,
                    minLines: 3,
                    maxLines: 8,
                    decoration: const InputDecoration(
                      labelText: 'Description',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 12),
                  AppField(
                    controller: _price,
                    label: l.rental ? 'Loyer mensuel (FCFA)' : 'Prix (FCFA)',
                    keyboard: TextInputType.number,
                  ),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Prix négociable'),
                    value: _negotiable,
                    onChanged: (v) => setState(() => _negotiable = v),
                  ),
                  if (l.rental) ...[
                    AppField(
                      controller: _deposit,
                      label: 'Caution (mois de loyer)',
                      keyboard: TextInputType.number,
                    ),
                    const SizedBox(height: 12),
                    AppField(
                      controller: _advance,
                      label: 'Avance (mois de loyer)',
                      keyboard: TextInputType.number,
                    ),
                    const SizedBox(height: 12),
                    AppField(
                      controller: _agencyFee,
                      label: "Frais d'agence (FCFA)",
                      keyboard: TextInputType.number,
                    ),
                    const SizedBox(height: 12),
                    AppField(
                      controller: _otherFees,
                      label: 'Autres frais (FCFA)',
                      keyboard: TextInputType.number,
                    ),
                    const Text(
                      'Montants affichés tels que saisis, sans total calculé.',
                      style: TextStyle(fontSize: 12),
                    ),
                  ],
                  PrimaryButton(
                    label: 'Enregistrer',
                    pending: _busy,
                    onPressed: () => _saveInfo(l),
                  ),
                ]),
                _section('2. Le bien', [
                  DropdownButtonFormField<int>(
                    initialValue: _neighborhoods.any((n) => n.id == _locality)
                        ? _locality
                        : null,
                    decoration: const InputDecoration(
                      labelText: 'Quartier',
                      border: OutlineInputBorder(),
                    ),
                    items: [
                      for (final n in _neighborhoods)
                        DropdownMenuItem(value: n.id, child: Text(n.label)),
                    ],
                    onChanged: (v) => setState(() => _locality = v),
                  ),
                  const SizedBox(height: 12),
                  AppField(
                    controller: _address,
                    label: 'Adresse (jamais affichée publiquement)',
                  ),
                  const SizedBox(height: 12),
                  AppField(
                    controller: _surface,
                    label: 'Surface (m²)',
                    keyboard: TextInputType.number,
                  ),
                  const SizedBox(height: 12),
                  AppField(
                    controller: _rooms,
                    label: 'Pièces',
                    keyboard: TextInputType.number,
                  ),
                  const SizedBox(height: 12),
                  AppField(
                    controller: _bedrooms,
                    label: 'Chambres',
                    keyboard: TextInputType.number,
                  ),
                  const SizedBox(height: 12),
                  AppField(
                    controller: _bathrooms,
                    label: 'Salles de bain',
                    keyboard: TextInputType.number,
                  ),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Meublé'),
                    value: _furnished,
                    onChanged: (v) => setState(() => _furnished = v),
                  ),
                  Wrap(
                    spacing: 8,
                    children: [
                      for (final a in _amenities)
                        FilterChip(
                          label: Text(a.label),
                          selected: _amenityIds.contains(a.id),
                          onSelected: (on) => setState(
                            () => on
                                ? _amenityIds.add(a.id)
                                : _amenityIds.remove(a.id),
                          ),
                        ),
                    ],
                  ),
                  PrimaryButton(
                    label: 'Enregistrer',
                    pending: _busy,
                    onPressed: () => _saveProperty(l),
                  ),
                ]),
                _section('3. Photos', [
                  OutlinedButton.icon(
                    icon: const Icon(Icons.add_a_photo_outlined),
                    label: const Text('Ajouter des photos'),
                    onPressed: _busy ? null : () => _pickPhotos(l),
                  ),
                  if (l.processing)
                    const Text('Traitement des photos en cours…'),
                  for (final img in l.images)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: SizedBox(
                        width: 64,
                        child: img.status == 'ready' && img.thumbUrl != null
                            ? Image.network(
                                img.thumbUrl!,
                                fit: BoxFit.cover,
                                errorBuilder: (_, _, _) =>
                                    const Icon(Icons.image_outlined),
                              )
                            : const Icon(Icons.hourglass_empty),
                      ),
                      title: Text(
                        img.isCover
                            ? 'Couverture'
                            : (img.status == 'failed'
                                  ? 'Échec du traitement'
                                  : (img.status == 'processing'
                                        ? 'Traitement…'
                                        : 'Photo')),
                      ),
                      subtitle: !img.isCover && img.status == 'ready'
                          ? TextButton(
                              onPressed: () => _photoAction(
                                l,
                                () => widget.repository.setCover(l.id, img.id),
                                'Couverture modifiée.',
                              ),
                              child: const Align(
                                alignment: Alignment.centerLeft,
                                child: Text('Définir comme couverture'),
                              ),
                            )
                          : null,
                      trailing: IconButton(
                        tooltip: 'Supprimer',
                        icon: const Icon(Icons.delete_outline),
                        onPressed: () => _photoAction(
                          l,
                          () => widget.repository.deleteImage(l.id, img.id),
                          'Photo supprimée.',
                        ),
                      ),
                    ),
                ]),
                if (l.canSubmit)
                  _section('4. Publier', [
                    Text(
                      l.missingFields.isEmpty
                          ? 'Votre annonce est complète. Elle sera relue par notre équipe avant publication.'
                          : 'Il manque : ${l.missingFields.map((f) => missingLabels[f] ?? f).join(', ')}.',
                    ),
                    PrimaryButton(
                      label: 'Soumettre à la modération',
                      pending: _busy,
                      onPressed: l.missingFields.isEmpty
                          ? () => _run(
                              () => widget.repository.submit(l.id),
                              'Annonce envoyée en modération.',
                              fillForms: false,
                            )
                          : null,
                    ),
                  ]),
              ],
            ),
    );
  }

  Widget _section(String title, List<Widget> children) => Card(
    margin: const EdgeInsets.only(top: 16),
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(title, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 12),
          ...children,
        ],
      ),
    ),
  );
}
