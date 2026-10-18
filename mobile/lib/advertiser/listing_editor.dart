import 'dart:async';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../ui/theme.dart';
import '../ui/widgets.dart';
import 'editor_repository.dart';

class NewListingScreen extends StatefulWidget {
  const NewListingScreen({super.key, required this.repository});

  final EditorRepository repository;

  @override
  State<NewListingScreen> createState() => _NewListingScreenState();
}

class _NewListingScreenState extends State<NewListingScreen> {
  late Future<List<Option>> _types = widget.repository.propertyTypes();
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

  static IconData _icon(String label) {
    final l = label.toLowerCase();
    if (l.contains('appart')) return Icons.apartment_outlined;
    if (l.contains('maison') || l.contains('villa')) return Icons.home_outlined;
    if (l.contains('studio')) return Icons.single_bed_outlined;
    if (l.contains('chambre')) return Icons.bed_outlined;
    if (l.contains('terrain')) return Icons.landscape_outlined;
    if (l.contains('bureau')) return Icons.business_center_outlined;
    if (l.contains('local')) return Icons.storefront_outlined;
    if (l.contains('entrep')) return Icons.warehouse_outlined;
    return Icons.domain_outlined;
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: FutureBuilder<List<Option>>(
        future: _types,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return ErrorRetry(
              message: errorText(snap.error!),
              onRetry: () => setState(() {
                _types = widget.repository.propertyTypes();
              }),
            );
          }
          return Column(
            children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                  children: [
                    Row(
                      children: [
                        IconButton.outlined(
                          tooltip: 'Retour',
                          onPressed: () => Navigator.of(context).maybePop(),
                          icon: const Icon(Icons.arrow_back),
                        ),
                        const SizedBox(width: 12),
                        const Text(
                          'Publier une annonce',
                          style: TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 18,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),
                    Row(
                      children: [
                        for (final (i, label) in const [
                          'Infos',
                          'Détails',
                          'Photos',
                          'Aperçu',
                        ].indexed)
                          Expanded(
                            child: Column(
                              children: [
                                CircleAvatar(
                                  radius: 16,
                                  backgroundColor: i == 0
                                      ? AppColors.primary
                                      : const Color(0xFFEDE8E3),
                                  child: Text(
                                    '${i + 1}',
                                    style: TextStyle(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 13,
                                      color: i == 0
                                          ? Colors.white
                                          : AppColors.muted,
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  label,
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: AppColors.muted,
                                  ),
                                ),
                              ],
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 22),
                    const Eyebrow('Commençons par l\'essentiel'),
                    const SizedBox(height: 6),
                    const AccentHeadline(
                      'Quel bien souhaitez-vous ',
                      'publier ?',
                      size: 30,
                    ),
                    const SizedBox(height: 20),
                    const Text(
                      'Type d\'opération',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 8),
                    SegmentedButton<String>(
                      showSelectedIcon: false,
                      segments: const [
                        ButtonSegment(
                          value: 'rent_long',
                          label: Text('Location'),
                        ),
                        ButtonSegment(value: 'sale', label: Text('Vente')),
                      ],
                      selected: {_transaction},
                      onSelectionChanged: (v) =>
                          setState(() => _transaction = v.first),
                    ),
                    const SizedBox(height: 20),
                    const Text(
                      'Type de bien',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 8),
                    GridView.count(
                      crossAxisCount: 3,
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      mainAxisSpacing: 10,
                      crossAxisSpacing: 10,
                      childAspectRatio: 1.05,
                      children: [
                        for (final t in snap.data!)
                          InkWell(
                            borderRadius: BorderRadius.circular(AppRadius.md),
                            onTap: () => setState(() => _type = t.id),
                            child: Container(
                              decoration: BoxDecoration(
                                color: _type == t.id
                                    ? AppColors.primarySoft
                                    : Colors.white,
                                borderRadius: BorderRadius.circular(
                                  AppRadius.md,
                                ),
                                border: Border.all(
                                  color: _type == t.id
                                      ? AppColors.primary
                                      : AppColors.line,
                                  width: _type == t.id ? 2 : 1,
                                ),
                              ),
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(
                                    _icon(t.label),
                                    size: 30,
                                    color: _type == t.id
                                        ? AppColors.primary
                                        : AppColors.ink,
                                  ),
                                  const SizedBox(height: 8),
                                  Padding(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 4,
                                    ),
                                    child: Text(
                                      t.label,
                                      textAlign: TextAlign.center,
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        fontSize: 13,
                                        fontWeight: _type == t.id
                                            ? FontWeight.w800
                                            : FontWeight.w500,
                                        color: _type == t.id
                                            ? AppColors.primary
                                            : AppColors.ink,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                      ],
                    ),
                    if (_error != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 12),
                        child: Text(
                          _error!,
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.error,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
                child: PrimaryButton(
                  label: 'Continuer vers les détails',
                  pending: _pending,
                  arrow: true,
                  onPressed: _create,
                ),
              ),
            ],
          );
        },
      ),
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

  int _step = 0;

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
                _Stepper(
                  listing: l,
                  current: _step,
                  onSelect: (i) => setState(() => _step = i),
                ),
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
                if (_step == 0)
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
                if (_step == 1)
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
                if (_step == 2)
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
                                  () =>
                                      widget.repository.setCover(l.id, img.id),
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
                if (_step == 3)
                  _section(
                    '4. Publier',
                    l.canSubmit
                        ? [
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
                          ]
                        : [
                            Text(
                              l.missingFields.isEmpty
                                  ? 'Enregistrez les étapes précédentes pour pouvoir soumettre.'
                                  : 'Il manque : ${l.missingFields.map((f) => missingLabels[f] ?? f).join(', ')}.',
                            ),
                          ],
                  ),
                Padding(
                  padding: const EdgeInsets.only(top: 16),
                  child: Row(
                    children: [
                      if (_step > 0)
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () => setState(() => _step--),
                            child: const Text('Précédent'),
                          ),
                        ),
                      if (_step > 0 && _step < 3) const SizedBox(width: 12),
                      if (_step < 3)
                        Expanded(
                          child: FilledButton(
                            onPressed: () => setState(() => _step++),
                            child: const Text('Suivant'),
                          ),
                        ),
                    ],
                  ),
                ),
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

/// Progression en 4 étapes (design) : chaque étape est « faite » selon les champs réellement renseignés.
class _Stepper extends StatelessWidget {
  const _Stepper({
    required this.listing,
    required this.current,
    required this.onSelect,
  });

  final EditableListing listing;
  final int current;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) {
    final l = listing;
    final missing = l.missingFields;
    final done = [
      !missing.contains('title') &&
          !missing.contains('description') &&
          !missing.contains('price_xof'),
      !missing.contains('locality_id') && !missing.contains('surface_m2'),
      !missing.contains('cover_image'),
      !l.canSubmit,
    ];
    const labels = ['Infos', 'Détails', 'Photos', 'Aperçu'];
    return Row(
      children: [
        for (var i = 0; i < 4; i++)
          Expanded(
            child: InkWell(
              onTap: () => onSelect(i),
              child: Column(
                children: [
                  CircleAvatar(
                    radius: 15,
                    backgroundColor: done[i] ? AppColors.primary : Colors.white,
                    child: done[i]
                        ? const Icon(Icons.check, size: 16, color: Colors.white)
                        : Text(
                            '${i + 1}',
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                              color: AppColors.muted,
                            ),
                          ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    labels[i],
                    style: TextStyle(
                      fontSize: 11,
                      color: done[i] || i == current
                          ? AppColors.primary
                          : AppColors.muted,
                      fontWeight: i == current
                          ? FontWeight.w900
                          : FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}
