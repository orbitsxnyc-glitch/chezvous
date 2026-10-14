/// Montant entier avec espaces : 450000 -> « 450 000 ».
String groupThousands(int value) {
  final digits = value.abs().toString();
  final buf = StringBuffer(value < 0 ? '-' : '');
  for (var i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) buf.write(' ');
    buf.write(digits[i]);
  }
  return buf.toString();
}

/// « 450 000 FCFA / mois » pour une location, « 50 000 000 FCFA » pour une vente.
String formatPrice(int? xof, String transaction) {
  if (xof == null) return 'Prix non communiqué';
  final base = '${groupThousands(xof)} FCFA';
  return switch (transaction) {
    'rent_long' => '$base / mois',
    'rent_furnished_short' => '$base / nuit',
    _ => base,
  };
}

String relativeSince(DateTime date, DateTime now) {
  final minutes = now.difference(date).inMinutes;
  if (minutes < 60) return "il y a moins d'une heure";
  final hours = minutes ~/ 60;
  if (hours < 24) return 'il y a $hours h';
  final days = hours ~/ 24;
  return 'il y a $days jour${days > 1 ? 's' : ''}';
}

/// Texte du badge de fraîcheur : toujours daté, jamais une simple promesse.
String freshnessLabel(String state, DateTime? confirmedAt, [DateTime? now]) {
  final since = confirmedAt == null
      ? null
      : relativeSince(confirmedAt, now ?? DateTime.now());
  return switch (state) {
    'available' =>
      since != null ? 'Disponible · confirmé $since' : 'Disponible',
    'reserved' => since != null ? 'Réservé · confirmé $since' : 'Réservé',
    'to_reconfirm' =>
      since != null
          ? 'À reconfirmer · dernière confirmation $since'
          : 'À reconfirmer',
    _ => 'Information ancienne : disponibilité à vérifier',
  };
}

const checkLabels = {
  'phone': 'Téléphone vérifié',
  'identity': 'Identité vérifiée',
  'professional': 'Professionnel vérifié',
  'location': 'Localisation vérifiée',
  'document': 'Document contrôlé',
  'field_visit': 'Visite terrain effectuée',
};
