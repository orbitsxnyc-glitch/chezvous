import 'package:chezvous/core/format.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('formate les prix en francs CFA entiers', () {
    expect(groupThousands(450000), '450 000');
    expect(groupThousands(50000000), '50 000 000');
    expect(groupThousands(999), '999');
    expect(formatPrice(450000, 'rent_long'), '450 000 FCFA / mois');
    expect(formatPrice(50000000, 'sale'), '50 000 000 FCFA');
    expect(formatPrice(null, 'sale'), 'Prix non communiqué');
  });

  test('la fraîcheur est toujours datée', () {
    final now = DateTime(2026, 10, 14, 12);
    expect(
      freshnessLabel('available', now.subtract(const Duration(hours: 5)), now),
      'Disponible · confirmé il y a 5 h',
    );
    expect(
      freshnessLabel('available', now.subtract(const Duration(days: 3)), now),
      'Disponible · confirmé il y a 3 jours',
    );
    expect(freshnessLabel('to_reconfirm', null, now), 'À reconfirmer');
    expect(freshnessLabel('stale', now, now), contains('à vérifier'));
  });
}
