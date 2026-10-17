import 'package:chezvous/ui/theme.dart';
import 'package:flutter/painting.dart';
import 'package:flutter_test/flutter_test.dart';

double _contrast(Color a, Color b) {
  final la = a.computeLuminance();
  final lb = b.computeLuminance();
  final hi = la > lb ? la : lb;
  final lo = la > lb ? lb : la;
  return (hi + 0.05) / (lo + 0.05);
}

void main() {
  const white = Color(0xFFFFFFFF);

  test('textes courants : contraste WCAG AA (4,5) sur chaque fond utilisé', () {
    final pairs = <String, (Color, Color)>{
      'texte sur blanc': (AppColors.ink, AppColors.surface),
      'texte sur sable': (AppColors.ink, AppColors.sand),
      'texte sur fond de page': (AppColors.ink, AppColors.surfaceSoft),
      'texte secondaire sur blanc': (AppColors.muted, AppColors.surface),
      'texte secondaire sur sable': (AppColors.muted, AppColors.sand),
      'texte secondaire sur fond doux': (
        AppColors.muted,
        AppColors.primarySoft,
      ),
      'lien sur blanc': (AppColors.primary, AppColors.surface),
      'lien sur fond de page': (AppColors.primary, AppColors.surfaceSoft),
      'lien sur fond doux': (AppColors.primary, AppColors.primarySoft),
      'accent sur blanc': (AppColors.accent, AppColors.surface),
      'erreur sur blanc': (AppColors.danger, AppColors.surface),
      'succès sur blanc': (AppColors.success, AppColors.surface),
      'blanc sur bouton': (white, AppColors.primary),
      'blanc sur section': (white, AppColors.primaryHover),
      'texte doux sur section': (
        const Color(0xFFFFE9DA),
        AppColors.primaryHover,
      ),
    };
    for (final e in pairs.entries) {
      expect(
        _contrast(e.value.$1, e.value.$2),
        greaterThanOrEqualTo(4.5),
        reason: e.key,
      );
    }
  });

  test('les couleurs du logo ne servent qu\'au décor et aux grands textes', () {
    expect(_contrast(white, AppColors.brandOrange), lessThan(4.5));
    expect(_contrast(AppColors.brandRed, white), greaterThanOrEqualTo(3.0));
  });
}
